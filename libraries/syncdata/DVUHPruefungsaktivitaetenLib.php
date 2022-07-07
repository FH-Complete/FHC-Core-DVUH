<?php

/**
 * Library for retrieving data from FHC for DVUH.
 * Extracts data from FHC db, performs data quality checks and puts data in DVUH form.
 */
class DVUHPruefungsaktivitaetenLib
{
	private $_ci;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/JQMSchedulerLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');

		// load models
		$this->_ci->load->model('education/Zeugnisnote_model', 'ZeugnisnoteModel');

		// load helpers
		//$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Retrieves Prüfungsaktivitäten data for sending to DVUH, for each prestudent of a person.
	 * Sums up ects angerechnet and erworben.
	 * @param int $person_id
	 * @param string $studiensemester
	 * @return object prestudent ids with ects data or error
	 */
	public function getPruefungsaktivitaetenData($person_id, $studiensemester)
	{
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz');
		$note_angerechnet_ids = $this->_ci->config->item('fhc_dvuh_sync_note_angerechnet');

		$prestudentEcts = array();

		//get all valid prestudents of person
		$prestudentsRes = $this->_ci->fhcmanagementlib->getPrestudentsOfPerson(
			$person_id,
			$studiensemester,
			$status_kurzbz[JQMSchedulerLib::JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN]
		);

		if (isError($prestudentsRes))
			return $prestudentsRes;

		if (hasData($prestudentsRes))
		{
			$prestudents = getData($prestudentsRes);

			foreach ($prestudents as $prestudent)
			{
				// convert erhalter kz and studiengangskennzahl to DVUH format
				$isAusserordentlich = isset($prestudent->personenkennzeichen) && $this->_ci->dvuhcheckinglib->checkIfAusserordentlich($prestudent->personenkennzeichen);

				$meldeStudiengangRes = $this->_ci->dvuhconversionlib->getMeldeStudiengangKz($prestudent->studiengang_kz, $prestudent->erhalter_kz, $isAusserordentlich);

				if (isError($meldeStudiengangRes))
					return $meldeStudiengangRes;

				$melde_studiengang_kz = null;
				if (hasData($meldeStudiengangRes))
					$melde_studiengang_kz = getData($meldeStudiengangRes);

				$prestudentEctsObj = new stdClass();
				$prestudentEctsObj->ects_erworben = 0.0;
				$prestudentEctsObj->ects_angerechnet = 0.0;
				$prestudentEctsObj->dvuh_stgkz = $melde_studiengang_kz;
				$prestudentEctsObj->matr_nr = $prestudent->matr_nr;
				$prestudentEcts[$prestudent->prestudent_id] = $prestudentEctsObj;
			}
		}

		// get ects sums of Noten which are aktiv, both lehre and non-lehre, offiziell, positiv, have zeugnis = true
		$zeugnisNotenResult = $this->_ci->ZeugnisnoteModel->getByPerson($person_id, $studiensemester, true, null, true, true, true);

		if (isError($zeugnisNotenResult))
			return $zeugnisNotenResult;

		if (hasData($zeugnisNotenResult))
		{
			$zeugnisNoten = getData($zeugnisNotenResult);

			// sum up ects by prestudent, angerechnete Noten separately
			foreach ($zeugnisNoten as $note)
			{
				if (isset($prestudentEcts[$note->prestudent_id]) && isset($note->ects))
				{
					if (in_array($note->note, $note_angerechnet_ids))
						$prestudentEcts[$note->prestudent_id]->ects_angerechnet += $note->ects;
					else
						$prestudentEcts[$note->prestudent_id]->ects_erworben += $note->ects;
				}
			}
		}

		return success($prestudentEcts);
	}
}
