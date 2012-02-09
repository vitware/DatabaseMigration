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
			Debugger::barDump($table);
			if (strlen($table['Comment']) <= 0 && strPoS($table['Comment'], $this::COMMENT_PREFIX) !== NULL) {
				throw new \Nette\Application\BadRequestException('Spusťte nejdřív \database\create');
			}
			$report[$table['Name']] = $this->analyzeTable($table['Name']);
		}
		return $report;
		Debugger::barDump($report, 'tables');
	}

	/**
	 * Zpracuje analýzu jedné tabulky
	 * 
	 * @param $tableName název tabulky
	 */
	private function analyzeTable($tableName)
	{
		$columns = array();
		foreach ($this->database->query('SHOW FULL COLUMNS FROM ' . $tableName) as $column) {

			// zjistí cizí klíče
			try {
				$reference = $this->database->getDatabaseReflection()->getBelongsToReference($tableName, $column['Field']);
			} catch (\PDOException $e) {
				$reference = NULL;
			}
			$columns[$column['Field']] = array(
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

	public function actionCreate()
	{
		foreach ($this->database->query('SHOW TABLE STATUS') as $table) {
			// projde pouze tabulky, které neobsahují komentář
			if (strlen($table['Comment']) <= 0 && strPoS($table['Comment'], self::COMMENT_PREFIX) !== NULL) {
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
			if (strlen($column['Comment']) <= 0 && strPoS($column['Comment'], self::COMMENT_PREFIX) !== NULL) {
				// bude hledat nenalezne klíč, které ještě nebyl přidělen
				do {
					$comment = self::COMMENT_PREFIX . \Nette\Utils\Strings::random(self::COMMENT_LENGHT);
					$this->database->query("ALTER TABLE " . $tableName . " CHANGE `".$column['Field']."` `".$column['Field']."` ".$column['Type'].", COMMENT='" . $column['Comment'] . " " . $comment . "'");
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
		$this->template->compare = ($this->compareDatabase($source, $destination));
	}

	/**
	 * Porovná aktuální tabulku s tabulkou, která je uložena v souboru
	 * 
	 * @param array $source zdrojová tabulka, zdoj jak má vypadat výsledek
	 * @param array $destination  cílová tabulka na ni se budou aplikovat změny
	 */
	private function compareDatabase(array $source, array $destination)
	{
		$report = array();
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

		// musím projít všechny tabulky, abych zjistil, jestli je tam tabulka navíc
		foreach ($destination as $key => $destination_table) {
			if (!isSet($source[$key])) {
				$report[$key] = '-';
			}
		}

		return $report;
	}

	private function compareTable(array $source, array $destination)
	{
		$report = array();
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

		// musím projít všechny sloupce, abych zjisti, jestli je tam sloupce navíc
		foreach ($destination as $key => $destination_table) {
			if (!isSet($source[$key])) {
				$report[$key] = '-';
			}
		}

		return $report;
	}

}