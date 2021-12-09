<?php

class DVUHPruefungsaktivitaeten_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_dvuh_pruefungsaktivitaeten';
		$this->pk = 'pruefungsaktivitaeten_id';
	}
}
