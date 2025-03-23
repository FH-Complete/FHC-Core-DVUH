<?php

require_once APPPATH.'libraries/extensions/FHC-Core-DVUH/syncdata/DVUHErrorProducerLib.php';

/**
 * Contains logic for interaction of FHC with UHSTAT interface.
 * This includes initializing webservice calls for modifiying UHSTAT data.
 */
class UHSTATDataManagementLib extends DVUHErrorProducerLib
{
	private $_ci;

	// UHSTAT codes for person id type
	private $_pers_id_types = array(
		//'svnr' => 1,
		'ersatzkennzeichen' => 2,
		'vbpkAs' => 6
	);

	const PERS_ID_NAME = 'persid';
	const PERS_ID_TYPE_NAME = 'persidTyp';
	const PERS_ID_FREMDSCHLÜSSEL_NAME = 'persidFremdVerschl';

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_ci =& get_instance(); // get code igniter instance

		// load api model
		$this->_ci->load->model('extensions/FHC-Core-DVUH/UHSTAT1Model', 'UHSTAT1Model');

		// load data model
		$this->_ci->load->model('codex/Uhstat1daten_model', 'Uhstat1datenModel');

		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHUHSTAT1_model', 'DVUHUHSTAT1Model');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Sends UHSTAT1 data of students.
	 * @param array $person_id_arr
	 */
	public function sendUHSTAT1($person_id_arr)
	{
		// get person data for UHSTAT1
		$personRes = $this->_ci->Uhstat1datenModel->getUHSTAT1PersonData($person_id_arr);

		if (isError($personRes))
		{
			$this->addError(getError($personRes));
			return;
		}

		if (!hasData($personRes))
		{
			$this->addError("Keine FHC Daten gefunden");
			return;
		}

		$personData = getData($personRes);

		foreach ($personData as $person)
		{
			// get UHSTAT1 specific data from person data
			$uhstat1Data = $this->_getUHSTAT1Data($person);

			// skip if error occured when getting UHSTAT1 code data
			if (!isset($uhstat1Data)) continue;

			// if everything ok, send UHSTAT1 data
			$uhstat1Result = $this->_ci->UHSTAT1Model->saveEntry(
				$uhstat1Data[self::PERS_ID_TYPE_NAME],
				isset($uhstat1Data[self::PERS_ID_TYPE_NAME]) && $uhstat1Data[self::PERS_ID_TYPE_NAME] == $this->_pers_id_types['vbpkAs']
					? base64_urlencode($uhstat1Data[self::PERS_ID_NAME]) // url encode if bpk in url
					: $uhstat1Data[self::PERS_ID_NAME],
				$uhstat1Data
			);

			// add error if error when sending UHSTAT1 data
			if (isError($uhstat1Result))
			{
				$this->addError(getError($uhstat1Result)."; Person Id ".$person->person_id);
				continue;
			}

			// if it went through, log info
			$this->addInfo("UHSTAT1 Daten für Person mit Id ".$person->person_id." erfolgreich gesendet");

			// write UHSTAT1 Meldung in FHC db
			$uhstatSyncSaveRes = $this->_ci->DVUHUHSTAT1Model->insert(
				array(
					'uhstat1daten_id' => $person->uhstat1daten_id,
					'gemeldetamum' => 'NOW()'
				)
			);

			// write error if adding of sync entry failed
			if (isError($uhstatSyncSaveRes))
			{
				$this->addError(
					"UHSTAT1 Daten für Person Id ".$person->person_id." erfolgreich gesendet, Fehler beim Speichern der Meldung in FHC"
				);
			}
		}
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Gets UHSTAT person identification data.
	 * @param object $personData data of student from FHC database
	 */
	private function _getUHSTATIdentificationData($personData)
	{
		$errorOccured = false;
		$idData = array();

		// get persIdType and persId (bpkAs, svnr, or ersatzkennzeichen)
		//~ if (isset($personData->svnr) && !isEmptyString($personData->svnr))
		//~ {
			//~ $idData['persId'] = $personData->svnr;
			//~ $idData['persIdType'] = $this->_pers_id_types['svnr'];
			//~ $idData[self::PERS_ID_FREMDSCHLÜSSEL_NAME] = $personData->vbpkBf;
		//~ }
		if (isset($personData->vbpkAs) && !isEmptyString($personData->vbpkAs)
			&& isset($personData->vbpkBf) && !isEmptyString($personData->vbpkBf))
		{
			// TODO: is it needed to explicitely replace special chars here?
			$idData[self::PERS_ID_NAME] = base64_urlencode($personData->vbpkAs);
			$idData[self::PERS_ID_TYPE_NAME] = $this->_pers_id_types['vbpkAs'];
			$idData[self::PERS_ID_FREMDSCHLÜSSEL_NAME] = $personData->vbpkBf;
		}
		elseif (isset($personData->ersatzkennzeichen) && !isEmptyString($personData->ersatzkennzeichen))
		{
			$idData[self::PERS_ID_NAME] = $personData->ersatzkennzeichen;
			$idData[self::PERS_ID_TYPE_NAME] = $this->_pers_id_types['ersatzkennzeichen'];
		}
		else
		{
			// TODO add issues?
			// add issue if data missing
			$this->addWarning(
				"Personkennung fehlt (vBpk AS, vBpk BF oder Ersatzkennzeichen fehlt); Person ID ".$personData->person_id
				//~ createIssueObj(
					//~ 'uhstatPersonkennungFehlt',
					//~ $personData->person_id
				//~ )
			);
			// error occured, do not report student
			$errorOccured = true;
		}

		// return null if error occured
		if ($errorOccured) return null;

		// data successfully retrieved
		return $idData;
	}

	/**
	 * Gets UHSTAT1 data to be sent, in format as expected by API.
	 * @param object $personData data of student from FHC database
	 */
	private function _getUHSTAT1Data($personData)
	{
		/*
		expected data format:
		{
			"persid": "XXXXXXXXXX",
			"persidTyp": 2,
			"gebstaat": "A",
			"mutter": {
				"gebstaat": "A",
				"gebjahr": 1970,
				"bildstaat": "A",
				"bildmax": 240
			},
			"vater": {
				"gebstaat": "A",
				"gebjahr": 1970,
				"bildstaat": "A",
				"bildmax": 240
			  }
		}*/

		$errorOccured = false;
		$uhstat1Data = array();

		if (isset($personData->vbpkAs) && !isEmptyString($personData->vbpkAs)
			&& isset($personData->vbpkBf) && !isEmptyString($personData->vbpkBf))
		{
			// TODO: is it needed to explicitely replace special chars here?
			$uhstat1Data[self::PERS_ID_NAME] = $personData->vbpkAs;
			$uhstat1Data[self::PERS_ID_TYPE_NAME] = $this->_pers_id_types['vbpkAs'];
			$uhstat1Data[self::PERS_ID_FREMDSCHLÜSSEL_NAME] = $personData->vbpkBf;
		}
		elseif (isset($personData->ersatzkennzeichen) && !isEmptyString($personData->ersatzkennzeichen))
		{
			$uhstat1Data[self::PERS_ID_NAME] = $personData->ersatzkennzeichen;
			$uhstat1Data[self::PERS_ID_TYPE_NAME] = $this->_pers_id_types['ersatzkennzeichen'];
		}
		else
		{
			// TODO add issues?
			// add issue if data missing
			$this->addWarning(
				"Personkennung fehlt (vBpk AS, vBpk BF oder Ersatzkennzeichen fehlt); Person ID ".$personData->person_id
				//~ createIssueObj(
					//~ 'uhstatPersonkennungFehlt',
					//~ $personData->person_id
				//~ )
			);
			// error occured, do not report student
			$errorOccured = true;
		}

		// get Geburtsnation
		if (isset($personData->geburtsnation) && !isEmptyString($personData->geburtsnation))
		{
			$uhstat1Data['gebstaat'] = $personData->geburtsnation;
		}
		else
		{
			// add issue if data missing
			$this->addWarning(
				"Geburtsnation fehlt; Person ID ".$personData->person_id
				//~ createIssueObj(
					//~ 'uhstatGeburtsnationFehlt',
					//~ $personData->person_id
				//~ )
			);
			// error occured, do not report student
			$errorOccured = true;
		}

		// get UHSTAT1 fields
		$uhstat1Data['mutter'] = array(
			'gebstaat' => $personData->mutter_geburtsstaat,
			'gebjahr' => $personData->mutter_geburtsjahr,
			'bildstaat' => $personData->mutter_bildungsstaat,
			'bildmax' => $personData->mutter_bildungmax
		);

		$uhstat1Data['vater'] = array(
			'gebstaat' => $personData->vater_geburtsstaat,
			'gebjahr' => $personData->vater_geburtsjahr,
			'bildstaat' => $personData->vater_bildungsstaat,
			'bildmax' => $personData->vater_bildungmax
		);

		// return null if error occured
		if ($errorOccured) return null;

		// data successfully retrieved
		return $uhstat1Data;
	}
}
