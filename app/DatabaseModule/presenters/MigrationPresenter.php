<?php

namespace DatabaseModule;

use Nette\Diagnostics\Debugger,
	Nette\Utils\Neon;

/**
 * Description of DatabasePresenter
 *
 * @author pH
 */
class MigrationPresenter extends \BasePresenter
{
	const COMMENT_PREFIX = 'DBM-',
	COMMENT_LENGHT = 10;

	/** @var Nette\Database\Connection */
	private $database;

	/**
	 * (non-phpDoc)
	 *
	 * @see Nette\Application\Presenter#startup()
	 */
	protected function startup()
	{
		parent::startup();
		//$this->database = $this->getService('model')->database;
		$this->database = $this->context->database;
	}

	public function renderDefault()
	{
		//$this->template->tables = $this->analyzeDatabase();
		$this->template->tables = array();
	}

	/**
	 * Zpracuje analýzu celé db
	 * 
	 */
	private function analyzeDatabase()
	{
		// projde všechny tabulky
		$report = array();
		foreach ($this->database->query('SHOW TABLE STATUS') as $table) {
			if (strlen($table['Comment']) <= 0 || strPoS($table['Comment'], $this::COMMENT_PREFIX) === FALSE) {
				throw new \Nette\Application\BadRequestException('Spusťte nejdřív \database\create: ' . $table['Name']);
			}
			$report[$this->getID($table['Comment'])] = $this->analyzeTable($table['Name'], $table['Comment']);
		}
		return $report;
	}

	/**
	 * Zpracuje analýzu jedné tabulky
	 * 
	 * @param $tableName název tabulky
	 */
	private function analyzeTable($tableName, $comment)
	{
		$columns = array();
		$columns['__table'] = array(
			'Name' => $tableName,
			'Comment' => $comment);

		foreach ($this->database->query('SHOW FULL COLUMNS FROM ' . $tableName) as $column) {
			if (strlen($column['Comment']) <= 0 || strPoS($column['Comment'], $this::COMMENT_PREFIX) === FALSE) {
				throw new \Nette\Application\BadRequestException('Spusťte nejdřív \database\create');
			}
			// zjistí cizí klíče
			try {
				$reference = $this->database->getDatabaseReflection()->getBelongsToReference($tableName, $column['Field']);
			} catch (\PDOException $e) {
				$reference = NULL;
			}
			$columns[$this->getID($column['Comment'])] = array(
				'Field' => $column['Field'],
				'Type' => $column['Type'],
				'Null' => $column['Null'],
				'Key' => $column['Key'],
				'Default' => $column['Default'],
				'Extra' => $column['Extra'],
				'Reference' => $reference,
				'Comment' => $column['Comment'],
			);
		}
		return $columns;
	}

	/**
	 * Vrátí ID z commentu
	 * 
	 * @param type $comment
	 * @return type 
	 */
	private function getID($comment)
	{
		return substr($comment, strPos($comment, self::COMMENT_PREFIX) + 4);
	}

	public function actionCreate()
	{
		foreach ($this->database->query('SHOW TABLE STATUS') as $table) {
			// projde pouze tabulky, které neobsahují komentář
			if (strlen($table['Comment']) <= 0 || strPoS($table['Comment'], self::COMMENT_PREFIX) === FALSE) {
				// bude hledat nenalezne klíč, které ještě nebyl přidělen
				do {
					$comment = self::COMMENT_PREFIX . \Nette\Utils\Strings::random(self::COMMENT_LENGHT);
					$this->database->query("ALTER TABLE " . $table['Name'] . " COMMENT '" . $table['Comment'] . ' ' . $comment . "'");
					$row = $this->database->query('SHOW TABLE STATUS WHERE COMMENT LIKE "' . $comment . '%"');
				} while (!$row);
			}
			$this->createCommentColumn($table['Name']);
		}
	}

	/**
	 * Doplní ke všem sloupcům v dané tabulce komentáře
	 * 
	 * @param type $tableName Název tabulky
	 */
	private function createCommentColumn($tableName)
	{
		foreach ($this->database->query('SHOW FULL COLUMNS FROM ' . $tableName) as $column) {
			// projde pouze tabulky, které neobsahují komentář
			if (strlen($column['Comment']) <= 0 || strPoS($column['Comment'], self::COMMENT_PREFIX) === FALSE) {
				// bude hledat nenalezne klíč, které ještě nebyl přidělen
				do {
					$comment = self::COMMENT_PREFIX . \Nette\Utils\Strings::random(self::COMMENT_LENGHT);
					/** @todo občas asi to některé věci mění :-( */
					$this->database->query("ALTER TABLE " . $tableName . " CHANGE `" . $column['Field'] . "` `" . $column['Field'] . "` " . $column['Type'] . " COMMENT '" . $column['Comment'] . " " . $comment . "'");
					$row = $this->database->query('SHOW FULL COLUMNS FROM ' . $tableName . ' where COMMENT LIKE "' . $comment . '%"');
				} while (!$row);
			}
		}
	}

	public function actionSave()
	{
		$structure = $this->analyzeDatabase();
		file_put_contents(APP_DIR . '\config\database.neon', Neon::encode($structure, 1));
		$this->flashMessage('Stav db byl uložen do souboru database.neon', 'success');
		$this->redirect('load');
	}

	public function renderLoad()
	{
		$this->template->tables = Neon::decode(file_get_contents(APP_DIR . '\config\database.neon'));
	}

	public function renderCompare()
	{
		$source = Neon::decode(file_get_contents(APP_DIR . '\config\database.neon'));
		$destination = $this->analyzeDatabase();
		$compare = $this->compareDatabase($source, $destination);


		$this->template->compare = $compare;
		$this->template->source = $source;
		$this->template->destination = $destination;

		$sql = '';
		// projede všechny tabulky 
		foreach ($compare as $key => $table) {
			if (is_array($table)) {
				// došlo k změně uvnitř tabulky
				$sql .= $this->updateTable($source[$key], $destination[$key]);
			} elseif ($table == '+') {
				// musíme vytvořit tabulku
				$sql .= $this->createTable($source[$key]);
			} elseif ($table == '-') {
				// musíme smazat tabulku
				$sql .= $this->dropTable($destination[$key]['__table']['Name']);
			}
		}

		Debugger::barDump($sql);
		$this->template->sql = $sql;
	}

	/**
	 * Porovná aktuální databázi s databázi, která je uložena v souboru
	 * 
	 * @param array $source zdrojová tabulka, zdoj jak má vypadat výsledek
	 * @param array $destination  cílová tabulka na ni se budou aplikovat změny
	 */
	private function compareDatabase(array $source, array $destination)
	{
		$report = array();

		// projde všechny tabulky, abych zjistil, jestli je tam tabulka navíc
		foreach ($destination as $key => $destination_table) {
			if (!isSet($source[$key])) {
				$report[$key] = '-';
			}
		}

		foreach ($source as $key => $source_table) {
			if (!isSet($destination[$key])) {
				// tabulka v cílové část není - musí se přidat
				$report[$key] = '+';
			} elseif ($source_table == $destination[$key]) {
				// tabulka v cílové části je stejná
				$report[$key] = '=';
			} else {
				// tabulka je přítomna, ale musí se změnit
				$report[$key] = $this->compareTable($source_table, $destination[$key]);
			}
		}


		return $report;
	}

	/**
	 * Porovná danou tabulku
	 * 
	 * @param array $source
	 * @param array $destination
	 * @return string 
	 */
	private function compareTable(array $source, array $destination)
	{
		$report = array();

		// projde všechny sloupce, abych zjisti, jestli je tam sloupce navíc
		foreach ($destination as $key => $destination_table) {
			if (!isSet($source[$key])) {
				$report[$key] = '-';
			}
		}
		foreach ($source as $key => $source_column) {
			if (!isSet($destination[$key])) {
				// sloupec v cílové části není - musí se přidat
				$report[$key] = '+';
			} elseif ($source_column == $destination[$key]) {
				// sloupce jsou totožné
				$report[$key] = '=';
			} else {
				// sloupec je změněný
				$report[$key] = '?';
			}
		}



		return $report;
	}

	/**
	 * Vrátí SQL příkaz k vytvoření tabulky na základě matice
	 * 
	 * @param array $source 
	 * @return string SQL příkaz k přidání tabulky
	 */
	private function createTable($source)
	{
		$sql = '#' . $source['__table']['Name'] . "\n";
		$sql .= 'CREATE TABLE `' . $source['__table']['Name'] . '` ';

		// pokus jsou v tabulce nějaké sloupce
		if (count($source) > 1) {
			$sql .= "( \n";
			$i = 0;
			foreach ($source as $key => $column) {
				$i++;
				if ($key !== '__table') {
					$sql .= "\t";
					$sql .= ($this->columnPartSQL($column));
					if ($i < count($source)) {
						$sql .= ', ';
					}
					$sql .= "\n";
				}
			}
			$sql .= ") ";
		}

		$sql .= "COMMENT='" . $source['__table']['Comment'] . "'; \n\n";

		return $sql;
	}

	/**
	 * Vrátí SQL příkaz k odstranění tabulky
	 * 
	 * @param string $tableName Název tabulky
	 * @return string SQL příkaz k odstranění tabulky
	 */
	private function dropTable($tableName)
	{
		return "#" . $tableName . "\nDROP TABLE `" . $tableName . "`; \n\n";
	}

	private function updateTable($source, $destinatin)
	{
		Debugger::barDump($source, 'A');
		$sql = '#' . $source['__table']['Name'] . "\n";
		/**
		 * ALTER TABLE `newtable-s`
		  CHANGE `asdf` `asdf-kjkj` int(11) NOT NULL COMMENT ' DBM-1fa86t3dkz' AUTO_INCREMENT FIRST,
		  RENAME TO `newtable`,
		  COMMENT=' DBM-aibmpvjnxg'
		  REMOVE PARTITIONING;
		 */
		$sql .= "ALTER TABLE `".$destinatin['__table']['Name']."` \n";
		
		
		if ($destinatin['__table']['Name'] !== $source['__table']['Name']) {
			$sql .= "RENAME TO `".$source['__table']['Name']."` \n";
		}
		
		$sql .= "COMMENT='".$source['__table']['Name']."'";
		

		$sql .= "\n\n";

		return $sql;
	}

	/**
	 * Vrátí část SQL s informacemi o sloupci
	 * 
	 * @param type $column 
	 * @return string část sql příkazu
	 */
	private function columnPartSQL($column)
	{
		// jméno sloupce
		$sql = '`' . $column['Field'] . '`';

		// typ sloupce
		$sql .= ' ' . $column['Type'] . ' ';

		// NULL
		if ($column['Null'] == 'NO') {
			$sql .= ' NOT NULL ';
		}

		// Default
		if ($column['Default'] != '') {
			$sql .= " DEFAULT '" . $column['Default'] . "' ";
		}

		$sql .= " COMMENT '" . $column['Comment'] . "' ";

		// AUTO_INCREMENT 
		if ($column['Extra'] != '') {
			$sql .= " " . $column['Extra'] . " ";
		}

		// klíče
		if ($column['Key'] == 'PRI') {
			$sql .= ' PRIMARY KEY ';
		}

		return $sql;
	}

}