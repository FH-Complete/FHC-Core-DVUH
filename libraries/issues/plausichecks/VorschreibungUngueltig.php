<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class VorschreibungUngueltig extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$buchungstypen = isset($params['buchungstypen']) ? $params['buchungstypen'] : null;
		$person_id = isset($params['person_id']) ? $params['person_id'] : null;

		if (!isset($buchungstypen) || isEmptyArray($buchungstypen))
		{
			$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
			$buchungstypenConfig = $this->_ci->config->item('fhc_dvuh_buchungstyp') ?? [];
			$buchungstypen = array_merge($buchungstypenConfig['oehbeitrag'] ?? [], $buchungstypenConfig['studiengebuehr'] ?? []);

			if (isEmptyArray($buchungstypen)) return success([]);
		}

		// get all students failing the plausicheck
		$buchungenRes = $this->_getBuchungSums($studiensemester_kurzbz, $studiengang_kz, $buchungstypen, $person_id, $exkludierte_studiengang_kz);

		if (isError($buchungenRes)) return $buchungenRes;

		if (hasData($buchungenRes))
		{
			$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

			$buchungen = getData($buchungenRes);

			// populate results with data necessary for writing issues
			foreach ($buchungen as $buchung)
			{
				$vorschreibungCheck = true;
				if (isset($buchungstypenConfig['oehbeitrag']) && in_array($buchung->buchungstyp_kurzbz, $buchungstypenConfig['oehbeitrag']))
				{
					// call method for checking oehbeitrag
					$vorschreibungCheck = $this->_ci->dvuhcheckinglib->checkOehBeitrag($buchung->betrag);
				}
				elseif (
					isset($buchungstypenConfig['studiengebuehr']) && in_array($buchung->buchungstyp_kurzbz, $buchungstypenConfig['studiengebuehr'])
				) {
					// call method for checking studiengebuehr
					$vorschreibungCheck = $this->_ci->dvuhcheckinglib->checkStudiengebuehr($buchung->betrag);
				}

				// if check failed, produce issue
				if (!$vorschreibungCheck)
				{
					$results[] = array(
						'person_id' => $buchung->person_id,
						'fehlertext_params' => array('buchungstypen' => $buchung->buchungstyp_kurzbz),
						'resolution_params' => array('buchungstypen' => [$buchung->buchungstyp_kurzbz])
					);
				}
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Get invalid charges.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param buchungstypen array type of charges
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	public function _getBuchungSums(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$buchungstypen = null,
		$person_id = null,
		$exkludierte_studiengang_kz = null
	) {
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendCharge'] ?? null;

		$params = array();

		$qry = "SELECT person_id, buchungstyp_kurzbz, studiensemester_kurzbz, abs(sum(betrag))*100 AS betrag FROM (
					SELECT
						DISTINCT person_id, buchungstyp_kurzbz, kto.studiensemester_kurzbz, betrag
					FROM
						public.tbl_prestudent pre
						JOIN public.tbl_person pers USING(person_id)
						JOIN public.tbl_prestudentstatus status USING(prestudent_id)
						JOIN public.tbl_studiensemester sem ON status.studiensemester_kurzbz = sem.studiensemester_kurzbz
						JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
						JOIN public.tbl_konto kto USING(person_id)
					WHERE
						stg.melderelevant
						AND pre.bismelden
						AND kto.betrag <= 0
						AND kto.buchungsnr_verweis IS NULL
						AND EXISTS (
							SELECT
								1
							FROM
								public.tbl_prestudent
								JOIN public.tbl_prestudentstatus USING (prestudent_id)
							WHERE
								tbl_prestudent.person_id = kto.person_id
								AND tbl_prestudentstatus.studiensemester_kurzbz = kto.studiensemester_kurzbz )";

		if (isset($studiensemester_kurzbz))
		{
			$qry .= " AND kto.studiensemester_kurzbz = ?";
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

		if (isset($buchungstypen) && !isEmptyArray($buchungstypen))
		{
			$qry .= " AND kto.buchungstyp_kurzbz IN ?";
			$params[] = $buchungstypen;
		}

		$qry .= ") AS buchungen
				GROUP BY
					person_id, studiensemester_kurzbz, buchungstyp_kurzbz";

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
