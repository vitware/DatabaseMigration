<?php

/**
 * Porovnává strukturu db
 *
 * @author pH
 */
class databaseCompare extends Nette\Object
{
	
	/**
	 * Porovná aktuální databázi s databázi, která je uložena v souboru
	 * 
	 * @param array $source zdrojová tabulka, zdoj jak má vypadat výsledek
	 * @param array $destination  cílová tabulka na ni se budou aplikovat změny
	 */
	public static function compareDatabase(array $source, array $destination)
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
				$report[$key] = self::compareTable($source_table, $destination[$key]);
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
	public static function compareTable(array $source, array $destination)
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

}

?>
