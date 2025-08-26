<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncmanagement/DVUHMatrikelnummerManagementLib.php';

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying Matrikelnummer data in DVUH, and updating data in FHC accordingly.
 */
class DVUHUniMatrikelnummerManagementLib extends DVUHMatrikelnummerManagementLib
{
	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelmeldung_model', 'MatrikelmeldungModel');
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Saves masterdata with Matrikelnr in DVUH, sets Matrikelnr in FHC.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz semester for which stammdaten are sent
	 * @param string $matrikelnummer
	 * @param bool $matr_aktiv wether Matrnr is already active (or not yet valid)
	 * @param array $infos for storing info messages
	 * @return object
	 */
	protected function _sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $matrikelnummer, $matr_aktiv, &$infoArr)
	{
		$sendMasterDataResult = $this->_ci->MatrikelmeldungModel->post($this->_be, $person_id, $matrikelnummer);

		if (isError($sendMasterDataResult))
			$result = $sendMasterDataResult;
		elseif (hasData($sendMasterDataResult))
		{
			$xmlstr = getData($sendMasterDataResult);

			$matrNrRes = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($matrNrRes)) return $matrNrRes;

			$infos = array();
			$warningCodesToExcludeFromIssues = array();

			$handleBpkWarningsRes = $this->_ci->dvuhmasterdatamanagementlib->handleBpkWarningsFromResponse(
				$xmlstr,
				$person_id,
				$infos,
				$warningCodesToExcludeFromIssues
			);

			if (isError($handleBpkWarningsRes)) return $handleBpkWarningsRes;

			$updateMatrResult = $this->_ci->fhcmanagementlib->updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv);

			if (!hasData($updateMatrResult))
				$result = error("Fehler beim Updaten der Matrikelnummer");
			else
			{
				$infos[] = "Matrikelnummer $matrikelnummer erfolgreich für Person Id $person_id gemeldet";

				if ($matr_aktiv == true)
				{
					$infos[] = "Bestehende Matrikelnr $matrikelnummer der Person Id $person_id zugewiesen";
				}
				elseif ($matr_aktiv == false)
				{
					$infos[] = "Neue Matrikelnr $matrikelnummer erfolgreich der Person Id $person_id vorläufig zugewiesen";
				}

				$result = $this->getResponseArr($xmlstr, $infos, array(), true, $warningCodesToExcludeFromIssues);
			}
		}
		else
			$result = error("Fehler beim Melden der Matrikelnummer");

		return $result;
	}
}
