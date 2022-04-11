<?php

/**
 * Library for handling operations concerning fhcomplete database.
 */
class FHCManagementLib
{
	const DVUH_USER = 'dvuhsync';

	private $_ci;
	private $_dbModel;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
		$this->_dbModel = new DB_Model();

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets all valid prestudents of a person.
	 * @param int $person_id
	 * @param string $studiensemester
	 * @param array $status_kurzbz
	 * @return object success with prestudents or error
	 */
	public function getPrestudentsOfPerson($person_id, $studiensemester, $status_kurzbz = null)
	{
		$params = array(
			$person_id,
			$studiensemester
		);

		$prstQry = "SELECT DISTINCT ON (prestudent_id) prestudent_id, stg.studiengang_kz, stg.erhalter_kz,
                                   pers.matr_nr, stud.matrikelnr AS personenkennzeichen
					FROM public.tbl_prestudent ps
					JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
					JOIN public.tbl_person pers USING(person_id)
					JOIN public.tbl_studiengang stg USING(studiengang_kz)
					LEFT JOIN public.tbl_student stud USING (prestudent_id)
					WHERE person_id = ?
					AND pss.studiensemester_kurzbz = ?
					AND ps.bismelden = TRUE
					AND stg.melderelevant = TRUE";

		if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
		{
			$prstQry .= " AND pss.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}

		return $this->_dbModel->execReadOnlyQuery(
			$prstQry,
			$params
		);
	}

	/**
	 * Gets uids for a person with prestudent in a certain semester.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz
	 * @return object success or error
	 */
	public function getUids($person_id, $studiensemester_kurzbz)
	{
		return $this->_dbModel->execReadOnlyQuery("
								SELECT student_uid AS uid FROM (
									SELECT student_uid, max(ben.insertamum) AS insertamum
								    FROM public.tbl_benutzer ben
								    JOIN public.tbl_student stud ON ben.uid = stud.student_uid
									JOIN public.tbl_prestudent USING (prestudent_id)
									JOIN public.tbl_prestudentstatus USING (prestudent_id)
									WHERE ben.person_id = ?
									AND studiensemester_kurzbz = ?
									GROUP BY student_uid
								) uids
								ORDER BY insertamum DESC",
			array(
				$person_id, $studiensemester_kurzbz
			)
		);
	}

	/**
	 * Gets non-paid Buchungen of a person, i.e. no other Buchung has it as buchungsnr_verweis.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz
	 * @param array $buchungstypen limit to only certain types
	 * @return mixed
	 */
	public function getUnpaidBuchungen($person_id, $studiensemester_kurzbz, $buchungstypen)
	{
		return $this->_dbModel->execReadOnlyQuery("
								SELECT buchungsnr
								FROM public.tbl_konto
								WHERE person_id = ?
								  AND studiensemester_kurzbz = ?
								  AND buchungsnr_verweis IS NULL
								  AND betrag < 0
								  AND NOT EXISTS (SELECT 1 FROM public.tbl_konto kto 
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr)
								  AND buchungstyp_kurzbz IN ?
								  ORDER BY buchungsdatum, buchungsnr
								  LIMIT 1",
			array(
				$person_id, $studiensemester_kurzbz, $buchungstypen
			)
		);
	}

	/**
	 * Updates a Matrikelnummer in FHC database
	 * @param int $person_id
	 * @param string $matrikelnummer
	 * @param bool $matr_aktiv
	 * @return object success or error
	 */
	public function updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv)
	{
		$updateResult = $this->_ci->PersonModel->update(
			$person_id,
			array(
				'matr_nr' => $matrikelnummer,
				'matr_aktiv' => $matr_aktiv,
				'updateamum' => date('Y-m-d H:i:s'),
				'updatevon' => self::DVUH_USER
			)
		);

		if (hasData($updateResult))
		{
			$updateResArr = array('matr_nr' => $matrikelnummer, 'matr_aktiv' => $matr_aktiv);

			return success($updateResArr);
		}
		else
		{
			return error("Fehler beim Aktualisieren der Matrikelnummer in FHC");
		}
	}

	/**
	 * Sets matr_nr to NULL if it is an old inactive Matrikelnr.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz
	 * @return object success (with result if matrnr is reset) or error
	 */
	public function resetInactiveMatrikelnummer($person_id, $studiensemester_kurzbz)
	{
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz');
		$matrikelnr_status_kurzbz = $status_kurzbz[JQMSchedulerLib::JOB_TYPE_REQUEST_MATRIKELNUMMER];
		$active_status_kurzbz_array = $this->_ci->config->item('fhc_dvuh_active_student_status_kurzbz');

		// check if person has old non-activated Matrikelnummer which needs to be overwritten by a new one
		$personForResetQry = "SELECT 1 FROM
								public.tbl_person pers
								JOIN public.tbl_prestudent ps USING (person_id)
								JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
								JOIN public.tbl_studiensemester sem USING (studiensemester_kurzbz)
								WHERE pers.matr_nr IS NOT NULL AND pers.matr_aktiv = FALSE
								AND person_id = ?
								AND pss.studiensemester_kurzbz = ?
								AND pss.status_kurzbz IN ?
								AND SUBSTRING(matr_nr, 2, 2) < ( /* old Matrikelnummer, older than current first prestudentstatus */
									SELECT SUBSTRING(studiensemester_kurzbz, 5, 2) 
									FROM public.tbl_prestudent
									JOIN public.tbl_prestudentstatus USING (prestudent_id)
									WHERE prestudent_id = ps.prestudent_id
									ORDER BY datum
									LIMIT 1
								)
								/* check for two digits of year works only for 100 years */
								AND SUBSTRING(studiensemester_kurzbz, 3, 4)::integer BETWEEN 2000 AND 2099
								AND NOT EXISTS ( /* no active prestudents in past */
									SELECT 1 FROM public.tbl_prestudent
									JOIN public.tbl_prestudentstatus ps_past USING (prestudent_id)
									JOIN public.tbl_studiensemester sem_past USING (studiensemester_kurzbz)
									WHERE status_kurzbz IN ?
									AND tbl_prestudent.person_id = pers.person_id
									AND prestudent_id <> ps.prestudent_id
									AND (ps_past.datum::date < pss.datum::date OR sem_past.start::date < sem.start::date)
								)";

		$personForResetRes = $this->_dbModel->execReadOnlyQuery(
			$personForResetQry,
			array(
				$person_id,
				$studiensemester_kurzbz,
				$matrikelnr_status_kurzbz,
				$active_status_kurzbz_array
			)
		);

		if (isError($personForResetRes))
			return $personForResetRes;

		// set null if Matrikelnr is old
		if (hasData($personForResetRes))
		{
			return $this->_ci->PersonModel->update(
				array(
					'person_id' => $person_id
				),
				array(
					'matr_nr' => null
				)
			);
		}
		else
			return success(null);
	}

	/**
	 * Saves bpk in FHC db.
	 * @param int $person_id
	 * @param string $bpk
	 * @return object success with bpk if saved, or error
	 */
	public function saveBpkInFhc($person_id, $bpk)
	{
		if (!$this->_ci->dvuhsynclib->checkBpk($bpk))
			return error("Invalid bPK");

		return $this->_ci->PersonModel->update(
			array(
				'person_id' => $person_id
			),
			array(
				'bpk' => $bpk,
				'updateamum' => date('Y-m-d H:i:s'),
				'updatevon' => self::DVUH_USER
			)
		);
	}

	/**
	 * Sets a Buchung in FHC to 0 and creates a Gegenbuchung with 0.
	 * @param object $buchung contains buchungsdata
	 * @return object success or error
	 */
	public function nullifyBuchung($buchung)
	{
		$andereBeBezahltTxt = 'An anderer Bildungseinrichtung bezahlt';
		$buchungNullify = $this->_ci->KontoModel->update(
			array('buchungsnr' => $buchung->buchungsnr),
			array(
				'betrag' => 0,
				'anmerkung' => $andereBeBezahltTxt,
				'updateamum' => date('Y-m-d H:i:s'),
				'updatevon' => self::DVUH_USER
			)
		);

		if (hasData($buchungNullify))
		{
			$gegenbuchungNullify = $this->_ci->KontoModel->insert(array(
					'person_id' => $buchung->person_id,
					'studiengang_kz' => $buchung->studiengang_kz,
					'studiensemester_kurzbz' => $buchung->studiensemester_kurzbz,
					'betrag' => 0,
					'buchungsdatum' => date('Y-m-d'),
					'buchungstext' => $buchung->buchungstext,
					'buchungstyp_kurzbz' => $buchung->buchungstyp_kurzbz,
					'buchungsnr_verweis' => $buchung->buchungsnr,
					'insertvon' => self::DVUH_USER,
					'insertamum' => date('Y-m-d H:i:s'),
					'anmerkung' => $andereBeBezahltTxt
				)
			);

			if (isError($gegenbuchungNullify))
				return $gegenbuchungNullify;

			return success("Buchung Erfolgreich nullifiziert");
		}
		else
			return error("Fehler beim Nullifizieren der Buchung");
	}

	/**
	 * Checks if a certain Kontobuchung is already sent as salesorder to SAP.
	 * @param int $buchungsnr
	 * @return object success with true/false or error
	 */
	public function checkIfSentToSap($buchungsnr)
	{
		$tblExistsQry = "SELECT 1
							FROM information_schema.tables
							WHERE table_schema = 'sync' and table_name='tbl_sap_salesorder'";

		$tblExistsRes = $this->_dbModel->execReadOnlyQuery($tblExistsQry);

		if (isError($tblExistsRes))
			return $tblExistsRes;

		if (hasData($tblExistsRes))
		{
			$sentToSapQry = "SELECT 1
								FROM sync.tbl_sap_salesorder
								WHERE buchungsnr = ?";

			$sentToSapRes = $this->_dbModel->execReadOnlyQuery($sentToSapQry, array($buchungsnr));

			if (isError($sentToSapRes))
				return $sentToSapRes;

			if (hasData($sentToSapRes))
				return success(array(true));
		}

		return success(array(false));
	}
}
