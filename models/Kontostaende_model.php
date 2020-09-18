<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read Account Data
 */
class Kontostaende_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/rws/0.5/kontostaende.xml';
	}

	/**
	 * Performs the Webservie Call
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $semester Studysemester in format 2019W (optional)
	 * @param $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param $seit Date since income Changes
	 */
	public function get($be, $semester, $matrikelnummer = null, $seit = null)
	{
		$callParametersArray = array(
			'be' => $be,
			'semester' => $semester,
			'uuid' => getUUID()
		);

		if (!is_null($matrikelnummer))
			$callParametersArray['matrikelnummer'] = $matrikelnummer;
		if (!is_null($seit))
			$callParametersArray['seit'] = $seit;

		$result = $this->_call('GET', $callParametersArray);
		echo print_r($result,true);
		// TODO Parse Result, Handle Errors
	}
}
