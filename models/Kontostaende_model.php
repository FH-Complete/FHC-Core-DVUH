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
		$this->_url = 'kontostaende.xml';

		// load helpers
		$this->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');
	}

	/**
	 * Performs the Webservie Call
	 * @param string $be Code of the Bildungseinrichtung
	 * @param string $semester Studysemester in format 2019W (optional)
	 * @param string $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param string $seit Date since income Changes
	 * @return object success or error
	 */
	public function get($be, $semester, $matrikelnummer, $seit = null)
	{
		if (isEmptyString($matrikelnummer))
			return createIssueError('Matrikelnummer nicht gesetzt', 'matrNrFehlt');

		if(isEmptyString($semester))
			return  error('Semester nicht gesetzt');

		$callParametersArray = array(
			'be' => $be,
			'semester' => $semester,
			'matrikelnummer' => $matrikelnummer,
			'uuid' => getUUID()
		);

		if (!is_null($seit))
			$callParametersArray['seit'] = $seit;

		$result = $this->_call(ClientLib::HTTP_GET_METHOD, $callParametersArray);

		return $result;
	}
}
