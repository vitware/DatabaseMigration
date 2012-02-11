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

		
		$sql .= "ALTER TABLE `" . $source['__table']['Name'] . "` \n";
		foreach ($source as $key => $column) {
			if ($key === '__table') {
				
			} else {
				$sql .= self::columnPartIndex($column, FALSE);
				$sql .= self::columnPartForeignKey($column['Reference'], NULL, $column);
			}
		}
		$sql .= "COMMENT='" . $source['__table']['Comment'] . "' \n ";
		$sql .= "REMOVE PARTITIONING; \n ";
		$sql .= "\n\n";
		
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

	/**
	 * Vrátí SQL příkaz k upravení tabulky
	 * 
	 * @param type $source 
	 * @param type $destination
	 * @return string 
	 */
	public static function updateTable($source, $destination)
	{
		$sql = '#' . $source['__table']['Name'] . "\n";

		$sql .= "ALTER TABLE `" . $destination['__table']['Name'] . "` \n";

		// odstraní sloupce
		foreach ($destination as $key => $column) {
			if (!isSet($source[$key])) {
				$sql .= "\t DROP `" . $column['Field'] . "`, \n";
			}
		}

		foreach ($source as $key => $column) {
			if ($key === '__table') {
				
			} elseif (!isSet($destination[$key])) {
				// nový sloupec
				$sql .= "\t ADD " . self::columnPartSQL($column) . ", \n";
				$sql .= self::columnPartIndex($column);
				$sql .= self::columnPartForeignKey($column['Reference'], NULL, $column);
			} else {
				if ($destination[$key]['Field'] != $column['Field'] ||
					$destination[$key]['Type'] != $column['Type'] ||
					$destination[$key]['Null'] != $column['Null'] ||
					$destination[$key]['Default'] != $column['Default'] ||
					$destination[$key]['Comment'] != $column['Comment']
				) {
					// změnil se sloupec
					$sql .= "\t CHANGE `" . $destination[$key]['Field'] . '` ' . self::columnPartSQL($column) . ", \n";
				}
				// jestli se změnil index
				if ($destination[$key]['Key'] != $column['Key']) {
					if ($column['Key'] == '') {
						// když nyní žádný není, může ho dropnout
					}
					// přidá nový index
					$sql .= self::columnPartIndex($column);
				}

				// jestli se změnil cizí klíč
				if ($destination[$key]['Reference'] != $column['Reference']) {
					$sql .= self::columnPartForeignKey($column['Reference'], $destination[$key]['Reference'], $column);
				}
			}
		}

		if ($destination['__table']['Name'] !== $source['__table']['Name']) {
			$sql .= "RENAME TO `" . $source['__table']['Name'] . "`, \n";
		}

		$sql .= "COMMENT='" . $source['__table']['Comment'] . "'\n";
		$sql .= "REMOVE PARTITIONING; ";

		$sql .= "\n\n";

		return $sql;
	}

	/**
	 * Vrátí část SQL s informacemi o sloupci
	 * 
	 * @param type $column 
	 * @return string část sql příkazu
	 */
	public static function columnPartSQL($column, $showPrimary = TRUE)
	{
		// jméno sloupce
		$sql = '`' . $column['Field'] . '`';

		// typ sloupce
		$sql .= ' ' . $column['Type'] . ' ';

		// NULL
		if ($column['Null'] == 'NO') {
			$sql .= ' NOT NULL ';
		} else {
			$sql .= ' NULL ';
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

		if ($showPrimary == TRUE) {
			// klíče
			if ($column['Key'] == 'PRI') {
				$sql .= ' PRIMARY KEY ';
			}
		}

		return $sql;
	}

	private static function columnPartIndex($column, $showPrimary = TRUE)
	{
		$sql = "";


		// zjistí jestli má nový sloupec index
		if ($column['Key'] == '') {
		} elseif ($column['Key'] == 'PRI' && $showPrimary = TRUE) {
			$sql .= "\t ADD PRIMARY KEY `" . $column['Field'] . "` (`" . $column['Field'] . "`), \n";
		} elseif ($column['Key'] == 'UNI') {
			$sql .= "\t ADD UNIQUE `" . $column['Field'] . "` (`" . $column['Field'] . "`), \n";
		} else {
			$sql .= "\t ADD INDEX `" . $column['Field'] . "` (`" . $column['Field'] . "`), \n";
		}
		return $sql;
	}

	/**
	 * Vrátí část SQL pro změnu cizích klíčů pro daný sloupec
	 * 
	 * @param array $source 
	 * @param array $destination 
	 */
	private static function columnPartForeignKey($source, $destination, $column)
	{
		$sql = '';

		// projde všechny klíče, které musí odstranit
		if (is_array($destination)) {
			foreach ($destination as $key => $foreignKey) {
				if (!isSet($source[$key])) {
					$sql .= "\t DROP FOREIGN KEY `" . $key . "`,\n";
				}
			}
		}

		// projde všechny klíče
		if (is_array($source)) {
			foreach ($source as $key => $foreignKey) {
				if (!isSet($destination[$key])) {
					// klíč v cílové část není - musí se přidat
					$sql .= "\t ADD CONSTRAINT `" . $foreignKey['Name'] . "` FOREIGN KEY (`" . $column['Field'] . "`) REFERENCES `" . $foreignKey['Table'] . "` (`" . $foreignKey['Column'] . "`), \n";
				} elseif ($source_table == $destination[$key]) {
					// jsou stejné, nemusí se nic dělat					
				} else {
					// došlo ke změně
					$sql .= '# ?? změna!?';
				}
			}
		}
		return $sql;
	}

}

?>
