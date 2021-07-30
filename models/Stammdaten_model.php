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
		$this->_url = '/0.5/stammdaten.xml';

		$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
	}

	/**
	 * Performs the Webservie Call
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param $semester Studysemester in format 2019W (optional)
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
			$stammdatenDataResult = $this->dvuhsynclib->getStammdatenData($person_id);

			if (isError($stammdatenDataResult))
				$result = $stammdatenDataResult;
			elseif (hasData($stammdatenDataResult))
			{
				$stammdatenData = getData($stammdatenDataResult);

				$matrikelnummer = isset($matrikelnummer) ? $matrikelnummer : $stammdatenData['matrikelnummer'];

				if (isEmptyString($matrikelnummer))
					$result = error('Matrikelnummer nicht gesetzt');
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

					// betragstatus 'O' if no oehbeitrag, otherwese Z error
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
