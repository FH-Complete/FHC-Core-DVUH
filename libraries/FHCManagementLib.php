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

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('crm/Prestudentstatus_model', 'PrestudentstatusModel');
		$this->_ci->load->model('crm/Konto_model', 'KontoModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');

		$this->_dbModel = new DB_Model();
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
		return $this->getReportablePrestudents($studiensemester, null, $status_kurzbz, $person_id);
	}

	/**
	 * Gets all valid prestudents of a person which should be reported to BIS.
	 * @param string $studiensemester
	 * @param int $studiengang_kz
	 * @param array $status_kurzbz
	 * @param int $person_id
	 * @return object success with prestudents or error
	 */
	public function getReportablePrestudents($studiensemester, $studiengang_kz = null, $status_kurzbz = null, $person_id = null)
	{
		$params = array($studiensemester);

		$prstQry = "SELECT DISTINCT ON (prestudent_id) prestudent_id, person_id,
						stg.studiengang_kz, stg.erhalter_kz, stg.oe_kurzbz,
						pers.matr_nr, stud.matrikelnr AS personenkennzeichen
					FROM public.tbl_prestudent ps
					JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
					JOIN public.tbl_person pers USING(person_id)
					JOIN public.tbl_studiengang stg USING(studiengang_kz)
					LEFT JOIN public.tbl_student stud USING (prestudent_id)
					WHERE pss.studiensemester_kurzbz = ?
					AND ps.bismelden = TRUE
					AND stg.melderelevant = TRUE";

		if (isset($studiengang_kz))
		{
			$prstQry .= " AND stg.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
		{
			$prstQry .= " AND pss.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}

		if (isset($person_id))
		{
			$prstQry .= " AND pers.person_id = ?";
			$params[] = $person_id;
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
		return $this->_dbModel->execReadOnlyQuery(
			"SELECT student_uid AS uid FROM (
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

	/*
	 * Gets charges of a student in a certain semester, for certain buchungstypen.
	 * @param int person_id
	 * @param string studiensemester_kurzbz
	 * @return object success or error
	 */
	public function getBuchungenOfStudent($person_id, $studiensemester_kurzbz, $buchungstypen)
	{
		return $this->_dbModel->execReadOnlyQuery(
			"SELECT person_id, studiengang_kz, buchungsdatum, betrag, buchungsnr, zahlungsreferenz, buchungstyp_kurzbz,
				   studiensemester_kurzbz, buchungstext, buchungsdatum,
					(SELECT count(*) FROM public.tbl_konto kto /* no Gegenbuchung yet */
								WHERE kto.person_id = tbl_konto.person_id
								AND kto.buchungsnr_verweis = tbl_konto.buchungsnr) AS bezahlt
			FROM public.tbl_konto
			WHERE person_id = ?
			AND studiensemester_kurzbz = ?
			AND buchungsnr_verweis IS NULL
			AND betrag <= 0
			AND EXISTS (SELECT 1 FROM public.tbl_prestudent
							JOIN public.tbl_prestudentstatus USING (prestudent_id)
							WHERE tbl_prestudent.person_id = tbl_konto.person_id
							AND tbl_prestudentstatus.studiensemester_kurzbz = tbl_konto.studiensemester_kurzbz)
			AND buchungstyp_kurzbz IN ?
			ORDER BY buchungsdatum, buchungsnr",
			array(
				$person_id,
				$studiensemester_kurzbz,
				$buchungstypen
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
		return $this->_dbModel->execReadOnlyQuery(
			"SELECT buchungsnr
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
								/* old Matrikelnummer, older than current first prestudentstatus. */
								/* +1 for SS because year in Matrikelnr spans 2 Semester */
								AND
								SUBSTRING(matr_nr, 2, 2)::int + (CASE WHEN SUBSTRING(sem.studiensemester_kurzbz, 1, 2) = 'SS' THEN 1 ELSE 0 END)
								<
								(
									SELECT SUBSTRING(studiensemester_kurzbz, 5, 2) /* comparing year of matrikelnr and status studiensemester */
									FROM public.tbl_prestudent
									JOIN public.tbl_prestudentstatus first_status USING (prestudent_id)
									JOIN public.tbl_studiensemester first_status_sem USING (studiensemester_kurzbz)
									WHERE prestudent_id = ps.prestudent_id
									ORDER BY first_status_sem.start, first_status.datum
									LIMIT 1
								)::int
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
		if (!$this->_ci->dvuhcheckinglib->checkBpk($bpk))
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
	 * Saves ekz in FHC db.
	 * @param int $person_id
	 * @param string $ersatzkennzeichen
	 * @return object success with ekz if saved, or error
	 */
	public function saveEkzInFhc($person_id, $ersatzkennzeichen)
	{
		if (!$this->_ci->dvuhcheckinglib->checkEkz($ersatzkennzeichen))
			return error("Invalid ekz");

		return $this->_ci->PersonModel->update(
			array(
				'person_id' => $person_id
			),
			array(
				'ersatzkennzeichen' => $ersatzkennzeichen,
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
			$gegenbuchungNullify = $this->_ci->KontoModel->insert(
				array(
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
		// check if sync table exists (SAP sync extension should be installed)
		$tblExistsQry = "SELECT 1
							FROM information_schema.tables
							WHERE table_schema = 'sync' and table_name='tbl_sap_salesorder'";

		$tblExistsRes = $this->_dbModel->execReadOnlyQuery($tblExistsQry);

		if (isError($tblExistsRes))
			return $tblExistsRes;

		// check if buchungs was already sent and is in synctable
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

	/**
	 * Checks if prestudenstatus of Semester previous to given Studiensemester has a certain type.
	 * @param int $prestudent_id
	 * @param string $studiensemester_kurzbz
	 * @param array $status_kurzbz_arr status kurzbz to check
	 * @return object success with true/false or error
	 */
	public function checkPreviousPrestudentStatusType($prestudent_id, $studiensemester_kurzbz, $status_kurzbz_arr)
	{
		// status_kurzbz_arr has to be array
		if (!is_array($status_kurzbz_arr)) $status_kurzbz_arr = array($status_kurzbz_arr);

		// get previous semester
		$previousStudiensemesterRes = $this->_ci->StudiensemesterModel->getPreviousFrom($studiensemester_kurzbz);

		if (isError($previousStudiensemesterRes))
			return $previousStudiensemesterRes;

		// check the type(s)
		if (hasData($previousStudiensemesterRes))
		{
			$previousStudiensemester = getData($previousStudiensemesterRes)[0]->studiensemester_kurzbz;

			return $this->_checkLastStatusType($prestudent_id, $previousStudiensemester, $status_kurzbz_arr);
		}

		return success(array(false));
	}

	/*
	 * Gets first status of a prestudent status series, descending from the given studiensemester_kurzbz.
	 * e.g.if a student has the same status for 3 semester, and the method is called for the third, date of the first semester is returned.
	 * @param int $prestudent_id
	 * @param string $studiensemester_kurzbz start from this semester and go back
	 * @param array $searched_status_arr status to check. If multiple status given, series continues as long as status is one of them.
	 * @return object success with date or error
	 */
	public function getFirstDateOfPrestudentStatusSeries($prestudent_id, $studiensemester_kurzbz, $searched_status_arr)
	{
		// searched_status has to be array
		if (!is_array($searched_status_arr)) $searched_status_arr = array($searched_status_arr);

		$prevFirstStatusDate = null;

		$statusRes = $this->getAllPrestudentStatusUntilStudiensemester($prestudent_id, $studiensemester_kurzbz);

		if (isError($statusRes)) return $statusRes;

		if (hasData($statusRes))
		{
			$status = getData($statusRes);

			foreach ($status as $st)
			{
				// if status is a searched status, get the date
				if (in_array($st->status_kurzbz, $searched_status_arr))
					$prevFirstStatusDate = $st->datum;
				else // stop when status is not a searched status anymore
					break;
			}
		}

		return success($prevFirstStatusDate);
	}

	/*
	 * Gets first status of a prestudent status series, descending from the given studiensemester_kurzbz, stopping at a stop status.
	 * e.g.if a student has the same status for 3 semester, and the method is called for the third, date of the first semester is returned.
	 * @param int $prestudent_id
	 * @param string $studiensemester_kurzbz start from this semester and go back
	 * @param array $searched_status_arr status to check. If multiple status given, series continues as long as status is one of them.
	 * @param string $search_stop_status series stops when this status is encountered.
	 * @return object success with date or error
	 */
	public function getFirstDateOfPrestudentStatusSeriesAfterStatus($prestudent_id, $studiensemester_kurzbz, $searched_status_arr, $search_stop_status)
	{
		// status_kurzbz_arr has to be array
		if (!is_array($searched_status_arr)) $searched_status_arr = array($searched_status_arr);

		$prevFirstStatusDate = null;
		$currentStatus = null;

		$statusRes = $this->getAllPrestudentStatusUntilStudiensemester($prestudent_id, $studiensemester_kurzbz);

		if (isError($statusRes)) return $statusRes;

		if (hasData($statusRes))
		{
			$status = getData($statusRes);

			foreach ($status as $st)
			{
				// if status is a searched status, get the date
				if (in_array($st->status_kurzbz, $searched_status_arr))
					$currentStatus = $st->datum;
				else // if it is not a searched status anymore...
				{
					// ...save the currentStatus as previous first status if stopped at the stop status
					if ($st->status_kurzbz === $search_stop_status)
						$prevFirstStatusDate = $currentStatus;
					break; // ...and stop
				}
			}
		}

		return success($prevFirstStatusDate);
	}

	/*
	 * Gets all prestudent status until (incl.) a Studiensemester.
	 * @param int $prestudent_id
	 * @param string $studiensemester_kurzbz start from this semester and go back
	 * @return object success or error
	 */
	public function getAllPrestudentStatusUntilStudiensemester($prestudent_id, $studiensemester_kurzbz)
	{
		$qry = '
				SELECT status.datum, status.status_kurzbz
				FROM public.tbl_prestudentstatus status
				JOIN public.tbl_studiensemester sem USING (studiensemester_kurzbz)
				WHERE prestudent_id = ?
				AND sem.start::date <= (SELECT start from public.tbl_studiensemester WHERE studiensemester_kurzbz = ?)::date
				ORDER BY sem.start DESC, status.datum DESC, status.insertamum DESC';

		return $this->_dbModel->execReadOnlyQuery($qry, array($prestudent_id, $studiensemester_kurzbz));
	}

	/**
	 * Checks if last prestudenstatus of a prestudent of a certain semester has a certain type.
	 * @param int $prestudent_id
	 * @param string $studiensemester_kurzbz
	 * @param array $status_kurzbz_arr status kurzbz to check
	 * @return object success with true/false or error
	 */
	private function _checkLastStatusType($prestudent_id, $studiensemester_kurzbz, $status_kurzbz_arr)
	{
		// get last status for the prestudent and semester
		$lastStatusRes = $this->_ci->PrestudentstatusModel->getLastStatus($prestudent_id, $studiensemester_kurzbz);

		if (isError($lastStatusRes))
			return $lastStatusRes;

		// check the type(s)
		if (hasData($lastStatusRes))
		{
			$lastStatusKurzbz = getData($lastStatusRes)[0]->status_kurzbz;

			if (in_array($lastStatusKurzbz, $status_kurzbz_arr))
				return success(array(true));
		}

		return success(array(false));
	}
}
