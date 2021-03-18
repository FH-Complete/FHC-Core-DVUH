<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read Error Messages
 */
class Fehler_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/0.5/fehler.xml';
	}

	/**
	 * Performs the Webservie Call to read the Errormessages
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $semester Studysemester in format 2019W (optional)
	 * @param $matrikelnummer Matrikelnummer of the Person you are Searching for
	 */
	public function get($be, $semester, $matrikelnummer = null)
	{
		$callParametersArray = array(
			'be' => $be,
			'semester' => $semester,
			'uuid' => getUUID()
		);

		if (!is_null($matrikelnummer))
			$callParametersArray['matrikelnummer'] = $matrikelnummer;

		$result = $this->_call('GET', $callParametersArray);
		echo print_r($result,true);
		// TODO Parse Result, Handle Errors
	}
}
