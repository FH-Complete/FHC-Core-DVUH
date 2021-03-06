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

	/**
	 * Performs the Webservie Call to get a list of already reserved Numbers
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $sj Studienjahr
	 */
	public function get($be, $sj)
	{
		$callParametersArray = array(
			'be' => $be,
			'sj' => $sj,
			'uuid' => getUUID()
		);

		$result = $this->_call('GET', $callParametersArray);
		echo print_r($result,true);
		// TODO Parse Result, Handle Errors
	}

	/**
	 * Performs a Webservice Call to Reserve a list of matrikelnumbers
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $sj Studienjahr
	 * @param $anzahl number of matrikelnumbers to reserve
	 */
	public function post($be, $sj, $anzahl)
	{
		$params = array(
			"uuid" => getUUID(),
			"anzahl" => $anzahl,
			"sj" => $sj,
			"be" => $be
		);
		$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/matrikelreservierung', $params, true);
		echo $postData;

		$result = $this->_call('POST', null, $postData);
		echo print_r($result, true);
		return $result;

	}
}
