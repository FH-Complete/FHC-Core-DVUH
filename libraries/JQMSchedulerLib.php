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

	public function requestMatrikelnummer()
	{
		$jobInput = null;
		$result = null;

		// get current semester
		$studiensemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();

		if (hasData($studiensemesterResult))
		{
			$studiensemester = getData($studiensemesterResult)[0]->studiensemester_kurzbz;

			// get students with no Matrikelnr
			$qry = "SELECT DISTINCT person_id, pss.studiensemester_kurzbz
					FROM public.tbl_person pers
						JOIN public.tbl_prestudent ps USING (person_id)
						JOIN public.tbl_student using(prestudent_id)
						JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
						LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
					WHERE ps.bismelden = true
						AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
						AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
						AND pers.matr_nr IS NULL
						AND pss.studiensemester_kurzbz = ?";

			$dbModel = new DB_Model();

			$maToSyncResult = $dbModel->execReadOnlyQuery(
				$qry, array($studiensemester)
			);

			// If error occurred while retrieving students from database then return the error
			if (isError($maToSyncResult)) return $maToSyncResult;

			// If students are present
			if (hasData($maToSyncResult))
			{
				$jobInput = json_encode(getData($maToSyncResult));
			}

			$result = success($jobInput);
		}
		else
			$result = error('No Studiensemester found');

		return $result;
	}

/*	public function sendMasterData()
	{
		$jobInput = null;
		$result = null;

		// person, adresse, kontakt

		// get students whose master data changed
		$qry = "SELECT DISTINCT person_id, pss.studiensemester_kurzbz
				FROM public.tbl_person pers
					JOIN public.tbl_prestudent ps USING (person_id)
					JOIN public.tbl_prestudentstatus pss ON ps.prestudent_id = pss.prestudent_id AND pss.studiensemester_kurzbz = kto.studiensemester_kurzbz
					JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
					JOIN public.tbl_student using(prestudent_id)
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
					LEFT JOIN public.tbl_kontakt ktkt on pers.person_id = ktkt.person_id
					LEFT JOIN public.tbl_adresse adr on pers.person_id = adr.person_id
				WHERE ps.bismelden = true
					AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
					AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
				  	AND pers.insertamum > ? OR ktkt.insertamum > ? OR adr.insertamum > ?
						OR pers.updateamum > ? OR ktkt.updateamum > ? OR adr.updateamum > ?
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
	}*/

	public function sendCharge()
	{
		$jobInput = null;
		$result = null;

		/*$studiensemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();

		if (hasData($studiensemesterResult))
		{
			$studiensemester = getData($studiensemesterResult)[0]->studiensemester_kurzbz;*/

		// TODO sync when updateamum changes

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
					AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
					AND kto.buchungstyp_kurzbz IN ('Studiengebuehr', 'OEH')
					AND kto.buchungsnr_verweis IS NULL
					AND kto.betrag < 0
					AND NOT EXISTS (SELECT 1 FROM public.tbl_konto ggb /* no Gegenbuchung yet */
									WHERE ggb.person_id = kto.person_id
									AND ggb.buchungsnr_verweis = kto.buchungsnr
									LIMIT 1)
					AND NOT EXISTS (SELECT 1 from sync.tbl_dvuh_zahlungen /* not yet sent to DVUH */
									WHERE buchungsnr = kto.buchungsnr
									AND betrag < 0
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
					AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
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

	public function sendStudyData($lastJobTime)
	{
		$jobInput = null;
		$result = null;

		// TODO sync when updateamum changes

		$params = array($this->_startdatum);

		// get students with vorschreibung which have no Studiumsmeldung or have a data change
		// data change: prestudent, prestudentstatus, bisio, mobilitaet
		$qry = "SELECT DISTINCT ps.prestudent_id, pss.studiensemester_kurzbz
				FROM public.tbl_prestudent ps
					JOIN public.tbl_student using(prestudent_id)
					JOIN public.tbl_prestudentstatus pss ON ps.prestudent_id = pss.prestudent_id
					JOIN public.tbl_studiensemester USING(studiensemester_kurzbz)
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
					LEFT JOIN bis.tbl_bisio bisio ON tbl_student.student_uid = bisio.student_uid
					LEFT JOIN bis.tbl_mobilitaet mob ON ps.prestudent_id = mob.prestudent_id
				WHERE ps.bismelden = true
					AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
					AND pss.status_kurzbz IN ('Student', 'Incoming', 'Diplomand')
					AND tbl_studiensemester.ende >= ?::date
					AND EXISTS (SELECT 1 FROM sync.tbl_dvuh_zahlungen zlg /* Vorschreibung sent */
									JOIN public.tbl_konto kto USING (buchungsnr)
									WHERE kto.person_id = ps.person_id
									AND kto.studiensemester_kurzbz = pss.studiensemester_kurzbz
									AND zlg.betrag < 0
									LIMIT 1)
					AND (
							NOT EXISTS (SELECT 1 FROM sync.tbl_dvuh_studiumdaten /* no studiumsmeldung yet */
										WHERE prestudent_id = ps.prestudent_id AND studiensemester_kurzbz = pss.studiensemester_kurzbz)";

		if (isset($lastJobTime))
		{
			$qry .= " OR pss.insertamum > ? OR ps.insertamum > ? OR mob.insertamum > ? OR bisio.insertamum > ? /* modified */
						OR pss.updateamum > ? OR ps.updateamum > ? OR mob.updateamum > ? OR bisio.updateamum > ?";
			$params = array_merge($params, array_pad(array(), 8, $lastJobTime));
		}

		$qry .= 	 ")";

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