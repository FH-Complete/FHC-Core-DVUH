<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Codex BE Codes
 */
class Exportbecodes_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'exportBecodes';
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
