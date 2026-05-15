<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class StudienkennunguniUngueltig extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$gsprogramm_id = isset($params['gsprogramm_id']) ? $params['gsprogramm_id'] : null;
		$person_id = isset($params['person_id']) ? $params['person_id'] : null;

		// get all students failing the plausicheck
		$studentRes = $this->_getStudents($studiensemester_kurzbz, $studiengang_kz, $gsprogramm_id, $person_id, $exkludierte_studiengang_kz);

		if (isError($studentRes)) return $studentRes;

		if (hasData($studentRes))
		{
			$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

			$students = getData($studentRes);

			// populate results with data necessary for writing issues
			foreach ($students as $student)
			{
				// call method for checking ersatzkennzeichen
				$ersatzkennzeichenCheck = $this->_ci->dvuhcheckinglib->checkStudienkennunguni($student->studienkennung_uni);

				// if check failed, produce issue
				if (!$ersatzkennzeichenCheck)
				{
					$results[] = array(
						'person_id' => $student->person_id,
						'fehlertext_params' => array('programm_code' => $student->programm_code),
						'resolution_params' => array('gsprogramm_id' => $student->gsprogramm_id)
					);
				}
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Invalid Studienkennung.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param gsprogramm_id int if check is to be executed only for one address
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	public function _getStudents(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$gsprogramm_id = null,
		$person_id = null,
		$exkludierte_studiengang_kz = null
	) {
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendStudyData'] ?? null;

		$params = array();

		$qry = "
			SELECT
				DISTINCT person_id, pre.prestudent_id, gs.studienkennung_uni, gs.gsprogramm_id, gs.programm_code
			FROM
				public.tbl_prestudent pre
				JOIN public.tbl_person pers USING(person_id)
				JOIN public.tbl_prestudentstatus status USING(prestudent_id)
				JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
				JOIN bis.tbl_mobilitaet mo ON pre.prestudent_id = mo.prestudent_id
				JOIN bis.tbl_gsprogramm gs USING(gsprogramm_id)
			WHERE
				studienkennung_uni IS NOT NULL
				AND stg.melderelevant
				AND pre.bismelden";

		if (isset($studiensemester_kurzbz))
		{
			$qry .= " AND status.studiensemester_kurzbz = ?";
			$params[] = $studiensemester_kurzbz;
		}

		if (isset($studiengang_kz))
		{
			$qry .= " AND stg.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
		{
			$qry .= " AND status.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}

		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND stg.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		if (isset($person_id))
		{
			$qry .= " AND pre.person_id = ?";
			$params[] = $person_id;
		}

		if (isset($gsprogramm_id))
		{
			$qry .= " AND pers.gsprogramm_id = ?";
			$params[] = $gsprogramm_id;
		}

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
