<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Reserve a pool of Matrikelnummern
 * List existing pool of Matrikelnummmern
 */
class Matrikelreservierung_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/rws/0.5/matrikelreservierung.xml';
	}
}
