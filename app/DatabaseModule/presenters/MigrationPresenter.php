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

	/** @var \databaseMigration */
	private $dbm;

	/**
	 * (non-phpDoc)
	 *
	 * @see Nette\Application\Presenter#startup()
	 */
	protected function startup()
	{
		parent::startup();

		$this->dbm = new \databaseMigration(
				$this->context->database, // připojení k db, musí být instance Nette\Database\Connection
				APP_DIR . '\config\database.neon' // cesta k souboru
		);
	}

	public function renderDefault()
	{

	}

	/**
	 * Zobrazí strukturu aktuální db
	 */
	public function renderStructure()
	{
		$this->template->tables = $this->dbm->getActive();
	}

	public function actionSave()
	{
		$this->dbm->save();
		$this->flashMessage('Struktura byla uložena','alert-success');
		$this->redirect('load');
	}

	public function createComponentDatabaseStructure()
	{
		return new \databaseStructure();
	}

	public function renderLoad()
	{
		$this->template->tables = $this->dbm->getSaved();
	}

	public function renderCompare()
	{
		$this->template->compare = $this->dbm->compareDatabase();
		$this->template->source = $this->dbm->getSaved();
		$this->template->destination = $this->dbm->getActive();

		$this->template->sql = $this->dbm->getSQL();
	}

}