<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * manage Pruefungsaktivitäten for Students
 */
class Pruefungsaktivitaeten_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'pruefungsaktivitaeten.xml';
	}

	/**
	 * Get all Pruefungsaktivitäten of a person with a Matrikelnr from DVUH.
	 * @param $be
	 * @param string $semester
	 * @param null $matrikelnummer
	 * @return object success or error
	 */
	public function get($be, $semester, $matrikelnummer = null)
	{
		if (isEmptyString($semester))
			$result = error('Matrikelnummer nicht gesetzt');
		else
		{
			$callParametersArray = array(
				'be' => $be,
				'semester' => $semester,
				'uuid' => getUUID()
			);

			if (!is_null($matrikelnummer))
				$callParametersArray['matrikelnummer'] = $matrikelnummer;

			$result = $this->_call(ClientLib::HTTP_GET_METHOD, $callParametersArray);
		}

		return $result;
	}

	/**
	 * Saves Pruefungsaktivitäten data in DVUH.
	 * @param string $be
	 * @param array $pruefungsaktivitaetenData
	 * @param string $dvuh_studiensemester
	 * @return object success or error
	 */
	public function post($be, $pruefungsaktivitaetenData, $dvuh_studiensemester)
	{
		$postData = $this->retrievePostData($be, $pruefungsaktivitaetenData, $dvuh_studiensemester);

		if (isError($postData))
			return $postData;

		if (hasData($postData))
			$result = $this->_call(ClientLib::HTTP_POST_METHOD, null, getData($postData));
		else
			$result = $postData; // return empty array

		return $result;
	}

	/**
	 * Retrieves xml Pruefungsaktivitäten data for request to send to DVUH, including ECTS sums.
	 * @param string $be
	 * @param array $pruefungsaktivitaetenData
	 * @param string $dvuh_studiensemester
	 * @return object success or error
	 */
	public function retrievePostData($be, $pruefungsaktivitaetenData, $dvuh_studiensemester)
	{
		if (isEmptyArray($pruefungsaktivitaetenData))
			return success(array());

		// get ects sums for Noten of the person
		$studiumpruefungen = array();

		foreach ($pruefungsaktivitaetenData as $prestudent_id => $pruefungsaktivitaeten)
		{

			// only send Pruefungsaktivitaeten if there are ects
			if (/*$pruefungsaktivitaeten->ects_angerechnet == 0 && */$pruefungsaktivitaeten->ects_erworben == 0)
				continue;

			// format ects
			$ectsSums = new stdClass();
			//$ectsSums->ects_angerechnet = number_format($pruefungsaktivitaeten->ects_angerechnet, 1);
			$ectsSums->ects_erworben = number_format($pruefungsaktivitaeten->ects_erworben, 1);

			$studiumpruefungen[$prestudent_id]['matrikelnummer'] = $pruefungsaktivitaeten->matr_nr;
			$studiumpruefungen[$prestudent_id]['studiensemester'] = $dvuh_studiensemester;
			$studiumpruefungen[$prestudent_id]['studiengang'] = $pruefungsaktivitaeten->dvuh_stgkz;
			$studiumpruefungen[$prestudent_id]['ects'] = $ectsSums;
		}

		if (isEmptyArray($studiumpruefungen))
			return success(array()); // empty array means no pruefungsaktivitaeten were found

		$params = array(
			'uuid' => getUUID(),
			'be' => $be,
			'studiumpruefungen' => $studiumpruefungen
		);

		$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/pruefungsaktivitaeten', $params, true);

		return success($postData);
	}
}
