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

		$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
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
	 * @param $person_id
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
	public function post($be, $person_id, $semester,
						 $matrikelnummer = null, $oehbeitrag = null, $sonderbeitrag = null, $studiengebuehr = null, $valutadatum = null, $valutadatumnachfrist = null,
						 $studiengebuehrnachfrist = null)
	{
		$postData = $this->retrievePostData($be, $person_id, $semester, $matrikelnummer, $oehbeitrag, $sonderbeitrag, $studiengebuehr, $valutadatum,
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
	 * @param int $person_id
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
	public function retrievePostData($be, $person_id, $semester, $matrikelnummer = null,
									  $oehbeitrag = null, $sonderbeitrag = null, $studiengebuehr = null, $valutadatum = null, $valutadatumnachfrist = null,
									  $studiengebuehrnachfrist = null)
	{
		$result = null;

		if (isEmptyString($person_id))
			$result = error('personID nicht gesetzt');
		elseif (isEmptyString($semester))
			$result = error('Semester nicht gesetzt');
		else
		{
			$stammdatenDataResult = $this->dvuhsynclib->getStammdatenData($person_id, $semester);

			if (isError($stammdatenDataResult))
				$result = $stammdatenDataResult;
			elseif (hasData($stammdatenDataResult))
			{
				$stammdatenData = getData($stammdatenDataResult);

				$matrikelnummer = isset($matrikelnummer) ? $matrikelnummer : $stammdatenData['matrikelnummer'];

				if (isEmptyString($matrikelnummer))
					$result = createError('Matrikelnummer nicht gesetzt', 'matrNrFehlt');
				elseif (!$this->dvuhsynclib->checkMatrikelnummer($matrikelnummer))
					$result = createError("Matrikelnummer ungültig", 'matrikelnrUngueltig', array($matrikelnummer));
				else
				{
					$params = array(
						"uuid" => getUUID(),
						"studierendenkey" => array(
							"matrikelnummer" => $matrikelnummer,
							"be" => $be,
							"semester" => $semester
						),
						"studentinfo" => $stammdatenData['studentinfo']
					);

					$oehbeitrag = isset($oehbeitrag) ? $oehbeitrag : '0';
					$sonderbeitrag = isset($sonderbeitrag) ? $sonderbeitrag : '0';
					$studiengebuehr = isset($studiengebuehr) ? $studiengebuehr : '0';
					$studiengebuehrnachfrist = isset($studiengebuehrnachfrist) ? $studiengebuehrnachfrist : '0';

					// betragstatus 'O' if no oehbeitrag, otherwise Z error from DVUH
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

					$result = success($postData);
				}
			}
			else
				$result = error("Keine Stammdaten gefunden");
		}

		return $result;
	}
}
