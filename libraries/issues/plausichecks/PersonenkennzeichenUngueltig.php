<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class PersonenkennzeichenUngueltig extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$student_uid = isset($params['student_uid']) ? $params['student_uid'] : null;

		// get all students failing the plausicheck
		$personRes = $this->_getPersons($studiensemester_kurzbz, $studiengang_kz, $student_uid, $exkludierte_studiengang_kz);

		if (isError($personRes)) return $personRes;

		if (hasData($personRes))
		{
			$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

			$persons = getData($personRes);

			// populate results with data necessary for writing issues
			foreach ($persons as $person)
			{
				// call method for checking ersatzkennzeichen
				$personenkennzeichenCheck = $this->_ci->dvuhcheckinglib->checkPersonenkennzeichen(trim($person->personenkennzeichen));

				// if check failed, produce issue
				if (!$personenkennzeichenCheck)
				{
					$results[] = array(
						'person_id' => $person->person_id,
						'fehlertext_params' => array('personenkennzeichen' => $person->personenkennzeichen),
						'resolution_params' => array('student_uid' => $person->student_uid)
					);
				}
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Invalid Personenkennzeichen.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param student_uid int if check is to be executed only for one address
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	public function _getPersons(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$student_uid = null,
		$exkludierte_studiengang_kz = null
	) {
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendCharge'] ?? null;

		$params = array();

		$qry = "
			SELECT
				DISTINCT person_id, stud.matrikelnr AS personenkennzeichen, stud.student_uid
			FROM
				public.tbl_prestudent pre
				JOIN public.tbl_student stud USING (prestudent_id)
				JOIN public.tbl_person pers USING(person_id)
				JOIN public.tbl_prestudentstatus status USING(prestudent_id)
				JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
			WHERE
				stud.matrikelnr IS NOT NULL
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

		if (isset($student_uid))
		{
			$qry .= " AND stud.student_uid = ?";
			$params[] = $student_uid;
		}

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
