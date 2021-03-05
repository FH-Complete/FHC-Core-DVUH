<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance
	private $_startdatum; // lower threshold date for getting data

	const JOB_TYPE_REQUEST_MATRIKELNUMMER = 'DVUHRequestMatrikelnummer';
	const JOB_TYPE_SEND_CHARGE = 'DVUHSendCharge';
	const JOB_TYPE_SEND_PAYMENT = 'DVUHSendPayment';
	const JOB_TYPE_SEND_STUDY_DATA = 'DVUHSendStudyData';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$this->_startdatum = $this->_ci->config->item('fhc_dvuh_sync_startdatum');

		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets students for input of requestMatrikelnummer job.
	 * @param $studiensemester_kurzbz string semester for which Matrikelnr should be requested and Stammdaten should be sent
	 * @return object students
	 */
	public function requestMatrikelnummer($studiensemester_kurzbz)
	{
		$jobInput = null;
		$result = null;

		// get students with no Matrikelnr
		$qry = "SELECT DISTINCT person_id, pss.studiensemester_kurzbz
				FROM public.tbl_person pers
					JOIN public.tbl_prestudent ps USING (person_id)
					JOIN public.tbl_student USING(prestudent_id)
					JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				WHERE ps.bismelden = true
					AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
					AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
					AND pers.matr_nr IS NULL
					AND pss.studiensemester_kurzbz = ?";

		$dbModel = new DB_Model();

		$maToSyncResult = $dbModel->execReadOnlyQuery(
			$qry, array($studiensemester_kurzbz)
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($maToSyncResult)) return $maToSyncResult;

		// If students are present
		if (hasData($maToSyncResult))
		{
			$jobInput = json_encode(getData($maToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}

	/**
	 * Gets students for input of sendCharge job.
	 * @param $lastJobTime string start date of last job
	 * @return object students
	 */
	public function sendCharge()
	{
		$jobInput = null;
		$result = null;

		$params = array($this->_startdatum);

		// get students not sent to DVUH yet
		$qry = "SELECT DISTINCT persons.person_id, persons.studiensemester_kurzbz FROM (
					SELECT pers.person_id, pss.studiensemester_kurzbz, max(stammd.meldedatum) AS max_meldedatum, max(zlg.buchungsdatum) AS max_zlg_buchungsdatum,
					   pers.insertamum AS person_insertamum, pers.updateamum AS person_updateamum,
					   kto.insertamum AS kto_insertamum, kto.updateamum AS kto_updateamum, kto.buchungsnr
					FROM public.tbl_person pers
						JOIN public.tbl_prestudent ps USING (person_id)
						JOIN public.tbl_student USING (prestudent_id)
						JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
						JOIN public.tbl_studiensemester USING (studiensemester_kurzbz)
						LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
						LEFT JOIN public.tbl_konto kto ON pers.person_id = kto.person_id AND kto.buchungstyp_kurzbz IN ('Studiengebuehr','OEH')
														AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz AND kto.buchungsnr_verweis IS NULL
														AND kto.betrag < 0
						LEFT JOIN sync.tbl_dvuh_stammdaten stammd ON pss.studiensemester_kurzbz = stammd.studiensemester_kurzbz AND pers.person_id = stammd.person_id
						LEFT JOIN sync.tbl_dvuh_zahlungen zlg ON kto.buchungsnr = zlg.buchungsnr
						WHERE ps.bismelden = true
						AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
						AND tbl_studiensemester.ende >= ?
						GROUP BY pers.person_id, pss.studiensemester_kurzbz, kto.buchungsnr, kto_insertamum, kto_updateamum
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
				  (max_zlg_buchungsdatum IS NULL AND buchungsnr IS NOT NULL )  /* vorschreibung not sent to DVUH yet */
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
	 * @return object students
	 */
	public function sendPayment()
	{
		$jobInput = null;
		$result = null;

		// get students with outstanding Buchungen not sent to DVUH yet
		$qry = "SELECT DISTINCT person_id, kto.studiensemester_kurzbz
				FROM public.tbl_person pers
					JOIN public.tbl_konto kto USING(person_id)
					JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
					JOIN public.tbl_prestudent ps USING (person_id)
					JOIN public.tbl_student using(prestudent_id)
					JOIN public.tbl_prestudentstatus pss ON ps.prestudent_id = pss.prestudent_id AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				WHERE ps.bismelden = true
					AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
					AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent')
					AND kto.buchungstyp_kurzbz IN ('Studiengebuehr', 'OEH')
					AND kto.buchungsnr_verweis IS NOT NULL
					AND kto.betrag > 0
					AND NOT EXISTS (SELECT 1 from sync.tbl_dvuh_zahlungen /* payment not yet sent to DVUH */
									WHERE buchungsnr = kto.buchungsnr
									AND betrag > 0
									LIMIT 1)
					AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz
					AND tbl_studiensemester.ende >= ?::date";

		$dbModel = new DB_Model();

		$maToSyncResult = $dbModel->execReadOnlyQuery(
			$qry,
			array($this->_startdatum)
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($maToSyncResult)) return $maToSyncResult;

		// If students are present
		if (hasData($maToSyncResult))
		{
			$jobInput = json_encode(getData($maToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}

	/**
	 * Gets students for input of sendStudyData job.
	 * @param $lastJobTime string start date of last job
	 * @return object students
	 */
	public function sendStudyData()
	{
		$jobInput = null;
		$result = null;

		$params = array($this->_startdatum);

		// get students with vorschreibung which have no Studiumsmeldung or have a data change
		// data change: prestudent, prestudentstatus, bisio, mobilitaet
		$qry = "SELECT DISTINCT prestudents.prestudent_id, prestudents.studiensemester_kurzbz
				FROM (
						 SELECT ps.prestudent_id, pss.studiensemester_kurzbz,
								ps.insertamum AS ps_insertamum, pss.insertamum AS pss_insertamum, mob.insertamum as mob_insertamum, bisio.insertamum AS bisio_insertamum, 
								ps.updateamum AS ps_updateamum, pss.updateamum AS pss_updateamum, mob.updateamum AS mob_updateamum, bisio.updateamum AS bisio_updateamum,
								max(studd.meldedatum) AS max_studiumdaten_meldedatum
						 FROM public.tbl_prestudent ps
								  JOIN public.tbl_student using (prestudent_id)
								  JOIN public.tbl_prestudentstatus pss ON ps.prestudent_id = pss.prestudent_id
								  JOIN public.tbl_studiensemester USING (studiensemester_kurzbz)
								  LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
								  LEFT JOIN bis.tbl_bisio bisio ON tbl_student.student_uid = bisio.student_uid
								  LEFT JOIN bis.tbl_mobilitaet mob ON ps.prestudent_id = mob.prestudent_id
								  LEFT JOIN sync.tbl_dvuh_studiumdaten studd
											ON pss.studiensemester_kurzbz = studd.studiensemester_kurzbz AND
											   ps.prestudent_id = studd.prestudent_id
						 WHERE ps.bismelden = true
						   AND stg.studiengang_kz < 10000
						   AND stg.studiengang_kz <> 0
						   AND pss.status_kurzbz IN ('Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent')
						   AND tbl_studiensemester.ende >= ?::date
						   AND EXISTS (SELECT 1 FROM sync.tbl_dvuh_zahlungen zlg /* charge sent */
										JOIN public.tbl_konto kto USING (buchungsnr)
										WHERE kto.person_id = ps.person_id
										AND kto.studiensemester_kurzbz = pss.studiensemester_kurzbz
										AND zlg.betrag < 0
										LIMIT 1)
						   GROUP BY ps.prestudent_id, pss.studiensemester_kurzbz, ps.insertamum, pss.insertamum, mob.insertamum, bisio.insertamum,
							 ps.updateamum, pss.updateamum, ps.updateamum, pss.updateamum, mob.updateamum, bisio.updateamum
					 ) prestudents
					WHERE max_studiumdaten_meldedatum IS NULL /* either not sent to DVUH or data modified since last send*/
					OR pss_insertamum >= max_studiumdaten_meldedatum OR ps_insertamum >= max_studiumdaten_meldedatum
					OR mob_insertamum >= max_studiumdaten_meldedatum OR bisio_insertamum >= max_studiumdaten_meldedatum
					OR pss_updateamum >= max_studiumdaten_meldedatum OR ps_updateamum >= max_studiumdaten_meldedatum
					OR mob_updateamum >= max_studiumdaten_meldedatum OR bisio_updateamum >= max_studiumdaten_meldedatum";

		$dbModel = new DB_Model();

		$maToSyncResult = $dbModel->execReadOnlyQuery(
			$qry,
			$params
		);

		// If error occurred while retrieving students from database then return the error
		if (isError($maToSyncResult)) return $maToSyncResult;

		// If students are present
		if (hasData($maToSyncResult))
		{
			$jobInput = json_encode(getData($maToSyncResult));
		}

		$result = success($jobInput);

		return $result;
	}
}
