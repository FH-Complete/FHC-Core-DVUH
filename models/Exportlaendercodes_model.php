<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Codex Laender Codes
 */
class Exportlaendercodes_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'exportLaendercodes';
	}

	public function get()
	{
		$params = array(
			"uuid" => getUUID()
		);

		$result = $this->_call(ClientLib::HTTP_GET_METHOD, $params);
		return $result;
	}
}
