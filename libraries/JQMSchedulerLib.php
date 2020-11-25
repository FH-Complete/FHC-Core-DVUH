<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance

	const JOB_TYPE_REQUEST_MATRIKELNUMMER = 'DVUHRequestMatrikelnummer';
	const JOB_TYPE_SEND_CHARGE = 'DVUHSendCharge';
	const JOB_TYPE_SEND_PAYMENT = 'DVUHSendPayment';
	const JOB_TYPE_SEND_STUDENT_DATA = 'DVUHSendStudentData';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

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
			$qry = "SELECT DISTINCT person_id FROM public.tbl_person pers
                    JOIN public.tbl_prestudent ps USING (person_id)
					JOIN public.tbl_student using(prestudent_id)
					JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				WHERE ps.bismelden = true
					AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
					AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
					AND pers.matr_nr IS NULL
					AND pss.studiensemester_kurzbz = ?
				   ";

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

	public function sendCharge()
	{
		$jobInput = null;
		$result = null;

		// get current semester
		$studiensemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();

		if (hasData($studiensemesterResult))
		{
			$studiensemester = getData($studiensemesterResult)[0]->studiensemester_kurzbz;

			// get students with outstanding Buchungen not sent to DVUH yet
			$qry = "SELECT DISTINCT person_id FROM public.tbl_person pers
                    JOIN public.tbl_prestudent ps USING (person_id)
					JOIN public.tbl_student using(prestudent_id)
					JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
    				JOIN public.tbl_konto kto USING(person_id)
					LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				WHERE ps.bismelden = true
					AND stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0
					AND pss.status_kurzbz IN ('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
					AND kto.buchungsnr_verweis IS NULL
					AND kto.betrag < 0
					AND NOT EXISTS (SELECT 1 FROM public.tbl_konto ggb 
								  	WHERE ggb.person_id = kto.person_id
								    AND ggb.buchungsnr_verweis = kto.buchungsnr
								    LIMIT 1)
				  	AND NOT EXISTS (SELECT 1 from sync.tbl_dvuh_zahlungen
				  	    			WHERE buchungsnr = kto.buchungsnr
				  					AND betrag < 0
				  	    			LIMIT 1)				
					AND pss.studiensemester_kurzbz = ?
				   ";

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
}
