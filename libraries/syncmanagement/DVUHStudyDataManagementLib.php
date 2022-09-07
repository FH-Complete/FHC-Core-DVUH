<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncmanagement/DVUHManagementLib.php';

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying data in DVUH, and updating data in FHC accordingly.
 */
class DVUHStudyDataManagementLib extends DVUHManagementLib
{
	const STORNO_MELDESTATUS = 'O'; // Meldestatus code for Storno

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHStudyDataLib');

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Studium_model', 'StudiumModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHStudiumdaten_model', 'DVUHStudiumdatenModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Sends study data for prestudents to DVUH, activates Matrikelnummer in FHC.
	 * @param string $studiensemester executed for a certain semester
	 * @param int $person_id
	 * @param int $prestudent_id optionally, send only data for one prestudent. person_id or prestudent_id must be given!
	 * @param false $preview if true, only data to post and infos are returned
	 * @return object error or success with infos
	 */
	public function sendStudyData($studiensemester, $person_id = null, $prestudent_id = null, $preview = false)
	{
		if (!isset($person_id))
		{
			if (!isset($prestudent_id))
				return error("Person Id oder Prestudent Id muss angegeben werden");

			$this->_ci->PrestudentModel->addSelect('person_id');
			$personIdRes = $this->_ci->PrestudentModel->load($prestudent_id);

			if (hasData($personIdRes))
			{
				$person_id = getData($personIdRes)[0]->person_id;
			}
			else
				return error('Keine Person für Prestudent gefunden');
		}

		$result = null;
		$fhc_studiensemester = $this->_ci->dvuhconversionlib->convertSemesterToFHC($studiensemester);
		$dvuh_studiensemester = $this->_ci->dvuhconversionlib->convertSemesterToDVUH($studiensemester);

		$studiumDataResult = $this->_ci->dvuhstudydatalib->getStudyData($person_id, $fhc_studiensemester, $prestudent_id);

		if (isError($studiumDataResult))
			return $studiumDataResult;

		if (!hasData($studiumDataResult))
			return error('Keine Studiumdaten gefunden');

		$studiumData = getData($studiumDataResult);

		if ($preview)
		{
			$postData = $this->_ci->StudiumModel->retrievePostData($this->_be, $studiumData, $dvuh_studiensemester);

			if (isError($postData))
				return $postData;

			return $this->getResponseArr(getData($postData));
		}

		// put if only for one prestudent, with post all data would be updated.
		if (isset($prestudent_id))
			$studiumResult = $this->_ci->StudiumModel->put($this->_be, $studiumData, $dvuh_studiensemester);
		else
			$studiumResult = $this->_ci->StudiumModel->post($this->_be, $studiumData, $dvuh_studiensemester);

		// get and reset warnings produced by dvuhstudydatalib
		$warnings = $this->_ci->dvuhstudydatalib->readWarnings();

		if (isError($studiumResult))
			$result = $studiumResult;
		elseif (hasData($studiumResult))
		{
			$xmlstr = getData($studiumResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($parsedObj))
				$result = $parsedObj;
			else
			{
				$result = $this->getResponseArr(
					$xmlstr,
					array('Studiumdaten erfolgreich in DVUH gespeichert'),
					$warnings,
					true
				);

				// activate Matrikelnr
				$matrNrActivationResult = $this->_ci->PersonModel->update(
					array(
						'person_id' => $person_id,
						'matr_aktiv' => false
					),
					array(
						'matr_aktiv' => true
					)
				);

				if (isError($matrNrActivationResult))
					$result = error("Studiumdaten erfolgreich gespeichert, Fehler beim Scharfschalten der Matrikelnummer in FHC");

				foreach ($studiumData->prestudent_ids as $syncedPrestudentId)
				{
					// save info about saved studiumdata in sync table
					$studiumSaveResult = $this->_ci->DVUHStudiumdatenModel->insert(
						array(
							'prestudent_id' => $syncedPrestudentId,
							'studiensemester_kurzbz' => $fhc_studiensemester,
							'meldedatum' => date('Y-m-d')
						)
					);

					if (isError($studiumSaveResult))
						$result = error("Studiumdaten erfolgreich gespeichert, Fehler beim Speichern in der Synctabelle in FHC");
				}
			}
		}
		else
			$result = error("Fehler beim Senden der Studiumdaten");

		return $result;
	}

	/**
	 * Cancels study data in DVUH.
	 * @param int $prestudent_id
	 * @param string $semester
	 * @param bool $preview
	 * @return object error or success
	 */
	public function cancelStudyData($prestudent_id, $semester, $preview = false)
	{
		$result = null;
		$infos = array();

		if (!isset($prestudent_id))
		{
			return error("Prestudent Id muss angegeben werden"); // TODO phrase
		}

		if (!isset($semester))
		{
			return error("Semester muss angegeben werden"); // TODO phrase
		}

		// get matrikel number and studiengang from prestudent
		$this->_ci->PersonModel->addSelect("matr_nr, matrikelnr AS personenkennzeichen, stg.studiengang_kz, stg.melde_studiengang_kz, erhalter_kz");
		$this->_ci->PersonModel->addJoin("public.tbl_prestudent", "person_id");
		$this->_ci->PersonModel->addJoin("public.tbl_studiengang stg", "studiengang_kz");
		$this->_ci->PersonModel->addJoin("public.tbl_student stud", "prestudent_id");
		$studentRes = $this->_ci->PersonModel->loadWhere(
			array(
				'prestudent_id' => $prestudent_id
			)
		);

		if (isError($studentRes))
			return $studentRes;

		if (!hasData($studentRes))
		{
			$infos[] = 'keine Studien in FHC gefunden';// TODO phrases
			return $this->getResponseArr(null, $infos);
		}

		$studentData = getData($studentRes)[0];
		$matrikelnummer = $studentData->matr_nr;

		// Ausserordentlicher Studierender (4.Stelle in Personenkennzeichen = 9)
		$isAusserordentlich = $this->_ci->dvuhcheckinglib->checkIfAusserordentlich($studentData->personenkennzeichen);

		// studiengang kz
		$meldeStudiengangRes = $this->_ci->dvuhconversionlib->getMeldeStudiengangKz(
			$studentData->studiengang_kz,
			$studentData->erhalter_kz,
			$isAusserordentlich
		);

		if (isError($meldeStudiengangRes))
			return $meldeStudiengangRes;

		$fhc_stgkz_in_dvuh_format = null;
		if (hasData($meldeStudiengangRes))
			$fhc_stgkz_in_dvuh_format = getData($meldeStudiengangRes);

		$dvuh_studiensemester = $this->_ci->dvuhconversionlib->convertSemesterToDVUH($semester);

		// get xml of study data in DVUH
		$studyData = $this->_ci->StudiumModel->get($this->_be, $matrikelnummer, $dvuh_studiensemester);

		if (hasData($studyData))
		{
			$xmlstr = getData($studyData);

			// parse the received data, extract studiengang and lehrgang xml
			$studienRes = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('studiengang', 'lehrgang'));

			if (isError($studienRes))
				return $studienRes;

			if (hasData($studienRes))
			{
				$studiengaenge = array();
				$lehrgaenge = array();

				$studienData = getData($studienRes);

				$studien = array_merge($studienData->studiengang, $studienData->lehrgang);

				// student data params to send to DVUH
				$params = array(
					"uuid" => getUUID(),
					"studierendenkey" => array(
						"matrikelnummer" => $matrikelnummer,
						"be" => $this->_be,
						"semester" => $dvuh_studiensemester
					)
				);

				$studiengangIdName = 'stgkz';
				$lehrgangIdName = 'lehrgangsnr';

				foreach ($studien as $studium)
				{
					$isLehrgang = false;
					$studiumIdName = null;
					if (isset($studium->{$studiengangIdName}))
					{
						$studiumIdName = $studiengangIdName;
					}
					elseif (isset($studium->{$lehrgangIdName}))
					{
						$studiumIdName = $lehrgangIdName;
						$isLehrgang = true;
					}

					if (!isset($studiumIdName))
						return error("Studium Id fehlt"); //TODO phrases

					// compare studiengang kz received from DVUH vs studiengang kz got from FHC
					$dvuh_stgkz = $studium->{$studiumIdName};

					// only send Studiengang of requested prestudent id
 					if ($dvuh_stgkz != $fhc_stgkz_in_dvuh_format)
						continue;

					// add storno data to data received from dvuh
					$studium->meldestatus = self::STORNO_MELDESTATUS;

					// convert object data to assoc array
					$stdArr = json_decode(json_encode($studium), true);

					if ($isLehrgang)
					{
						$lehrgaenge[] = $stdArr;
					}
					else
					{
						$studiengaenge[] = $stdArr;
					}
				}

				$params['studiengaenge'] = $studiengaenge;
				$params['lehrgaenge'] = $lehrgaenge;

				// abort if no studien found
				if (isEmptyArray($studiengaenge) && isEmptyArray($lehrgaenge))
				{
					$infos[] = 'keine Studien in DVUH gefunden';// TODO phrases
					return $this->getResponseArr(null, $infos);
				}

				// show preview
				if ($preview)
				{
					$postData = $this->_ci->StudiumModel->retrievePostDataString($params);
					return $this->getResponseArr($postData);
				}

				// send study data with modified storno data
				$studiumPostRes = $this->_ci->StudiumModel->putManually($params);

				if (isError($studiumPostRes))
					return $studiumPostRes;

				// insert into sync table to record that data was cancelled
				$studiensemester_kurzbz = $this->_ci->dvuhconversionlib->convertSemesterToFHC($semester);

				$studiumSaveResult = $this->_ci->DVUHStudiumdatenModel->insert(
					array(
						'prestudent_id' => $prestudent_id,
						'studiensemester_kurzbz' => $studiensemester_kurzbz,
						'meldedatum' => date('Y-m-d'),
						'storniert' => true
					)
				);

				if (isError($studiumSaveResult)) // TODO phrases
					return error("Studiumdaten erfolgreich storniert, Fehler beim Speichern in der Synctabelle in FHC");

				$infos[] = "Studiumdaten erfolgreich storniert"; // TODO phrases

				$result = getData($studiumPostRes);
			}
			else
			{
				$infos[] = "Keine Studiumdaten zum Stornieren gefunden"; // TODO phrase
			}
		}
		else
			$infos[] = "Keine Studiumdaten zum Stornieren gefunden"; // TODO phrase

		return $this->getResponseArr(
			$result,
			$infos,
			null,
			true
		);
	}
}
