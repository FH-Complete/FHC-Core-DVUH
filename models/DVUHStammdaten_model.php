<?php

class DVUHStammdaten_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_dvuh_stammdaten';
		$this->pk = 'stammdaten_id';
	}
}
