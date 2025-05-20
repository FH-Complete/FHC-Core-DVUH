<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/ClientModel.php';

class DVUHClientModel extends ClientModel
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('extensions/FHC-Core-DVUH/DVUHClientLib');
		$this->_clientLib = $this->dvuhclientlib;
	}
}
