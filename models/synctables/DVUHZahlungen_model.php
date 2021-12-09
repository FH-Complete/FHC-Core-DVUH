<?php

class DVUHZahlungen_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_dvuh_zahlungen';
		$this->pk = 'zahlung_id';
	}
}
