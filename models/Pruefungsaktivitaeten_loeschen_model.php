<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * delerw Pruefungsaktivitäten for Students
 */
class Pruefungsaktivitaeten_loeschen_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'pruefungsaktivitaeten_loeschen.xml';

		$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
	}

	/**
	 * Deletes all Prüfungsaktivitäten of a person or prestudent in DVUH.
	 * @param string $be
	 * @param int $person_id
	 * @param string $semester
	 * @param int $prestudent_id
	 * @return object success with all deleted prestudent Ids or error
	 */
	public function delete($be, $person_id, $semester, $prestudent_id = null)
	{
		if (isEmptyString($person_id))
			return error('personID nicht gesetzt');

		$status_kurzbz = $this->config->item('fhc_dvuh_status_kurzbz');

		$dvuh_studiensemester = $this->dvuhsynclib->convertSemesterToDVUH($semester);

		// data of Pruefungsaktivitaeten for prestudents of the person
		$studiensemester_kurzbz = $this->dvuhsynclib->convertSemesterToFHC($semester);
		$prestudentsDataResult = $this->fhcmanagementlib->getPrestudentsOfPerson($person_id, $studiensemester_kurzbz, $status_kurzbz[JQMSchedulerLib::JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN]);

		if (isError($prestudentsDataResult))
			return $prestudentsDataResult;
		elseif (hasData($prestudentsDataResult))
		{
			$resultArr = array();
			$prestudentsData = getData($prestudentsDataResult);

			foreach ($prestudentsData as $prestudent)
			{
				// if prestudent Id given, only delete this prestudent
				if (isset($prestudent_id) && $prestudent_id != $prestudent->prestudent_id)
					continue;

				$params = array(
					'uuid' => getUUID(),
					'be' => $be
				);

				// studiengang kz
				$isAusserordentlich = isset($prestudent->personenkennzeichen) && $this->dvuhsynclib->checkIfAusserordentlich($prestudent->personenkennzeichen);
				$meldeStudiengangRes = $this->dvuhsynclib->getMeldeStudiengangKz($prestudent->studiengang_kz, $prestudent->erhalter_kz, $isAusserordentlich);

				if (isError($meldeStudiengangRes))
					return $meldeStudiengangRes;

				$melde_studiengang_kz = null;
				if (hasData($meldeStudiengangRes))
					$melde_studiengang_kz = getData($meldeStudiengangRes);

				// delete Prüfungsaktivitaeten endpoint needs only 4 digits studiengang kz - chop off erhalter kz
				if (strlen($melde_studiengang_kz) == DVUHSyncLib::DVUH_ERHALTER_LENGTH + DVUHSyncLib::DVUH_STGKZ_LENGTH)
				{
					$melde_studiengang_kz = substr($melde_studiengang_kz, -1 * DVUHSyncLib::DVUH_STGKZ_LENGTH);
				}

				$params['matrikelnummer'] = $prestudent->matr_nr;
				$params['semester'] = $dvuh_studiensemester;
				$params['studienkennung'] = $melde_studiengang_kz;

				$callRes = $this->_call('POST', $params);

				if (isSuccess($callRes))
				{
					$resultArr[] = $prestudent->prestudent_id;
				}
				else
					return $callRes;
			}

			return success($resultArr);
		}
		else
			return error("Keine Prestudenten gefunden!");
	}
}
