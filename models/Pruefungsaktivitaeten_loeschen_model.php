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
	 * Deletes all Prüfungsaktivitäten of a person in DVUH.
	 * @param string $be
	 * @param int $person_id
	 * @param string $semester
	 * @return object success with all deleted prestudent Ids or error
	 */
	public function delete($be, $person_id, $semester)
	{
		if (isEmptyString($person_id))
			return error('personID nicht gesetzt');
		else
		{
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
					$params = array(
						'uuid' => getUUID(),
						'be' => $be
					);

					// studiengang kz
					$dvuh_erhalter_kz = $this->dvuhsynclib->convertErhalterkennzahlToDVUH($prestudent->erhalter_kz);
					$isAusserordentlich = isset($prestudent->personenkennzeichen) && $this->dvuhsynclib->checkIfAusserordentlich($prestudent->personenkennzeichen);

					// special stg kz if ausserordentlich
					$dvuh_stgkz = $isAusserordentlich
						? $this->dvuhsynclib->convertStudiengangskennzahlToDVUHAusserordentlich($prestudent->studiengang_kz, $dvuh_erhalter_kz)
						: $this->dvuhsynclib->convertStudiengangskennzahlToDVUH($prestudent->studiengang_kz);

					$params['matrikelnummer'] = $prestudent->matr_nr;
					$params['semester'] = $dvuh_studiensemester;
					$params['studienkennung'] = $dvuh_stgkz;

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
}
