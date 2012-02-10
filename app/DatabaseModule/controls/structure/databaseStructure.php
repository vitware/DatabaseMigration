<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of databaseStructure
 *
 * @author pH
 */
class databaseStructure extends Nette\Application\UI\Control
{
	public function render($tables)
	{
		$this->template->setFile(__DIR__ . '/databaseStructure.latte'); //Soubor se šablonou
		$this->template->tables = $tables;
		$this->template->render();
	}
}

?>
