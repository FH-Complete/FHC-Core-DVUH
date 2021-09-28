<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance
	private $_status_kurzbz = array(); // contains prestudentstatus to retrieve for each jobtype
	private $_buchungstypen = array(); // contains Buchungstypen for which Charge should be sent
	private $_oe_kurzbz = array(); // oe_kurzbz - only students assigned to one of descendants of this oe are retrieved
	private $_angerechnet_note; // oe_kurzbz - only students assigned to one of descendants of this oe are retrieved

	const JOB_TYPE_REQUEST_MATRIKELNUMMER = 'DVUHRequestMatrikelnummer';
	const JOB_TYPE_SEND_CHARGE = 'DVUHSendCharge';
	const JOB_TYPE_SEND_PAYMENT = 'DVUHSendPayment';
	const JOB_TYPE_SEND_STUDY_DATA = 'DVUHSendStudyData';
	const JOB_TYPE_REQUEST_BPK = 'DVUHRequestBpk';
	const JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN = 'DVUHSendPruefungsaktivitaeten';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync'); // load sync config

		// set config items
		$this->_status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz');
		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$this->_buchungstypen = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);
		$oe_kurzbz = $this->_ci->config->item('fhc_dvuh_oe_kurzbz');
		$this->_angerechnet_note = $this->_ci->config->item('fhc_dvuh_sync_note_angerechnet');

		// get children if oe_kurzbz is set in config
		if (!isEmptyString($oe_kurzbz))
		{
			$this->_ci->load->model('organisation/Organisationseinheit_model', 'OrganisationseinheitModel');

			$childrenRes = $this->_ci->OrganisationseinheitModel->getChilds($oe_kurzbz);

			if (hasData($childrenRes))
			{
				$children = getData($childrenRes);
				foreach ($children as $child)
				{
					$this->_oe_kurzbz[] = $child->oe_kurzbz;
				}
			}
		}

		// load models
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets students for input of requestMatrikelnummer job.
	 * @param string $studiensemester_kurzbz semester for which Matrikelnr should be requested and Stammdaten should be sent
	 * @return object students
	 */
	public function requestMatrikelnummer($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;
		$params = array($studiensemester_kurzbz);

		// get students with no Matrikelnr
		$qry = "SELECT DISTINCT person_id, pss.studiensemester_kurzbz
				FROM public.tbl_person pers
					JOIN public.tbl_prestudent ps USING (person_id)
					JOIN public.tbl_student USING(prestudent_id)
					JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				WHERE ps.bismelden = TRUE
					AND stg.melderelevant = TRUE
					AND pers.matr_nr IS NULL
					AND pss.studiensemester_kurzbz = ?";

		if (isset($this->_status_kurzbz[self::JOB_TYPE_REQUEST_MATRIKELNUMMER]))
		{
			$qry .= " AND pss.status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_REQUEST_MATRIKELNUMMER];
		}

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		$dbModel = new DB_Model();

		$stToSyncResult = $dbModel->execReadOnlyQuery(
			$qry, $params
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($stToSyncResult)) return $stToSyncResult;

		// If students are present
		if (hasData($stToSyncResult))
		{
			$jobInput = json_encode(getData($stToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}

	/**
	 * Gets students for input of requestMatrikelnummer job.
	 * @param string $studiensemester_kurzbz semester for which Matrikelnr should be requested and Stammdaten should be sent
	 * @return object students
	 */
	public function requestBpk($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;
		$params = array($studiensemester_kurzbz);

		// get students with no BPK
		$qry = "SELECT
					DISTINCT person_id
				FROM
					public.tbl_person
					JOIN public.tbl_benutzer USING(person_id)
					JOIN public.tbl_student ON(tbl_student.student_uid=tbl_benutzer.uid)
					LEFT JOIN public.tbl_studiengang stg USING (studiengang_kz)
				WHERE
					public.tbl_benutzer.aktiv = TRUE
					AND tbl_person.matr_nr IS NOT NULL
					AND (tbl_person.bpk IS NULL OR tbl_person.bpk = '')
					AND stg.melderelevant = TRUE
					AND EXISTS(SELECT 1 FROM public.tbl_prestudent
					    		JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
								WHERE person_id=tbl_person.person_id
								AND bismelden = TRUE
								AND pss.studiensemester_kurzbz = ?";

		if (isset($this->_status_kurzbz[self::JOB_TYPE_REQUEST_BPK]))
		{
			$qry .= " AND pss.status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_REQUEST_BPK];
		}

		$qry .=		" LIMIT 1)";

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		$dbModel = new DB_Model();

		$stToSyncResult = $dbModel->execReadOnlyQuery(
			$qry, $params
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($stToSyncResult)) return $stToSyncResult;

		// If students are present
		if (hasData($stToSyncResult))
		{
			$jobInput = json_encode(getData($stToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}

	/**
	 * Gets students for input of sendCharge job.
	 * @param string $studiensemester_kurzbz prestudentstatus is checked for this semester
	 * @return object students
	 */
	public function sendCharge($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;

		$params = array($this->_buchungstypen, $studiensemester_kurzbz);

		// get students not sent to DVUH yet
		$qry = "SELECT DISTINCT persons.person_id, persons.studiensemester_kurzbz FROM (
					SELECT pers.person_id, pss.studiensemester_kurzbz, max(stammd.meldedatum) AS max_meldedatum, max(zlg.buchungsdatum) AS max_zlg_buchungsdatum,
					   pers.insertamum AS person_insertamum, pers.updateamum AS person_updateamum,
					   kto.insertamum AS kto_insertamum, kto.updateamum AS kto_updateamum, kto.buchungsnr
					FROM public.tbl_person pers
						JOIN public.tbl_prestudent ps USING (person_id)
						JOIN public.tbl_student USING (prestudent_id)
						JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
						LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
						LEFT JOIN public.tbl_konto kto ON pers.person_id = kto.person_id AND kto.buchungstyp_kurzbz IN ?
														AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz AND kto.buchungsnr_verweis IS NULL
														AND kto.betrag < 0
						LEFT JOIN sync.tbl_dvuh_stammdaten stammd ON pss.studiensemester_kurzbz = stammd.studiensemester_kurzbz AND pers.person_id = stammd.person_id
						LEFT JOIN sync.tbl_dvuh_zahlungen zlg ON kto.buchungsnr = zlg.buchungsnr
						WHERE ps.bismelden = TRUE
						AND stg.melderelevant = TRUE
						AND pss.studiensemester_kurzbz = ?";

		if (isset($this->_status_kurzbz[self::JOB_TYPE_SEND_CHARGE]))
		{
			$qry .= " AND pss.status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_SEND_CHARGE];
		}

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		$qry .= 		" GROUP BY pers.person_id, pss.studiensemester_kurzbz, kto.buchungsnr, kto_insertamum, kto_updateamum
				) persons
				LEFT JOIN (
					SELECT person_id, MAX(updateamum) AS updateamum, MAX(insertamum) AS insertamum
					FROM public.tbl_kontakt
					GROUP BY person_id
				) AS ktkt ON persons.person_id = ktkt.person_id
				LEFT JOIN (
					SELECT person_id, MAX(updateamum) AS updateamum, MAX(insertamum) AS insertamum
					FROM public.tbl_adresse
					GROUP BY person_id
				) AS adr ON persons.person_id = adr.person_id
				WHERE max_meldedatum IS NULL /* stammdaten not sent to DVUH yet */
				OR
				  (max_zlg_buchungsdatum IS NULL AND buchungsnr IS NOT NULL)  /* vorschreibung not sent to DVUH yet */
				OR
				  (persons.person_insertamum >= max_meldedatum OR ktkt.insertamum >= max_meldedatum /* modified since last sent to DVUH*/
					OR adr.insertamum >= max_meldedatum OR kto_insertamum >= max_meldedatum
					OR persons.person_updateamum >= max_meldedatum OR ktkt.updateamum >= max_meldedatum
					OR adr.updateamum >= max_meldedatum OR kto_updateamum >= max_meldedatum)";

		$dbModel = new DB_Model();

		$studToSyncResult = $dbModel->execReadOnlyQuery(
			$qry,
			$params
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($studToSyncResult)) return $studToSyncResult;

		// If students are present
		if (hasData($studToSyncResult))
		{
			$jobInput = json_encode(getData($studToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}

	/**
	 * Gets students for input of sendPayment job.
	 * @param string $studiensemester_kurzbz prestudentstatus is checked for this semester
	 * @return object students
	 */
	public function sendPayment($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;
		$params = array($this->_buchungstypen, $studiensemester_kurzbz);

		// get students with outstanding Buchungen not sent to DVUH yet
		$qry = "SELECT DISTINCT person_id, kto.studiensemester_kurzbz
				FROM public.tbl_person pers
					JOIN public.tbl_konto kto USING(person_id)
					JOIN public.tbl_prestudent ps USING (person_id)
					JOIN public.tbl_student using(prestudent_id)
					JOIN public.tbl_prestudentstatus pss ON ps.prestudent_id = pss.prestudent_id AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				WHERE ps.bismelden = TRUE
					AND stg.melderelevant = TRUE
					AND kto.buchungstyp_kurzbz IN ?
					AND kto.buchungsnr_verweis IS NOT NULL
					AND kto.betrag > 0
					AND NOT EXISTS (SELECT 1 from sync.tbl_dvuh_zahlungen /* payment not yet sent to DVUH */
									WHERE buchungsnr = kto.buchungsnr
									AND betrag > 0
									LIMIT 1)
					AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz
					AND kto.studiensemester_kurzbz = ?";

		if (isset($this->_status_kurzbz[self::JOB_TYPE_SEND_PAYMENT]))
		{
			$qry .= " AND pss.status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_SEND_PAYMENT];
		}

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		$dbModel = new DB_Model();

		$stToSyncResult = $dbModel->execReadOnlyQuery(
			$qry,
			$params
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($stToSyncResult)) return $stToSyncResult;

		// If students are present
		if (hasData($stToSyncResult))
		{
			$jobInput = json_encode(getData($stToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}

	/**
	 * Gets students for input of sendStudyData job.
	 * @param string $studiensemester_kurzbz prestudentstatus is checked for this semester
	 * @return object students
	 */
	public function sendStudyData($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;

		$params = array($studiensemester_kurzbz);

		// get students with vorschreibung which have no Studiumsmeldung or have a data change
		// data change: prestudent, prestudentstatus, bisio, mobilitaet
		$qry = "SELECT DISTINCT prestudents.prestudent_id, prestudents.studiensemester_kurzbz
				FROM (
						 SELECT ps.prestudent_id, pss.studiensemester_kurzbz,
								ps.insertamum AS ps_insertamum, pss.insertamum AS pss_insertamum, mob.insertamum as mob_insertamum, bisio.insertamum AS bisio_insertamum, 
								ps.updateamum AS ps_updateamum, pss.updateamum AS pss_updateamum, mob.updateamum AS mob_updateamum, bisio.updateamum AS bisio_updateamum,
								max(studd.meldedatum) AS max_studiumdaten_meldedatum, pss.datum AS prestudent_status_datum, bisio.bis AS bisio_endedatum
						 FROM public.tbl_prestudent ps
								  JOIN public.tbl_student using (prestudent_id)
								  JOIN public.tbl_prestudentstatus pss ON ps.prestudent_id = pss.prestudent_id
								  LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
								  LEFT JOIN bis.tbl_bisio bisio ON tbl_student.student_uid = bisio.student_uid
								  LEFT JOIN bis.tbl_mobilitaet mob ON ps.prestudent_id = mob.prestudent_id
								  LEFT JOIN sync.tbl_dvuh_studiumdaten studd
											ON pss.studiensemester_kurzbz = studd.studiensemester_kurzbz AND
											   ps.prestudent_id = studd.prestudent_id
						 WHERE ps.bismelden = TRUE
						   AND stg.melderelevant = TRUE
						   AND pss.studiensemester_kurzbz = ?
						   AND (EXISTS (SELECT 1 FROM sync.tbl_dvuh_zahlungen zlg /* charge sent */
										JOIN public.tbl_konto kto USING (buchungsnr)
										WHERE kto.person_id = ps.person_id
										AND kto.studiensemester_kurzbz = pss.studiensemester_kurzbz
										AND zlg.betrag <= 0
										LIMIT 1)
						            /*exception: Abbrecher, Unterbrecher might not need to pay*/
								OR pss.status_kurzbz IN ('Abbrecher', 'Unterbrecher', 'Absolvent')
						   )";

		if (isset($this->_status_kurzbz[self::JOB_TYPE_SEND_STUDY_DATA]))
		{
			$qry .= " AND pss.status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_SEND_STUDY_DATA];
		}

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		$qry .= 		" GROUP BY ps.prestudent_id, pss.studiensemester_kurzbz, ps.insertamum, pss.insertamum, mob.insertamum, bisio.insertamum,
							 ps.updateamum, pss.updateamum, ps.updateamum, pss.updateamum, mob.updateamum, bisio.updateamum, bisio.bis, pss.datum
					 ) prestudents
					WHERE max_studiumdaten_meldedatum IS NULL /* either not sent to DVUH or data modified since last send*/
					OR prestudent_status_datum = NOW() /* if prestudent status gets active today */
					OR bisio_endedatum = CURRENT_DATE /* if bisio ende is today, mobilitaeten in future are sent with no endedatum */
					OR pss_insertamum >= max_studiumdaten_meldedatum OR ps_insertamum >= max_studiumdaten_meldedatum
					OR mob_insertamum >= max_studiumdaten_meldedatum OR bisio_insertamum >= max_studiumdaten_meldedatum
					OR pss_updateamum >= max_studiumdaten_meldedatum OR ps_updateamum >= max_studiumdaten_meldedatum
					OR mob_updateamum >= max_studiumdaten_meldedatum OR bisio_updateamum >= max_studiumdaten_meldedatum";

		$dbModel = new DB_Model();

		$stToSyncResult = $dbModel->execReadOnlyQuery(
			$qry,
			$params
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($stToSyncResult)) return $stToSyncResult;

		// If students are present
		if (hasData($stToSyncResult))
		{
			$jobInput = json_encode(getData($stToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}

	/**
	 * Gets students for input of sendPruefungsaktivitaeten job.
	 * Students which have different ects sums than sent last.
	 * Students with 0 ects which have never been sent to DVUH are ignored (nothing to declare).
	 * @param string $studiensemester_kurzbz prestudentstatus and Noten are retrieved for this semester
	 * @return object students (person Ids)
	 */
	public function sendPruefungsaktivitaeten($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;

		$params = array(
			$studiensemester_kurzbz,
			$this->_angerechnet_note,
			$this->_angerechnet_note,
			$studiensemester_kurzbz
		);

		$qry = "SELECT DISTINCT person_id, ? AS studiensemester_kurzbz FROM (
					SELECT person_id, ps.prestudent_id, COALESCE(SUM(ects_angerechnet), 0) AS ects_angerechnet,
						   COALESCE(SUM(ects_erworben), 0) AS ects_erworben
					FROM public.tbl_prestudent ps
					JOIN public.tbl_studiengang stg  USING(studiengang_kz)
					LEFT JOIN (
						SELECT prestudent_id, CASE WHEN note.note = ? THEN ects ELSE 0 END AS ects_angerechnet,
							CASE WHEN note.note <> ? THEN ects ELSE 0 END AS ects_erworben
						FROM public.tbl_student
						LEFT JOIN lehre.tbl_zeugnisnote zgnisnote USING(student_uid)
						LEFT JOIN lehre.tbl_note note ON zgnisnote.note = note.note
						LEFT JOIN lehre.tbl_lehrveranstaltung lv USING (lehrveranstaltung_id)
						WHERE note.aktiv
						AND note.offiziell
						AND note.positiv
						AND zgnisnote.studiensemester_kurzbz = ?
					) no_ects ON ps.prestudent_id = no_ects.prestudent_id
					WHERE ps.bismelden = TRUE
					AND stg.melderelevant = TRUE";

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		$qry .= " GROUP BY person_id, ps.prestudent_id
				) ps_sum_ects
				WHERE EXISTS (
					SELECT 1 FROM public.tbl_prestudentstatus
					WHERE prestudent_id = ps_sum_ects.prestudent_id
					AND studiensemester_kurzbz = ?";

		$params[] = $studiensemester_kurzbz;

		if (isset($this->_status_kurzbz[self::JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN]))
		{
			$qry .= " AND status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN];
		}

		$qry .= ")
				AND (ects_angerechnet <> /* different ects sums sent last time */
						(SELECT COALESCE(SUM(last_ects_ar), 0) FROM
							(SELECT ects_angerechnet as last_ects_ar
								FROM sync.tbl_dvuh_pruefungsaktivitaeten
								WHERE prestudent_id = ps_sum_ects.prestudent_id
								AND studiensemester_kurzbz = ?
								ORDER BY meldedatum DESC, insertamum DESC
								LIMIT 1) last_ects_ar
						)
					OR ects_erworben <>
						(SELECT COALESCE(SUM(last_ects_er), 0) FROM
							(SELECT ects_erworben as last_ects_er
								FROM sync.tbl_dvuh_pruefungsaktivitaeten
								WHERE prestudent_id = ps_sum_ects.prestudent_id
								AND studiensemester_kurzbz = ?
								ORDER BY meldedatum DESC, insertamum DESC
								LIMIT 1) last_ects_er
						)
				)";

		$params = array_merge($params, array($studiensemester_kurzbz, $studiensemester_kurzbz));

		$dbModel = new DB_Model();

		$stToSyncResult = $dbModel->execReadOnlyQuery(
			$qry,
			$params
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($stToSyncResult)) return $stToSyncResult;

		// If students are present
		if (hasData($stToSyncResult))
		{
			$jobInput = json_encode(getData($stToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}
}
