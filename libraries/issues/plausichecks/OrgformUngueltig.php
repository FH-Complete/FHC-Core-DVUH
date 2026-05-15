<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class OrgformUngueltig extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$prestudent_id = isset($params['prestudent_id']) ? $params['prestudent_id'] : null;
		$person_id = isset($params['person_id']) ? $params['person_id'] : null;

		// get all students failing the plausicheck
		$prestudentRes = $this->_getOrgformUngueltig(
			$studiensemester_kurzbz,
			$studiengang_kz,
			$prestudent_id,
			$person_id,
			$exkludierte_studiengang_kz
		);

		if (isError($prestudentRes)) return $prestudentRes;

		if (hasData($prestudentRes))
		{
			$prestudents = getData($prestudentRes);

			// populate results with data necessary for writing issues
			foreach ($prestudents as $prestudent)
			{
				$results[] = array(
					'person_id' => $prestudent->person_id,
					//'fehlertext_params' => array('prestudent_id' => $prestudent->prestudent_id),
					'resolution_params' => array('prestudent_id' => $prestudent->prestudent_id)
				);
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Invalid Orgform.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param prestudent_id int if check is to be executed only for one prestudent
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	private function _getOrgformUngueltig(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$prestudent_id = null,
		$person_id = null,
		$exkludierte_studiengang_kz = null
	) {

		$params = array();

		// get prestudent status for send study data job from config
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendStudyData'] ?? null;

		$studiensemester_clause = '';
		if (isset($studiensemester_kurzbz))
		{
			$studiensemester_clause = "AND sem.studiensemester_kurzbz = ?";
			$params[] = $studiensemester_kurzbz;
		}

		$status_clause = '';
		if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
		{
			$status_clause .= " AND status.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}


		$qry = "
			SELECT * FROM (
				SELECT
					DISTINCT ON (prestudent_id) prestudent_id, person_id, stg.oe_kurzbz AS prestudent_stg_oe_kurzbz,
					stg.studiengang_kz, COALESCE(plan.orgform_kurzbz, status.orgform_kurzbz, stg.orgform_kurzbz) AS orgform
				FROM
					public.tbl_prestudent pre
					JOIN public.tbl_person pers USING(person_id)
					JOIN public.tbl_prestudentstatus status USING(prestudent_id)
					JOIN public.tbl_studiensemester sem ON status.studiensemester_kurzbz = sem.studiensemester_kurzbz
					JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
					LEFT JOIN lehre.tbl_studienplan plan USING(studienplan_id)
				WHERE
					stg.melderelevant
					AND pre.bismelden
					{$studiensemester_clause}
					{$status_clause}
			) prestudents
			WHERE
				orgform IS NULL
				OR NOT EXISTS (SELECT 1 FROM bis.tbl_orgform WHERE orgform_kurzbz = prestudents.orgform AND rolle)";

		if (isset($studiengang_kz))
		{
			$qry .= " AND prestudents.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		if (isset($prestudent_id))
		{
			$qry .= " AND prestudents.prestudent_id = ?";
			$params[] = $prestudent_id;
		}

		if (isset($person_id))
		{
			$qry .= " AND prestudents.person_id = ?";
			$params[] = $person_id;
		}

		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND prestudents.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}

