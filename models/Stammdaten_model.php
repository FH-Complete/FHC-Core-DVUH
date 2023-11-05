<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read and save Student Master Data
 */
class Stammdaten_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'stammdaten.xml';

		// load libraries
		$this->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHCheckingLib');

		// load helpers
		$this->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');
	}

	/**
	 * Performs the Webservie Call.
	 * @param string $be Code of the Bildungseinrichtung
	 * @param string $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param string $semester Studysemester in format 2019W (optional)
	 * @return object success or error
	 */
	public function get($be, $matrikelnummer, $semester = null)
	{
		if (isEmptyString($matrikelnummer))
			$result = error('Matrikelnummer nicht gesetzt');
		else
		{
			$callParametersArray = array(
				'be' => $be,
				'matrikelnummer' => $matrikelnummer,
				'uuid' => getUUID()
			);

			if (!is_null($semester))
				$callParametersArray['semester'] = $semester;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}

	/**
	 * Saving Stammdaten in DVUH, using person_id to retrieve data available in FHC and add additional payment data.
	 * @param string $be
	 * @param array $studentinfo contains student data
	 * @param string $semester
	 * @param string $matrikelnummer
	 * @param float $oehbeitrag OEH Beitrag payment amount without insurance
	 * @param float $sonderbeitrag OEH Beitrag insurance amount
	 * @param string $studiengebuehr OEH Beitrag payment amount
	 * @param string $valutadatum
	 * @param string $valutadatumnachfrist
	 * @param string $studiengebuehrnachfrist
	 * @return object  success or error
	 */
	public function post($be, $studentinfo, $semester,
							$matrikelnummer = null, $oehbeitrag = null, $sonderbeitrag = null, $studiengebuehr = null, $valutadatum = null, $valutadatumnachfrist = null,
							$studiengebuehrnachfrist = null)
	{
		$postData = $this->retrievePostData($be, $studentinfo, $semester, $matrikelnummer, $oehbeitrag, $sonderbeitrag, $studiengebuehr, $valutadatum,
			$valutadatumnachfrist, $studiengebuehrnachfrist);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->_call('POST', null, getData($postData));

		return $result;
	}

	/**
	 * Retrieve person data needed for sending Stammdaten, as well as needed payment data to send charge with the Stammdaten.
	 * @param string $be
	 * @param array $studentinfo
	 * @param string $semester
	 * @param string $matrikelnummer
	 * @param string $oehbeitrag
	 * @param string $sonderbeitrag
	 * @param string $studiengebuehr
	 * @param string $valutadatum
	 * @param string $valutadatumnachfrist
	 * @param string $studiengebuehrnachfrist
	 * @return object success with person data or error
	 */
	public function retrievePostData(
		$be,
		$studentinfo,
		$semester,
		$matrikelnummer = null,
		$oehbeitrag = null,
		$sonderbeitrag = null,
		$studiengebuehr = null,
		$valutadatum = null,
		$valutadatumnachfrist = null,
		$studiengebuehrnachfrist = null
	) {
		if (isEmptyArray($studentinfo))
			return error('Studentinfo nicht gesetzt');

		if (isEmptyString($semester))
			return error('Semester nicht gesetzt');

		if (!isset($matrikelnummer) && isset($studentinfo['matr_nr']))
			$matrikelnummer = $studentinfo['matr_nr'];

		if (isEmptyString($matrikelnummer))
			return createIssueError('Matrikelnummer nicht gesetzt', 'matrNrFehlt');

		if (!$this->dvuhcheckinglib->checkMatrikelnummer($matrikelnummer))
			return createIssueError("Matrikelnummer ungültig", 'matrikelnrUngueltig', array($matrikelnummer));

		$params = array(
			"uuid" => getUUID(),
			"studierendenkey" => array(
				"matrikelnummer" => $matrikelnummer,
				"be" => $be,
				"semester" => $semester
			),
			"studentinfo" => $studentinfo
		);

		$oehbeitrag = isset($oehbeitrag) ? $oehbeitrag : '0';
		$sonderbeitrag = isset($sonderbeitrag) ? $sonderbeitrag : '0';
		$studiengebuehr = isset($studiengebuehr) ? $studiengebuehr : '0';
		$studiengebuehrnachfrist = isset($studiengebuehrnachfrist) ? $studiengebuehrnachfrist : '0';

		// beitragstatus 'O' if no oehbeitrag, otherwise Z error from DVUH
		if ($oehbeitrag == '0' && $sonderbeitrag == '0')
			$params["studentinfo"]["beitragstatus"] = 'O';

		// valutadatum?? Buchungsdatum + Mahnspanne
		$valutadatum = isset($valutadatum) ? $valutadatum : date_format(date_create(), 'Y-m-d');
		$valutadatumnachfrist = isset($valutadatumnachfrist) ? $valutadatumnachfrist : date_format(date_create(), 'Y-m-d');

		$params["vorschreibung"] = array(
			'oehbeitrag' => $oehbeitrag, // IN CENT!!
			'sonderbeitrag' => $sonderbeitrag, // IN CENT!!,
			'studienbeitrag' => '0', // Bei FH immer 0, CENT !!
			'studienbeitragnachfrist' => '0', // Bei FH immer 0, CENT!!
			'studiengebuehr' => $studiengebuehr, // FH Studiengebuehr in CENT!!!
			'studiengebuehrnachfrist' => $studiengebuehrnachfrist, //  in CENT!!!
			'valutadatum' => $valutadatum,
			'valutadatumnachfrist' => $valutadatumnachfrist
		);

		$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/stammdaten', $params, true);

		return success($postData);
	}
}
