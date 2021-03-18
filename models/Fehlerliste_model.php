<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * List Information for failures
 */
class Fehlerliste_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/0.5/fehlerliste';
	}

	public function get()
	{
		$params = array(
			"uuid" => getUUID()
		);

		$result = $this->_call('GET', $params);
		return $result;
	}
}
