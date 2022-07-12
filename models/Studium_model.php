<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read and save Student Study Data
 */
class Studium_model extends DVUHClientModel
{
	//private $_prestudentIdsToSave = array();

	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'studium.xml';
	}

	/**
	 * Performs the Webservice Call
	 * @param string $be Code of the Bildungseinrichtung
	 * @param string $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param string $semester Studysemester in format 2019W
	 * @param string $studienkennung Die Studienkennung muss mindestens sechs Zeichen lang sein (optional)
	 * @return object success or error
	 */
	public function get($be, $matrikelnummer, $semester, $studienkennung = null)
	{
		$callParametersArray = array(
			'be' => $be,
			'matrikelnummer' => $matrikelnummer,
			'semester' => $semester,
			'uuid' => getUUID()
		);

		if (isEmptyString($matrikelnummer))
			$result = error('Matrikelnummer nicht gesetzt');
		elseif (isEmptyString($semester))
			$result = error('Studiensemester nicht gesetzt');
		else
		{
			if (!is_null($studienkennung))
				$callParametersArray['studienkennung'] = $studienkennung;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}

	/**
	 * Send Studium data to DVUH. If only person_id is passed, data of all prestudents is included in the request.
	 * Prestudent_id can be additionally passed to send data for only one prestudent.
	 * The API will attempt to delete all previously sent students if only one is sent.
	 * @param string $be
	 * @param object $studiumData as object with Studiengänge, Lehrgänge  of a person
	 * @param string $semester
	 * @param int $prestudent_id
	 * @return object success or error
	 */
	public function post($be, $studiumData, $semester)
	{
		$postData = $this->retrievePostData($be, $studiumData, $semester);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->postManually(getData($postData));

		return $result;
	}

	/**
	 * Execute studium post call.
	 * @param array $params
	 * @return object success or error
	 */
	public function postManually($params)
	{
		$postData = $this->retrievePostDataString($params);

		return $this->_call('POST', null, $postData);
	}

	/**
	 * Put request, same as post, but previously safed students are not deleted if only one student is sent.
	 * @param string $be
	 * @param object $studiumData as object with Studiengänge, Lehrgänge  of a person
	 * @param string $semester
	 * @param int $prestudent_id
	 * @return object success or error
	 */
	public function put($be, $studiumData, $semester)
	{
		$postData = $this->retrievePostData($be, $studiumData, $semester);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->putManually(getData($postData));

		return $result;
	}

	/**
	 * Execute studium put call.
	 * @param array $params
	 * @return object success or error
	 */
	public function putManually($params)
	{
		$postData = $this->retrievePostDataString($params);

		return $this->_call('PUT', null, $postData);
	}

	/**
	 * Retrieves necessary xml study data from fhc db for all prestudents of a person or a single prestudent.
	 * @param string $be
	 * @param object $studiumData as object with Studiengänge, Lehrgänge  of a person
	 * @param string $semester
	 * @param int $prestudent_id
	 * @return object success with study data or error
	 */
	public function retrievePostData($be, $studiumData, $semester)
	{
		if (!isset($studiumData->matrikelnummer) || !isset($studiumData->studiengaenge) || !isset($studiumData->lehrgaenge))
			return error("Studiumdaten ungültig, müssen Matrikelnummer, Studiengänge/Lehrgänge enthalten");

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

		$postData = $this->retrievePostDataString($params);

		return success($postData);
	}

	/**
	 * Gets xml string for studium.xml call.
	 * @param array $params
	 * @return string
	 */
	public function retrievePostDataString($params)
	{
		return $this->load->view('extensions/FHC-Core-DVUH/requests/studium', $params, true);
	}
}
