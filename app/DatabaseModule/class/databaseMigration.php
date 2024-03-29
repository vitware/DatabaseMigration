<?php

use Nette\Diagnostics\Debugger,
	Nette\Utils\Neon;

/**
 * Stará se o migraci db
 *
 * @author pH
 */
class databaseMigration extends Nette\Object
{
	const COMMENT_PREFIX = 'DBM-',
	COMMENT_LENGHT = 10;

	/** @var Nette\Database\Connection */
	private $database;

	/** @var string */
	private $fileName;
	private $active;
	private $saved;

	public function __construct(Nette\Database\Connection $database, $fileName)
	{
		$this->database = $database;
		$this->fileName = $fileName;
	}

	/**
	 * Vrátí strukturu db, ke které je připojený
	 */
	public function getActive()
	{
		if (!isSet($this->active)) {
			$this->active = $this->analyzeDatabase();
		}
		return $this->active;
	}

	/**
	 * Vrátí strukuru db uloženou do souboru
	 * 
	 */
	public function getSaved()
	{
		if (!isSet($this->saved)) {
			$this->saved = Neon::decode(file_get_contents($this->fileName));
		}

		return $this->saved;
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
				$id = $table['Name']; // tabulka, která nemá přidělené ID pooužije svůj název
			} else {
				$id = $this->getID($table['Comment']);
			}

			// ochrana před tím, aby víc tabulek mělo stejné id
			if (isSet($report[$id])) {
				throw new Exception('Podezření na duplicitní ID');
			}

			$report[$id] = $this->analyzeTable($table['Name'], $table['Comment']);
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

		$keys = $this->database->query('select * from information_schema.KEY_COLUMN_USAGE  where
			`CONSTRAINT_SCHEMA` = SCHEMA() AND 
			`REFERENCED_TABLE_NAME` IS NOT NULL AND
			`TABLE_NAME` = "' . $tableName . '"')->fetchAll();

		foreach ($this->database->query('SHOW FULL COLUMNS FROM `' . $tableName . '`') as $column) {
			if (strlen($column['Comment']) <= 0 || strPoS($column['Comment'], $this::COMMENT_PREFIX) === FALSE) {
				$id = $column['Field']; // sloupec, který nemá přidělené ID, použije svůj název
			} else {
				$id = $this->getID($column['Comment']);
			}

			// ochrana před duplicitním id
			if (isSet($report[$id])) {
				throw new Exception('Podezření na duplicitní ID v tabulce: ' . $tableName . ', ve sloupci: ' . $id);
			}


			// zjistí cizí klíče
			$reference = NULL;
			foreach ($keys as $key) {
				if ($key['COLUMN_NAME'] == $column['Field']) {
					$reference[$key['CONSTRAINT_NAME']] = Array(
						'Name' => $key['CONSTRAINT_NAME'],
						'Table' => $key['REFERENCED_TABLE_NAME'],
						'Column' => $key['REFERENCED_COLUMN_NAME'],
					);
				}
			}

			$columns[$id] = array(
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

	public function compareDatabase($reverse = false)
	{
		if ($reverse == false) {
			return databaseCompare::compareDatabase($this->getSaved(), $this->getActive());
		} else {
			return databaseCompare::compareDatabase($this->getActive(), $this->getSaved());
		}
	}

	private function createCommentTables()
	{
		foreach ($this->database->query('SHOW TABLE STATUS') as $table) {
			// projde pouze tabulky, které neobsahují komentář
			if (strlen($table['Comment']) <= 0 || strPoS($table['Comment'], self::COMMENT_PREFIX) === FALSE) {
				// bude hledat nenalezne klíč, které ještě nebyl přidělen
				do {
					$comment = self::COMMENT_PREFIX . \Nette\Utils\Strings::random(self::COMMENT_LENGHT);
					$this->database->query("ALTER TABLE `" . $table['Name'] . "` COMMENT '" . $table['Comment'] . ' ' . $comment . "'");
					$row = $this->database->query('SHOW TABLE STATUS WHERE COMMENT LIKE "%' . $comment . '"');
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
		foreach ($this->database->query('SHOW FULL COLUMNS FROM `' . $tableName . '`') as $column) {
			// projde pouze tabulky, které neobsahují komentář
			if (strlen($column['Comment']) <= 0 || strPoS($column['Comment'], self::COMMENT_PREFIX) === FALSE) {
				// bude hledat nenalezne klíč, které ještě nebyl přidělen
				do {
					$comment = self::COMMENT_PREFIX . \Nette\Utils\Strings::random(self::COMMENT_LENGHT);
					$nColumn = $column;
					$nColumn['Comment'] = $column['Comment'] . ' ' . $comment;
					$this->database->query("ALTER TABLE `" . $tableName . "`\n\t CHANGE `" . $column['Field'] . "` " . databaseSQL::columnPartSQL($nColumn, FALSE) . ";");
					$row = $this->database->query('SHOW FULL COLUMNS FROM `' . $tableName . '` where COMMENT LIKE "%' . $column['Comment'] . '"');
				} while (!$row);
			}
		}
	}

	public function Save()
	{
		if (!$this->allHasId()) {
			$this->createCommentTables();
		}
		$structure = $this->getActive();
		file_put_contents($this->fileName, Neon::encode($structure, 1));
	}

	/**
	 * Zjistí, zda všechny tabulky mají své ID
	 * @return boolean 
	 */
	private function allHasId()
	{
		$allHasId = TRUE;
		// projde všechny tabulky
		foreach ($this->database->query('SHOW TABLE STATUS') as $table) {
			if (strlen($table['Comment']) <= 0 || strPoS($table['Comment'], $this::COMMENT_PREFIX) === FALSE) {
				$allHasId = FALSE;
				break;
			}
			foreach ($this->database->query('SHOW FULL COLUMNS FROM ' . $table['Name']) as $column) {
				if (strlen($column['Comment']) <= 0 || strPoS($column['Comment'], $this::COMMENT_PREFIX) === FALSE) {
					$allHasId = FALSE;
					break 2;
				}
			}
		}
		return $allHasId;
	}

	public function getSQL($reverse = false)
	{
		if ($reverse == false) {
			$source = $this->getSaved();
			$destination = $this->getActive();
			$compare = $this->compareDatabase();
		} else {
			$source = $this->getActive();
			$destination = $this->getSaved();
			$compare = $this->compareDatabase(true);
		}

		$sql = "SET foreign_key_checks = 0;\n\n";
		// projede všechny tabulky 
		foreach ($compare as $key => $table) {
			if (is_array($table)) {
				// došlo k změně uvnitř tabulky
				$sql .= databaseSQL::updateTable($source[$key], $destination[$key]);
			} elseif ($table == '+') {
				// musíme vytvořit tabulku
				$sql .= databaseSQL::createTable($source[$key]);
			} elseif ($table == '-') {
				// musíme smazat tabulku
				$sql .= databaseSQL::dropTable($destination[$key]['__table']['Name']);
			}
		}

		return $sql;
	}

}

?>
