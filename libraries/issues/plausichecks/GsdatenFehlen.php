<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class GsdatenFehlen extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$mobilitaet_id = isset($params['mobilitaet_id']) ? $params['mobilitaet_id'] : null;
		$fehlendes_feld = isset($params['fehlendes_feld']) ? $params['fehlendes_feld'] : null;
		$person_id = isset($params['person_id']) ? $params['person_id'] : null;

		// get all students failing the plausicheck
		$prestudentRes = $this->_getGsdatenFehlen(
			$studiensemester_kurzbz,
			$studiengang_kz,
			$mobilitaet_id,
			$fehlendes_feld,
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
					'oe_kurzbz' => $prestudent->prestudent_stg_oe_kurzbz,
					'fehlertext_params' => array('fehlendes_feld' => $prestudent->fehlendes_feld),
					'resolution_params' => array('mobilitaet_id' => $prestudent->mobilitaet_id, 'fehlendes_feld' => $prestudent->fehlendes_feld)
				);
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * No Gs data.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param mobilitaet_id int if check is to be executed only for one mobiliy
	 * @param fehlendes_feld string name of missing field
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	private function _getGsdatenFehlen(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$mobilitaet_id = null,
		$fehlendes_feld = null,
		$person_id = null,
		$exkludierte_studiengang_kz = null
	) {
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendStudyData'] ?? null;

		$params = array();

		$studiensemester_clause = '';
		if (isset($studiensemester_kurzbz))
		{
			$studiensemester_clause = "AND mo.studiensemester_kurzbz = ?";
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
					DISTINCT ON (mobilitaet_id) mobilitaet_id prestudent_id, person_id, stg.oe_kurzbz AS prestudent_stg_oe_kurzbz, stg.studiengang_kz,
					mo.mobilitaet_id,
					(
						CASE
							WHEN mo.mobilitaetsprogramm_code IS NULL THEN 'mobilitaetsprogramm_code'
							WHEN fa.partner_code IS NULL OR fa.partner_code = '' THEN 'partner_code'
							WHEN pr.programm_code IS NULL THEN 'programm_code'
							ELSE NULL
						END
					) AS fehlendes_feld
				FROM
					public.tbl_prestudent pre
					JOIN public.tbl_person USING(person_id)
					JOIN public.tbl_prestudentstatus status USING(prestudent_id)
					JOIN public.tbl_studiensemester sem ON status.studiensemester_kurzbz = sem.studiensemester_kurzbz
					JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
					JOIN bis.tbl_mobilitaet mo ON pre.prestudent_id = mo.prestudent_id
					LEFT JOIN bis.tbl_gsprogramm pr USING(gsprogramm_id)
					LEFT JOIN public.tbl_firma fa USING(firma_id)
				WHERE
					(
						mo.mobilitaetsprogramm_code IS NULL
						OR fa.partner_code IS NULL OR fa.partner_code = ''
						OR pr.programm_code IS NULL
					)
					AND stg.melderelevant
					AND pre.bismelden
					{$studiensemester_clause}
					{$status_clause}
			) mobilitaeten
			WHERE fehlendes_feld IS NOT NULL";

		if (isset($studiengang_kz))
		{
			$qry .= " AND mobilitaeten.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		if (isset($mobilitaet_id))
		{
			$qry .= " AND mobilitaeten.mobilitaet_id = ?";
			$params[] = $mobilitaet_id;
		}

		if (isset($fehlendes_feld))
		{
			$qry .= " AND mobilitaeten.fehlendes_feld = ?";
			$params[] = $fehlendes_feld;
		}

		if (isset($person_id))
		{
			$qry .= " AND mobilitaeten.person_id = ?";
			$params[] = $person_id;
		}

		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND mobilitaeten.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
