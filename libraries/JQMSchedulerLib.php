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
	private $_angerechnet_note = array(); // oe_kurzbz - only students assigned to one of descendants of this oe are retrieved
	private $_studiensemester = array(); // default Studiensemster for which data is sent

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

		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper'); // load helper

		// set config items
		$this->_status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz');
		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$this->_buchungstypen = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);
		$oe_kurzbz = $this->_ci->config->item('fhc_dvuh_oe_kurzbz');
		$this->_angerechnet_note = $this->_ci->config->item('fhc_dvuh_sync_note_angerechnet');
		$studiensemesterMeldezeitraum = $this->_ci->config->item('fhc_dvuh_studiensemester_meldezeitraum');

		// get default Studiensemester from config
		$today = new DateTime(date('Y-m-d'));

		foreach ($studiensemesterMeldezeitraum as $studiensemester_kurzbz => $meldezeitraum)
		{
			if (validateDate($meldezeitraum['von']) && validateDate($meldezeitraum['bis'])
				&& $today >= new DateTime($meldezeitraum['von']) && $today <= new DateTime($meldezeitraum['bis']))
			{
				$this->_studiensemester[] = $studiensemester_kurzbz;
			}
		}

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

		$studiensemester_kurzbz_arr = $this->_getStudiensemester($studiensemester_kurzbz);

		if (isEmptyArray($studiensemester_kurzbz_arr))
			return error("Kein Studiensemester angegeben");

		$params = array($studiensemester_kurzbz_arr);

		// get students with no Matrikelnr
		$qry = "SELECT person_id, studiensemester_kurzbz FROM (
					SELECT DISTINCT person_id, pss.studiensemester_kurzbz, sem.start
					FROM public.tbl_person pers
						JOIN public.tbl_prestudent ps USING (person_id)
						JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
						JOIN public.tbl_studiensemester sem USING (studiensemester_kurzbz)
						LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
					WHERE ps.bismelden = TRUE
						AND stg.melderelevant = TRUE
						/* matr_aktiv = false: old Matrikelnummer not yet activated might need to be replaced with new */
						AND (pers.matr_nr IS NULL OR matr_aktiv = FALSE)
						AND pss.studiensemester_kurzbz IN ?";

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

		$qry .= ") persons";
		$qry .= " ORDER BY start, person_id";

		$dbModel = new DB_Model();

		$stToSyncResult = $dbModel->execReadOnlyQuery($qry, $params);

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
	 * Gets students for input of requestBpk job.
	 * @param string $studiensemester_kurzbz semester for which Bpk should be requested
	 * @return object students
	 */
	public function requestBpk($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;

		$studiensemester_kurzbz_arr = $this->_getStudiensemester($studiensemester_kurzbz);

		if (isEmptyArray($studiensemester_kurzbz_arr))
			return error("Kein Studiensemester angegeben");

		$params = array($studiensemester_kurzbz_arr);

		// get students with no BPK
		$qry = "SELECT DISTINCT person_id
				FROM public.tbl_person
					JOIN public.tbl_prestudent USING (person_id)
					JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
					JOIN public.tbl_studiengang stg USING (studiengang_kz)
				WHERE (tbl_person.bpk IS NULL OR tbl_person.bpk = '')
					AND stg.melderelevant = TRUE
					AND bismelden = TRUE
					AND pss.studiensemester_kurzbz IN ?";

		if (isset($this->_status_kurzbz[self::JOB_TYPE_REQUEST_BPK]))
		{
			$qry .= " AND pss.status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_REQUEST_BPK];
		}

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		$qry .= " ORDER BY person_id";

		$dbModel = new DB_Model();

		$stToSyncResult = $dbModel->execReadOnlyQuery($qry, $params);

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

		$studiensemester_kurzbz_arr = $this->_getStudiensemester($studiensemester_kurzbz);

		if (isEmptyArray($studiensemester_kurzbz_arr))
			return error("Kein Studiensemester angegeben");

		$params = array($this->_buchungstypen, $studiensemester_kurzbz_arr);

		// get students not sent to DVUH yet
		$qry = "SELECT person_id, studiensemester_kurzbz FROM (
					SELECT DISTINCT persons.person_id, persons.studiensemester_kurzbz, sem.start FROM (
						SELECT pers.person_id, pss.studiensemester_kurzbz,
							max(stammd.meldedatum) AS max_meldedatum, max(zlg.buchungsdatum) AS max_zlg_buchungsdatum,
							pers.insertamum AS person_insertamum, pers.updateamum AS person_updateamum,
							kto.insertamum AS kto_insertamum, kto.updateamum AS kto_updateamum, kto.buchungsnr
						FROM public.tbl_person pers
						JOIN public.tbl_prestudent ps USING (person_id)
						JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
						LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
						LEFT JOIN public.tbl_konto kto ON pers.person_id = kto.person_id AND kto.buchungstyp_kurzbz IN ?
														AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz AND kto.buchungsnr_verweis IS NULL
														AND kto.betrag <= 0
						LEFT JOIN sync.tbl_dvuh_stammdaten stammd ON
							pss.studiensemester_kurzbz = stammd.studiensemester_kurzbz AND pers.person_id = stammd.person_id
						LEFT JOIN sync.tbl_dvuh_zahlungen zlg ON kto.buchungsnr = zlg.buchungsnr
						WHERE ps.bismelden = TRUE
						AND stg.melderelevant = TRUE
						AND NOT ( /* if Abgewiesener last status and matr_nr NULL - rejected before study start, do not send to DVUH */
							EXISTS (
								SELECT 1 FROM public.tbl_prestudentstatus
								WHERE prestudent_id = ps.prestudent_id
								AND status_kurzbz = 'Abgewiesener'
								ORDER BY datum DESC, tbl_prestudentstatus.insertamum DESC NULLS LAST
								LIMIT 1
							)
							AND pers.matr_nr IS NULL
						)
						AND pss.studiensemester_kurzbz IN ?";

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
					LEFT JOIN ( /* add date when a kontakt was last modified */
						SELECT person_id, MAX(updateamum) AS updateamum, MAX(insertamum) AS insertamum
						FROM public.tbl_kontakt
						GROUP BY person_id
					) AS ktkt ON persons.person_id = ktkt.person_id
					LEFT JOIN ( /* add date when an adresse was last modified */
						SELECT person_id, MAX(updateamum) AS updateamum, MAX(insertamum) AS insertamum
						FROM public.tbl_adresse
						GROUP BY person_id
					) AS adr ON persons.person_id = adr.person_id
					JOIN public.tbl_studiensemester sem ON persons.studiensemester_kurzbz = sem.studiensemester_kurzbz
					WHERE max_meldedatum IS NULL /* stammdaten not sent to DVUH yet */
					OR
					  (max_zlg_buchungsdatum IS NULL AND buchungsnr IS NOT NULL)  /* vorschreibung not sent to DVUH yet */
					OR
					  (persons.person_insertamum >= max_meldedatum OR ktkt.insertamum >= max_meldedatum /* modified since last sent to DVUH*/
						OR adr.insertamum >= max_meldedatum OR kto_insertamum >= max_meldedatum
						OR persons.person_updateamum >= max_meldedatum OR ktkt.updateamum >= max_meldedatum
						OR adr.updateamum >= max_meldedatum OR kto_updateamum >= max_meldedatum)
				) perssemlist
				ORDER BY start, person_id";

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

		$studiensemester_kurzbz_arr = $this->_getStudiensemester($studiensemester_kurzbz);

		if (isEmptyArray($studiensemester_kurzbz_arr))
			return error("Kein Studiensemester angegeben");

		$params = array($this->_buchungstypen, $studiensemester_kurzbz_arr);

		// get students with outstanding Buchungen not sent to DVUH yet
		$qry = "SELECT person_id, studiensemester_kurzbz FROM (
					SELECT DISTINCT person_id, kto.studiensemester_kurzbz, sem.start
					FROM public.tbl_person pers
						JOIN public.tbl_konto kto USING (person_id)
						JOIN public.tbl_prestudent ps USING (person_id)
						JOIN public.tbl_prestudentstatus pss ON
							ps.prestudent_id = pss.prestudent_id AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz
						LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
						JOIN public.tbl_studiensemester sem ON kto.studiensemester_kurzbz = sem.studiensemester_kurzbz
					WHERE ps.bismelden = TRUE
						AND stg.melderelevant = TRUE
						AND kto.buchungstyp_kurzbz IN ?
						AND kto.buchungsnr_verweis IS NOT NULL
						AND kto.betrag > 0
						AND NOT EXISTS (SELECT 1 from sync.tbl_dvuh_zahlungen /* payment not yet sent to DVUH */
										WHERE buchungsnr = kto.buchungsnr
										AND betrag > 0)
						AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz
						AND kto.studiensemester_kurzbz IN ?";

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

		$qry .= ") perssemlist";
		$qry .= " ORDER BY start, person_id";

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

		$studiensemester_kurzbz_arr = $this->_getStudiensemester($studiensemester_kurzbz);

		if (isEmptyArray($studiensemester_kurzbz_arr))
			return error("Kein Studiensemester angegeben");

		$params = array($studiensemester_kurzbz_arr);

		// get students with Vorschreibung which have no Studiumsmeldung or have a data change
		// data change: prestudent, prestudentstatus, bisio, mobilitaet
		$qry = "SELECT prestudent_id, studiensemester_kurzbz FROM (
					SELECT DISTINCT prestudents.prestudent_id, prestudents.studiensemester_kurzbz, sem.start, ist_abbrecher
					FROM (
							SELECT ps.prestudent_id, pss.studiensemester_kurzbz,
									ps.insertamum AS ps_insertamum, pss.insertamum AS pss_insertamum,
									mob.insertamum as mob_insertamum, bisio.insertamum AS bisio_insertamum,
									ps.updateamum AS ps_updateamum, pss.updateamum AS pss_updateamum,
									mob.updateamum AS mob_updateamum, bisio.updateamum AS bisio_updateamum,
									max(studd.meldedatum) AS max_studiumdaten_meldedatum, pss.datum AS prestudent_status_datum,
									bisio.bis AS bisio_endedatum, bisio.von AS bisio_startdatum,
									CASE
										WHEN EXISTS (SELECT 1
											FROM public.tbl_prestudentstatus
											WHERE prestudent_id = ps.prestudent_id
											/* Abbrecher have lower priority, active prestudents should be sent first to avoid Matrikelnr lock */
											AND status_kurzbz = 'Abbrecher')
										THEN 1
										ELSE 0
									END AS ist_abbrecher
							FROM public.tbl_prestudent ps
							JOIN public.tbl_student USING (prestudent_id)
							JOIN public.tbl_prestudentstatus pss ON ps.prestudent_id = pss.prestudent_id
							LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
							LEFT JOIN bis.tbl_bisio bisio ON tbl_student.student_uid = bisio.student_uid
							LEFT JOIN bis.tbl_mobilitaet mob ON ps.prestudent_id = mob.prestudent_id
							LEFT JOIN sync.tbl_dvuh_studiumdaten studd ON pss.studiensemester_kurzbz = studd.studiensemester_kurzbz
																			AND ps.prestudent_id = studd.prestudent_id
							WHERE ps.bismelden = TRUE
							AND stg.melderelevant = TRUE
							AND pss.studiensemester_kurzbz IN ?
							AND (EXISTS (SELECT 1 FROM sync.tbl_dvuh_zahlungen zlg /* charge sent */
											JOIN public.tbl_konto kto USING (buchungsnr)
											WHERE kto.person_id = ps.person_id
											AND kto.studiensemester_kurzbz = pss.studiensemester_kurzbz
											AND zlg.betrag <= 0
											LIMIT 1)
										/*exception: Abbrecher, Unterbrecher etc. might not need to pay*/
								OR pss.status_kurzbz IN ('Abbrecher', 'Unterbrecher', 'Diplomand', 'Absolvent')
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
							 ps.updateamum, pss.updateamum, ps.updateamum, pss.updateamum, mob.updateamum,
							 bisio.updateamum, bisio.von, bisio.bis, pss.datum
					) prestudents
					JOIN public.tbl_studiensemester sem USING (studiensemester_kurzbz)
					WHERE
					(
						(/* not sent to DVUH and no IO data or it's time to send the IO data */
							max_studiumdaten_meldedatum IS NULL
							AND (bisio_startdatum IS NULL OR bisio_startdatum <= NOW())
						)
						OR prestudent_status_datum = CURRENT_DATE /* if prestudent status gets active today */
						OR bisio_endedatum = CURRENT_DATE /* if bisio ende is today, because mobilitaeten in future are sent with no endedatum */
						/* data modified since last send */
						OR ps_insertamum >= max_studiumdaten_meldedatum
						OR mob_insertamum >= max_studiumdaten_meldedatum OR bisio_insertamum >= max_studiumdaten_meldedatum
						OR pss_updateamum >= max_studiumdaten_meldedatum OR ps_updateamum >= max_studiumdaten_meldedatum
						OR mob_updateamum >= max_studiumdaten_meldedatum OR bisio_updateamum >= max_studiumdaten_meldedatum
						OR EXISTS(SELECT 1 FROM public.tbl_prestudentstatus pssu
									WHERE prestudent_id = prestudents.prestudent_id
									AND (insertamum >= max_studiumdaten_meldedatum OR updateamum >= max_studiumdaten_meldedatum)
									AND studiensemester_kurzbz IN ?
						)
					)
					AND NOT EXISTS /* exclude already storniert */
					(
						SELECT 1 FROM (
							SELECT storniert
							FROM sync.tbl_dvuh_studiumdaten
							WHERE prestudent_id = prestudents.prestudent_id
							AND studiensemester_kurzbz = prestudents.studiensemester_kurzbz
							ORDER BY meldedatum DESC, insertamum DESC
							LIMIT 1
						) max_studiumsync
						WHERE storniert = TRUE
					)
				) prestudentssem
				ORDER BY start, ist_abbrecher, prestudent_id";

		$params[] = $studiensemester_kurzbz_arr;

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

		$studiensemester_kurzbz_arr = $this->_getStudiensemester($studiensemester_kurzbz);

		if (isEmptyArray($studiensemester_kurzbz_arr))
			return error("Kein Studiensemester angegeben");

		$params = array(
			$this->_angerechnet_note, // add note angerechnet type params
			$this->_angerechnet_note,
			$studiensemester_kurzbz_arr
		);

		$qry = "SELECT DISTINCT person_id, studiensemester_kurzbz
				FROM (
					SELECT prestudenten.studiensemester_kurzbz, person_id, prestudenten.prestudent_id,
						COALESCE(SUM(ects_angerechnet), 0) AS summe_ects_angerechnet,
						COALESCE(SUM(ects_erworben), 0) AS summe_ects_erworben
					FROM
					(
						SELECT DISTINCT pss.studiensemester_kurzbz, person_id, ps.prestudent_id, lehrveranstaltung_id,
						CASE WHEN note.aktiv AND note.offiziell AND note.positiv AND lv.zeugnis AND note.note IN ?
							THEN ects
							ELSE 0
						END AS ects_angerechnet,
						CASE WHEN note.aktiv AND note.offiziell AND note.positiv AND lv.zeugnis AND note.note NOT IN ?
							THEN ects
							ELSE 0
						END AS ects_erworben
						FROM public.tbl_prestudent ps
						JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
						JOIN public.tbl_studiengang stg USING (studiengang_kz)
						LEFT JOIN public.tbl_student USING (prestudent_id)
						LEFT JOIN lehre.tbl_zeugnisnote zgnisnote ON tbl_student.student_uid = zgnisnote.student_uid AND pss.studiensemester_kurzbz = zgnisnote.studiensemester_kurzbz
						LEFT JOIN lehre.tbl_note note ON zgnisnote.note = note.note
						LEFT JOIN lehre.tbl_lehrveranstaltung lv USING (lehrveranstaltung_id)
						WHERE ps.bismelden = TRUE
						AND stg.melderelevant = TRUE
						AND pss.studiensemester_kurzbz IN ?";

		if (!isEmptyArray($this->_oe_kurzbz))
		{
			$qry .= " AND stg.oe_kurzbz IN ?";
			$params[] = $this->_oe_kurzbz;
		}

		if (isset($this->_status_kurzbz[self::JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN]))
		{
			$qry .= " AND pss.status_kurzbz IN ?";
			$params[] = $this->_status_kurzbz[self::JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN];
		}

		$qry .= "
					) prestudenten
					GROUP BY studiensemester_kurzbz, person_id, prestudent_id
				) summen_ects
				WHERE (/*summe_ects_angerechnet <>
					(SELECT COALESCE(SUM(last_ects_ar), 0)
						FROM (SELECT ects_angerechnet as last_ects_ar
						FROM sync.tbl_dvuh_pruefungsaktivitaeten
						WHERE prestudent_id = summen_ects.prestudent_id
						AND studiensemester_kurzbz = summen_ects.studiensemester_kurzbz
						ORDER BY meldedatum DESC, insertamum DESC, pruefungsaktivitaeten_id DESC LIMIT 1) last_ects_ar
					) OR */
					summe_ects_erworben <> /* different ects sums sent last time */
					(SELECT COALESCE(SUM(last_ects_er), 0)
						FROM (SELECT ects_erworben as last_ects_er
						FROM sync.tbl_dvuh_pruefungsaktivitaeten
						WHERE prestudent_id = summen_ects.prestudent_id
						AND studiensemester_kurzbz = summen_ects.studiensemester_kurzbz
						ORDER BY meldedatum DESC, insertamum DESC, pruefungsaktivitaeten_id DESC LIMIT 1) last_ects_er
					)
				)";

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

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Gets Studiensemester in an array, uses given parameter if valid or from config array field.
	 * @param string $studiensemester_kurzbz
	 * @return array
	 */
	private function _getStudiensemester($studiensemester_kurzbz)
	{
		$studiensemester_kurzbz_arr = array();

		if (!isEmptyString($studiensemester_kurzbz))
			$studiensemester_kurzbz_arr[] = $studiensemester_kurzbz;
		elseif (!isEmptyArray($this->_studiensemester))
			$studiensemester_kurzbz_arr = $this->_studiensemester;

		return $studiensemester_kurzbz_arr;
	}
}
