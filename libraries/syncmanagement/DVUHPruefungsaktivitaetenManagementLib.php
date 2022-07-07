<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncmanagement/DVUHManagementLib.php';

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying data in DVUH, and updating data in FHC accordingly.
 */
class DVUHPruefungsaktivitaetenManagementLib extends DVUHManagementLib
{
	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');

		// load models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefungsaktivitaeten_model', 'PruefungsaktivitaetenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefungsaktivitaeten_loeschen_model', 'PruefungsaktivitaetenLoeschenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHPruefungsaktivitaeten_model', 'DVUHPruefungsaktivitaetenModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Sends Pruefungsaktivitaeten to DVUH.
	 * @param int $person_id
	 * @param string $studiensemester
	 * @param bool $preview
	 * @return object error or success
	 */
	public function sendPruefungsaktivitaeten($person_id, $studiensemester, $preview = false)
	{
		$infos = array();
		$warnings = array();

		$requiredFields = array('person_id', 'studiensemester');

		foreach ($requiredFields as $requiredField)
		{
			if (!isset($$requiredField))
				return error("Daten fehlen: ".ucfirst($requiredField));
		}

		// TODO phrases
		$no_pruefungen_info = 'Keine Pruefungsaktivitäten vorhanden, in DVUH gespeicherte Aktivitäten werden gelöscht, wenn vorhanden';

		$studiensemester_kurzbz = $this->_ci->dvuhconversionlib->convertSemesterToFHC($studiensemester);

		if ($preview)
		{
			$postData = $this->_ci->PruefungsaktivitaetenModel->retrievePostData($this->_be, $person_id, $studiensemester_kurzbz);

			if (isError($postData))
				return $postData;

			if (hasData($postData))
				$postData = getData($postData);
			else
				$infos[] = $no_pruefungen_info;

			return $this->getResponseArr($postData, $infos);
		}

		$prestudentsToPost = array();

		$pruefungsaktivitaetenResult = $this->_ci->PruefungsaktivitaetenModel->post(
			$this->_be,
			$person_id,
			$studiensemester_kurzbz,
			$prestudentsToPost
		);

		if (isError($pruefungsaktivitaetenResult))
			return $pruefungsaktivitaetenResult;

		$pruefungsaktivitaetenResultData = null;

		if (hasData($pruefungsaktivitaetenResult))
		{
			$xmlstr = getData($pruefungsaktivitaetenResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($parsedObj))
				return $parsedObj;
		}
		else
			$infos[] = $no_pruefungen_info;

		// check for each prestudent to post if deletion is needed
		foreach ($prestudentsToPost as $prestudent_id => $ects)
		{
			// if no ects were sent
			if ($ects['ects_angerechnet'] == 0 && $ects['ects_erworben'] == 0)
			{
				// get last sent ects
				$checkPruefungsaktivitaetenRes = $this->_ci->DVUHPruefungsaktivitaetenModel->getLastSentPruefungsaktivitaet(
					$prestudent_id,
					$studiensemester_kurzbz
				);

				if (hasData($checkPruefungsaktivitaetenRes))
				{
					$checkPruefungsaktivitaeten = getData($checkPruefungsaktivitaetenRes)[0];

					// if there were ects sent before, delete all Pruefungsaktivitaeten for the prestudent
					if (isset($checkPruefungsaktivitaeten->ects_angerechnet) || isset($checkPruefungsaktivitaeten->ects_erworben))
					{
						$deletePruefunsaktivitaetenRes = $this->deletePruefungsaktivitaeten($person_id, $studiensemester_kurzbz, $prestudent_id);

						if (isError($deletePruefunsaktivitaetenRes))
							return $deletePruefunsaktivitaetenRes;

						if (hasData($deletePruefunsaktivitaetenRes))
						{
							$deletePruefungsaktivitaeten = getData($deletePruefunsaktivitaetenRes);

							$infos = array_merge($infos, $deletePruefungsaktivitaeten['infos']);
							$warnings = array_merge($warnings, $deletePruefungsaktivitaeten['warnings']);
						}
					}
				}
			}
			else
			{
				// if at least some ects were sent, write to sync table
				$ects_angerechnet_posted = $ects['ects_angerechnet'] == 0
					? null
					: number_format($ects['ects_angerechnet'], 2, '.', '');

				$ects_erworben_posted = $ects['ects_erworben'] == 0
					? null
					: number_format($ects['ects_erworben'], 2, '.', '');

				$pruefungsaktivitaetenSaveResult = $this->_ci->DVUHPruefungsaktivitaetenModel->insert(
					array(
						'prestudent_id' => $prestudent_id,
						'studiensemester_kurzbz' => $studiensemester_kurzbz,
						'ects_angerechnet' => $ects_angerechnet_posted,
						'ects_erworben' => $ects_erworben_posted,
						'meldedatum' => date('Y-m-d')
					)
				);

				if (isError($pruefungsaktivitaetenSaveResult))
					$warnings[] = error('Pruefungsaktivitätenmeldung erfolgreich, Fehler beim Speichern in der Synctabelle in FHC');
			}
		}

		$infos[] = 'Pruefungsaktivitätenmeldung erfolgreich';

		$result = $this->getResponseArr(
			$pruefungsaktivitaetenResultData,
			$infos,
			$warnings,
			true
		);

		return $result;
	}

	/**
	 * Deletes all Pruefungsaktivitäten of a person in DVUH.
	 * @param $person_id
	 * @param $studiensemester
	 * @param $prestudent_id
	 * @return object
	 */
	public function deletePruefungsaktivitaeten($person_id, $studiensemester, $prestudent_id = null)
	{
		$infos = array();
		$warnings = array();

		$requiredFields = array('person_id', 'studiensemester');

		foreach ($requiredFields as $requiredField)
		{
			if (!isset($$requiredField))
				return error("Daten fehlen: ".ucfirst($requiredField));
		}

		$studiensemester_kurzbz = $this->_ci->dvuhconversionlib->convertSemesterToFHC($studiensemester);

		$deleteRes = $this->_ci->PruefungsaktivitaetenLoeschenModel->delete($this->_be, $person_id, $studiensemester, $prestudent_id);

		if (isError($deleteRes))
			return $deleteRes;

		if (hasData($deleteRes))
		{
			$prestudentIdsResult = getData($deleteRes);

			// delete returns array of deleted prestudent ids
			foreach ($prestudentIdsResult as $prestudent_id)
			{
				// add entry to sync table with NULL to identify when Prüfungsaktivitäten were deleted
				$pruefungsaktivitaetenSaveResult = $this->_ci->DVUHPruefungsaktivitaetenModel->insert(
					array(
						'prestudent_id' => $prestudent_id,
						'studiensemester_kurzbz' => $studiensemester_kurzbz,
						'ects_angerechnet' => null,
						'ects_erworben' => null,
						'meldedatum' => date('Y-m-d')
					)
				);

				if (isError($pruefungsaktivitaetenSaveResult))
					$warnings[] = error('Pruefungsaktivitätenmeldung erfolgreich, Fehler beim Speichern in der Synctabelle in FHC');// TODO phrases
			}

			$infos[] = "Prüfungsaktivitäten in Datenverbund gelöscht, prestudent Id(s): " . implode(', ', $prestudentIdsResult); // TODO phrases
		}
		else
			$infos[] = "No Prüfungsaktivitäten found for deletion"; // TODO phrases

		return $this->getResponseArr(
			null,
			$infos,
			$warnings
		);
	}
}
