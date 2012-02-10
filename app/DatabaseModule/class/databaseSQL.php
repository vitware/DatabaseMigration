<?php

use Nette\Diagnostics\Debugger;
/**
 * Stará se vytváření SQL příkazů
 *
 * @author pH
 */
class databaseSQL extends Nette\Object
{
	
	/**
	 * Vrátí SQL příkaz k vytvoření tabulky na základě matice
	 * 
	 * @param array $source 
	 * @return string SQL příkaz k přidání tabulky
	 */
	public static function createTable($source)
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
					$sql .= (self::columnPartSQL($column));
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
	public static function dropTable($tableName)
	{
		return "#" . $tableName . "\nDROP TABLE `" . $tableName . "`; \n\n";
	}

	public static function updateTable($source, $destination)
	{
		$sql = '#' . $source['__table']['Name'] . "\n";
		/**
		 * ALTER TABLE `newtable-s`
		  CHANGE `asdf` `asdf-kjkj` int(11) NOT NULL COMMENT ' DBM-1fa86t3dkz' AUTO_INCREMENT FIRST,
		  RENAME TO `newtable`,
		  COMMENT=' DBM-aibmpvjnxg'
		  REMOVE PARTITIONING;
		 * 
		 * ALTER TABLE `newtable`
		  DROP `d`,
		  ADD `g` int(11) NULL AFTER `c`,
		  COMMENT=' DBM-aibmpvjnxg'
		  REMOVE PARTITIONING;
		 */
		$sql .= "ALTER TABLE `" . $destination['__table']['Name'] . "` \n";

		// odstraní sloupce
		foreach ($destination as $key => $column) {
			if (!isSet($source[$key])) {
				$sql .= "\t DROP `" . $column['Field'] . "` \n";
			}
		}

		foreach ($source as $key => $column) {
			if ($key === '__table') {
				
			} elseif (!isSet($destination[$key])) {
				// nový sloupec
				$sql .= "\t ADD `" . self::columnPartSQL($column) . "` \n";
			} else {
				if ($destination[$key]['Field'] != $column['Field'] ||
					$destination[$key]['Type'] != $column['Type'] ||
					$destination[$key]['Null'] != $column['Null'] ||
					$destination[$key]['Default'] != $column['Default'] ||
					$destination[$key]['Comment'] != $column['Comment']
				) {
					// změnil se sloupec
					$sql .= "\t CHANGE `" . $destination[$key]['Field'] . '` ' . self::columnPartSQL($column);
				}
			}
		}





		if ($destination['__table']['Name'] !== $source['__table']['Name']) {
			$sql .= "RENAME TO `" . $source['__table']['Name'] . "` \n";
		}

		$sql .= "COMMENT='" . $source['__table']['Comment'] . "'";
		
		$sql .= ";";

		$sql .= "\n\n";

		return $sql;
	}

	/**
	 * Vrátí část SQL s informacemi o sloupci
	 * 
	 * @param type $column 
	 * @return string část sql příkazu
	 */
	private static function columnPartSQL($column)
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

?>
