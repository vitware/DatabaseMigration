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
		if (! isSet($this->active)) {
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

	public function compareDatabase()
	{
		return databaseCompare::compareDatabase($this->getSaved(), $this->getActive());
	}

	private function createCommentTables()
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

	public function Save()
	{
		if (!$this->allHasId()) {
			$this->createCommentTables();
		}
		$structure = $this->getActive();
		file_put_contents($this->fileName, Neon::encode($structure, 1));
	}

	public function allHasId()
	{
		return true;
	}
	
	public function getSQL()
	{
		$source = $this->getSaved();
		$destination = $this->getActive();
		$compare = $this->compareDatabase();
		$sql = '';
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
