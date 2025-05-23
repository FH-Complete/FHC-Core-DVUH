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
		$this->_url = 'matrikelreservierung.xml';
	}

	/**
	 * Performs the Webservie Call to get a list of already reserved Numbers.
	 * @param string $be Code of the Bildungseinrichtung
	 * @param string $sj Studienjahr
	 * @return object success or error
	 */
	public function get($be, $sj)
	{
		if (isEmptyString($sj))
			$result = error('Studienjahr nicht gesetzt');
		else
		{
			$callParametersArray = array(
				'be' => $be,
				'sj' => $sj,
				'uuid' => getUUID()
			);

			$result = $this->_call(ClientLib::HTTP_GET_METHOD, $callParametersArray);
		}

		return $result;
	}

	/**
	 * Performs a Webservice Call to Reserve a list of matrikelnumbers.
	 * @param $be Code of the Bildungseinrichtung
	 * @param $sj Studienjahr
	 * @param $anzahl number of matrikelnumbers to reserve
	 * @return object success or error
	 */
	public function post($be, $sj, $anzahl)
	{
		if (isEmptyString($sj))
			$result = error('Studienjahr nicht gesetzt');
		elseif(isEmptyString($anzahl))
			$result = error('Anzahl nicht gesetzt');
		else
		{
			$params = array(
				"uuid" => getUUID(),
				"anzahl" => $anzahl,
				"sj" => $sj,
				"be" => $be
			);
			$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/matrikelreservierung', $params, true);

			$result = $this->_call(ClientLib::HTTP_POST_METHOD, null, $postData);
		}

		return $result;
	}
}
