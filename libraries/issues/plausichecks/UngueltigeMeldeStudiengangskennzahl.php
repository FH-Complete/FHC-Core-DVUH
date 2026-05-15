<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class UngueltigeMeldeStudiengangskennzahl extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$person_id = isset($params['person_id']) ? $params['person_id'] : null;

		// get all students failing the plausicheck
		$studiengangRes = $this->_getStudiengaenge($studiengang_kz, $person_id, $exkludierte_studiengang_kz);

		if (isError($studiengangRes)) return $studiengangRes;

		if (hasData($studiengangRes))
		{
			$studiengaenge = getData($studiengangRes);

			// populate results with data necessary for writing issues
			foreach ($studiengaenge as $studiengang)
			{
				$results[] = array(
					'oe_kurzbz' => $studiengang->oe_kurzbz,
					'fehlertext_params' => array('studiengang_kz' => $studiengang->studiengang_kz),
					'resolution_params' => array('studiengang_kz' => $studiengang->studiengang_kz)
				);
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Invalid Meldestudiengangskennzahl.
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	public function _getStudiengaenge(
		$studiengang_kz = null,
		$person_id = null,
		$exkludierte_studiengang_kz = null
	) {
		$params = array();

		$qry = "
			SELECT
				DISTINCT stg.studiengang_kz, stg.melde_studiengang_kz, stg.oe_kurzbz
			FROM
				public.tbl_studiengang stg
			WHERE
				stg.aktiv
				AND stg.melderelevant
				AND (
					CASE WHEN lgartcode IS NOT NULL THEN LPAD(stg.erhalter_kz::varchar, 3, '0') ELSE '' END
					|| LPAD(abs(stg.studiengang_kz)::varchar , 4, '0')
				) <> stg.melde_studiengang_kz";

		if (isset($studiengang_kz))
		{
			$qry .= " AND stg.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		if (isset($person_id))
		{
			$params[] = $person_id;

			$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
			$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendStudyData'] ?? null;

			$status_clause = "";
			if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
			{
				$status_clause = " AND status.status_kurzbz IN ?";
				$params[] = $status_kurzbz;
			}

			$qry .= "
				AND EXISTS (
					SELECT 1
					FROM
						public.tbl_prestudent pre
						JOIN public.tbl_studiengang stgg USING (studiengang_kz)
						JOIN public.tbl_prestudentstatus status USING (prestudent_id)
					WHERE
						stgg.studiengang_kz = stg.studiengang_kz
						AND stgg.melderelevant
						AND pre.bismelden
						AND pre.person_id = ?
						{$status_clause}
				)";
		}

		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND stg.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
