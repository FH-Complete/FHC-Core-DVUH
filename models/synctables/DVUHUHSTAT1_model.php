<?php

class DVUHUHSTAT1_model extends DB_Model
{
	/**
	 * Model for saving sync entries after UHSTAT1 data was sent.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_bis_uhstat1';
		$this->pk = 'uhstat1_id';
	}
}
