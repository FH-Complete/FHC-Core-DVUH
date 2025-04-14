<?php

require_once APPPATH.'libraries/extensions/FHC-Core-DVUH/uhstat/UHSTATErrorProducerLib.php';

/**
 * Contains logic for interaction of FHC with UHSTAT interface.
 * This includes initializing webservice calls for modifiying UHSTAT data.
 */
class UHSTATManagementLib extends UHSTATErrorProducerLib
{
	protected $_ci;

	// UHSTAT codes for person id type
	private $_pers_id_types = array(
		//'svnr' => 1,
		'ersatzkennzeichen' => 2,
		'vbpkAs' => 6
	);

	const PERS_ID_NAME = 'persid';
	const PERS_ID_TYPE_NAME = 'persidTyp';
	const PERS_ID_FREMDSCHLÜSSEL_NAME = 'persidFremdVerschl';
	const STUDIERENDE_JAHR_NAME = 'studienendeJahr';
	const STUDIERENDE_MONAT_NAME = 'studienendeMonat';

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-DVUH/UHSTATSync'); // load sync config

		// load api models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/UHSTAT1Model', 'UHSTAT1Model');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/UHSTAT2Model', 'UHSTAT2Model');

		// load data models
		$this->_ci->load->model('codex/Uhstat1daten_model', 'Uhstat1datenModel');

		// load sync models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHUHSTAT1_model', 'DVUHUHSTAT1Model');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHUHSTAT2_model', 'DVUHUHSTAT2Model');

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/uhstat/UHSTATDataLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/uhstat/UHSTATConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/uhstat/UHSTATSchedulerLib');

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
			$this->addError($personRes);
			return;
		}

		if (!hasData($personRes))
		{
			$this->addError(error("Keine FHC Daten gefunden"));
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
				$this->addError(error(getError($uhstat1Result)."; Person Id ".$person->person_id));
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
					error("UHSTAT1 Daten für Person Id ".$person->person_id." erfolgreich gesendet, Fehler beim Speichern der Meldung in FHC")
				);
			}
		}
	}

	/**
	 * Sends UHSTAT2 data for Mobilitäten of certain prestudents.
	 * @param $prestudent_id_arr
	 */
	public function sendUHSTAT2($prestudent_id_arr)
	{
		// get student data for UHSTAT2
		$mobilitaetRes = $this->_ci->uhstatdatalib->getMobilitaetData(
			$prestudent_id_arr, $this->_ci->config->item('fhc_uhstat_status_kurzbz')[UHSTATSchedulerLib::JOB_TYPE_UHSTAT2]
		);

		if (isError($mobilitaetRes))
		{
			$this->addError($mobilitaetRes);
			return;
		}

		if (!hasData($mobilitaetRes))
		{
			$this->addError(error("Keine FHC Daten gefunden"));
			return;
		}

		$mobilitaetData = getData($mobilitaetRes);

		$mobilitaetData = $this->_groupArrayByProperty($mobilitaetData, 'prestudent_id');

		foreach ($mobilitaetData as $prestudent_id => $prestudMobilitaeten)
		{
			// get UHSTAT2 specific data from person data
			$uhstat2Data = $this->_getUHSTAT2Data($prestudMobilitaeten);

			// skip if error occured when getting UHSTAT2 code data
			if (!isset($uhstat2Data)) continue;

			// if everything ok, send UHSTAT2 data
			$uhstat2Result = $this->_ci->UHSTAT2Model->saveEntry(
				$uhstat2Data[self::PERS_ID_TYPE_NAME],
				isset($uhstat2Data[self::PERS_ID_TYPE_NAME]) && $uhstat2Data[self::PERS_ID_TYPE_NAME] == $this->_pers_id_types['vbpkAs']
					? base64_urlencode($uhstat2Data[self::PERS_ID_NAME]) // url encode if bpk in url
					: $uhstat2Data[self::PERS_ID_NAME],
				$uhstat2Data[self::STUDIERENDE_JAHR_NAME],
				$uhstat2Data[self::STUDIERENDE_MONAT_NAME],
				$uhstat2Data
			);

			// add error if error when sending UHSTAT2 data
			if (isError($uhstat2Result))
			{
				$this->addError(error(getError($uhstat2Result)."; Prestudent Id ".$prestudent_id));
				continue;
			}

			// if it went through, log info
			$this->addInfo("UHSTAT2 Daten für Prestudent mit Id ".$prestudent_id." erfolgreich gesendet");

			// write UHSTAT2 Meldung in FHC db
			$uhstatSyncSaveRes = $this->_ci->DVUHUHSTAT2Model->insert(
				array(
					'prestudent_id' => $prestudent_id,
					'gemeldetamum' => 'NOW()'
				)
			);

			// write error if adding of sync entry failed
			if (isError($uhstatSyncSaveRes))
			{
				$this->addError(
					error("UHSTAT2 Daten für Prestudent Id ".$prestudent_id." erfolgreich gesendet, Fehler beim Speichern der Meldung in FHC")
				);
			}
		}
	}

	// --------------------------------------------------------------------------------------------
	// Private methods


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

		$uhstatIdData = $this->_getUHSTATIdentificationData($personData);

		if (isset($uhstatIdData))
		{
			$uhstat1Data = $uhstatIdData;
		}
		else
		{
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
				error("Geburtsnation fehlt; Person ID ".$personData->person_id),
				createExtendedIssueObj(
					'geburtsnationFehlt',
					$personData->person_id
				)
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

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	private function _getUHSTAT2Data($mobilitaeten)
	{
		$errorOccured = false;
		$uhstat2Data = array();

		$uhstatIdData = isset($mobilitaeten[0]) ? $this->_getUHSTATIdentificationData($mobilitaeten[0]) : null;

		if (isset($uhstatIdData))
		{
			// initialize with id data
			$uhstat2Data = $uhstatIdData;

			$uhstat2Data = array_merge($uhstat2Data, $this->_ci->uhstatconversionlib->convertToUHSTAT2($mobilitaeten));
		}
		else
		{
			$errorOccured = true;
		}

		// return null if error occured
		if ($errorOccured) return null;

		// data successfully retrieved
		return $uhstat2Data;
	}

	/**
	 * Gets UHSTAT person identification data.
	 * @param object $personData data of student from FHC database
	 */
	private function _getUHSTATIdentificationData($personData)
	{
		$idData = array();

		// get persIdType and persId (bpkAs, or ersatzkennzeichen)
		if (isset($personData->vbpkAs) && !isEmptyString($personData->vbpkAs)
			&& isset($personData->vbpkBf) && !isEmptyString($personData->vbpkBf))
		{
			$idData[self::PERS_ID_NAME] = $personData->vbpkAs;
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
			// add issue if data missing
			$this->addWarning(
				error("Personkennung fehlt (vBpk AS, vBpk BF oder Ersatzkennzeichen fehlt); Person ID ".$personData->person_id),
				createExtendedIssueObj(
					'uhstatPersonkennungFehlt',
					$personData->person_id
				)
			);
			// error occured, do not report student
			return null;
		}

		// data successfully retrieved
		return $idData;
	}

	/**
	 * Splits an array by property, with the property as array index.
	 * @param arr
	 * @param propName
	 */
	private function _groupArrayByProperty($arr, $propName)
	{
		$resArr = array();

		foreach ($arr as $element)
		{
			if (isset($element->{$propName}))
				$resArr[$element->{$propName}][] = $element;
		}

		return $resArr;
	}
}
