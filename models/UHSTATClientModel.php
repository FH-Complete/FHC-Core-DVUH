<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/ClientModel.php';

class UHSTATClientModel extends ClientModel
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('extensions/FHC-Core-DVUH/uhstat/UHSTATClientLib');
		$this->_clientLib = $this->uhstatclientlib;
	}
}
