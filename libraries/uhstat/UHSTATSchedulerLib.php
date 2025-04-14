<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library that contains the logic to generate new jobs
 */
class UHSTATSchedulerLib
{
	private $_ci; // Code igniter instance
	private $_status_kurzbz = array(); // contains prestudentstatus to retrieve for each jobtype

	const JOB_TYPE_UHSTAT1 = 'DVUHUHSTAT1';
	const JOB_TYPE_UHSTAT2 = 'DVUHUHSTAT2';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-DVUH/UHSTATSync'); // load sync config

		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper'); // load helper

		// set config items
		$this->_status_kurzbz = $this->_ci->config->item('fhc_uhstat_status_kurzbz');
		$this->_terminated_student_status_kurzbz = $this->_ci->config->item('fhc_uhstat_terminated_student_status_kurzbz');
				$studiensemesterMeldezeitraum = $this->_ci->config->item('fhc_uhstat_studiensemester_meldezeitraum');

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
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets students for input of UHSTAT1 job.
	 * @return object students
	 */
	public function sendUHSTAT1()
	{
		$jobInput = null;

		if (!isset($this->_status_kurzbz[self::JOB_TYPE_UHSTAT1]) || isEmptyArray($this->_status_kurzbz[self::JOB_TYPE_UHSTAT1]))
			return error("Kein status angegeben");

		$params = array($this->_status_kurzbz[self::JOB_TYPE_UHSTAT1]);

		// get students not sent to BIS yet
		$qry = "SELECT
					DISTINCT person_id
				FROM
					public.tbl_prestudent ps
					JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
					JOIN public.tbl_studiengang stg on ps.studiengang_kz = stg.studiengang_kz
					JOIN bis.tbl_uhstat1daten uhstat_daten USING (person_id)
				WHERE
					status_kurzbz IN ?
					AND ps.bismelden
					AND stg.melderelevant
					-- application is sent
					-- AND pss.bewerbung_abgeschicktamum IS NOT NULL
					-- data not sent yet or updated
					AND NOT EXISTS (
						SELECT 1
						FROM
							sync.tbl_bis_uhstat1
						WHERE
							(gemeldetamum > uhstat_daten.updateamum OR uhstat_daten.updateamum IS NULL)
							AND uhstat1daten_id = uhstat_daten.uhstat1daten_id
					)";

		if (isset($this->_terminated_student_status_kurzbz))
		{
			$qry .= "
				AND NOT EXISTS (
					SELECT 1
					FROM
						public.tbl_prestudentstatus
					WHERE
						prestudent_id = ps.prestudent_id
						AND status_kurzbz IN ?
				)";
			$params[] = $this->_terminated_student_status_kurzbz;
		}

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

		return success($jobInput);
	}

	/**
	 * Gets students for input of UHSTAT2 job.
	 * @return object students
	 */
	public function sendUHSTAT2($studiensemester_kurzbz)
	{
		$jobInput = null;

		$studiensemester_kurzbz_arr = $this->_getStudiensemester($studiensemester_kurzbz);

		if (isEmptyArray($studiensemester_kurzbz_arr))
			return error("Kein Studiensemester angegeben");

		if (!isset($this->_status_kurzbz[self::JOB_TYPE_UHSTAT2]) || isEmptyArray($this->_status_kurzbz[self::JOB_TYPE_UHSTAT2]))
			return error("Kein status angegeben");

		$params = array($studiensemester_kurzbz_arr, $this->_status_kurzbz[self::JOB_TYPE_UHSTAT2]);

		// get students not sent to BIS yet
		$qry = "SELECT
					DISTINCT ps.prestudent_Id
				FROM
					public.tbl_prestudent ps
					JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
					JOIN public.tbl_studiengang stg on ps.studiengang_kz = stg.studiengang_kz
					JOIN public.tbl_student stud USING (prestudent_id)
					JOIN bis.tbl_bisio bisio ON stud.student_uid = bisio.student_uid
				WHERE
					pss.studiensemester_kurzbz IN ?
					AND status_kurzbz IN ?
					AND ps.bismelden
					AND stg.melderelevant
					-- data not sent yet or updated
					AND NOT EXISTS (
						SELECT 1
						FROM
							sync.tbl_bis_uhstat2
						WHERE
							prestudent_id = ps.prestudent_id
							AND gemeldetamum > bisio.updateamum OR bisio.updateamum IS NULL
					)";

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

		return success($jobInput);
	}

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
