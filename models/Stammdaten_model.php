<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read and save Student Data
 */
class Stammdaten_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/rws/0.5/stammdaten.xml';
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

	public function post($be, $person_id, $semester, $oehbeitrag, $studiengebuehr, $valutadatum, $valutadatumnachfrist, $preview = false)
	{
		$result = null;

		if (isEmptyString($person_id))
			$result = error('personID nicht gesetzt');
		elseif (isEmptyString($semester))
			$result = error('Semester nicht gesetzt');
/*		elseif (isEmptyString($oehbeitrag))
			$result = error('ÖH-Beitrag nicht gesetzt');
		elseif (isEmptyString($studiengebuehr))
			$result = error('Studiengebührt nicht gesetzt');
		elseif (isEmptyString($valutadatum))
			$result = error('Valudadatum nicht gesetzt');
		elseif (isEmptyString($valutadatumnachfrist))
			$result = error('Valudadatumnachfrist nicht gesetzt');*/
		else
		{
			$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');

			$stammdatenDataResult = $this->dvuhsynclib->getStammdatenData($person_id, $semester);

			if (isError($stammdatenDataResult))
				$result = $stammdatenDataResult;
			elseif (hasData($stammdatenDataResult))
			{
				$stammdatenData = getData($stammdatenDataResult);

				$params = array(
					"uuid" => getUUID(),
					"studierendenkey" => array(
						"matrikelnummer" => $stammdatenData['matrikelnummer'],
						"be" => $be,
						"semester" => $semester
					),
					"studentinfo" => $stammdatenData['studentinfo'],
				);

/*				if (!isEmptyString($oehbeitrag) && !isEmptyString($studiengebuehr))
				{*/

				$oehbeitrag = isset($oehbeitrag) ? $oehbeitrag : '0';
				$studiengebuehr = isset($studiengebuehr) ? $studiengebuehr : '0';

				// valutadatum?? Buchungsdatum + Mahnspanne
				$valutadatum = isset($valutadatum) ? $valutadatum : date_format(date_create(), 'Y-m-d');
				$valutadatumnachfrist = isset($valutadatumnachfrist) ? $valutadatumnachfrist : date_format(date_create(), 'Y-m-d');

				$params["vorschreibung"] = array(
					'oehbeitrag' => $oehbeitrag, // IN CENT!!
					'sonderbeitrag' => '0',
					'studienbeitrag' => '0', // Bei FH immer 0, CENT !!
					'studienbeitragnachfrist' => '0', // Bei FH immer 0, CENT!!
					'studiengebuehr' => $studiengebuehr, // FH Studiengebuehr in CENT!!!
					'studiengebuehrnachfrist' => $studiengebuehr, //  in CENT!!!
					'valutadatum' => $valutadatum,
					'valutadatumnachfrist' => $valutadatumnachfrist
				);
				//}

				$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/stammdaten', $params, true);

				if ($preview)
					$result = success($postData);
				else
					$result = $this->_call('POST', null, $postData);

			}
		}

		return $result;
	}
}
