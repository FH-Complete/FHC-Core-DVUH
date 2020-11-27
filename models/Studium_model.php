<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read and Update Student Data
 */
class Studium_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/rws/0.5/studium.xml';

		$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
	}

	/**
	 * Performs the Webservie Call
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param $semester Studysemester in format 2019W (optional)
	 * @param $studienkennung Die Studienkennung muss mindestens sechs Zeichen lang sein (optional)
	 */
	public function get($be, $matrikelnummer, $semester = null, $studienkennung = null)
	{
		$callParametersArray = array(
			'be' => $be,
			'matrikelnummer' => $matrikelnummer,
			'uuid' => getUUID()
		);

		if (isEmptyString($matrikelnummer))
			$result = error('Matrikelnummer nicht gesetzt');
		else
		{
			if (!is_null($semester))
				$callParametersArray['semester'] = $semester;
			if (!is_null($studienkennung))
				$callParametersArray['studienkennung'] = $studienkennung;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}

	public function post($be, $person_id, $semester, $prestudent_id = null, $preview = false)
	{
		$result = null;

		$studiumDataResult = $this->dvuhsynclib->getStudyData($person_id, $semester, $prestudent_id);

		if (isError($studiumDataResult))
			$result = $studiumDataResult;
		elseif (hasData($studiumDataResult))
		{
			$studiumData = getData($studiumDataResult);

			$params = array(
				"uuid" => getUUID(),
				"studierendenkey" => array(
					"matrikelnummer" => $studiumData->matrikelnummer,
					"be" => $be,
					"semester" => $semester
				)
			);

			$params['studiengaenge'] = $studiumData->studiengaenge;
			$params['lehrgaenge'] = $studiumData->lehrgaenge;

			$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/studium', $params, true);

			if ($preview)
				$result = success($postData);
			else
				$result = $this->_call('POST', null, $postData);
		}
		else
			$result = error("Keine Studiumdaten gefunden");

		return $result;
	}
}
