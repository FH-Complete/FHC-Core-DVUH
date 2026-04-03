<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class VorgeschrBetragUngleichFestgesetzt extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$buchungsnr = isset($params['buchungsnr']) ? $params['buchungsnr'] : null;

		// get all students failing the plausicheck
		$buchungenRes = $this->_getBuchungen($studiensemester_kurzbz, $studiengang_kz, $buchungsnr, $exkludierte_studiengang_kz);

		if (isError($buchungenRes)) return $buchungenRes;

		if (hasData($buchungenRes))
		{
			$buchungen = getData($buchungenRes);

			// populate results with data necessary for writing issues
			foreach ($buchungen as $buchung)
			{
				// if check failed, produce issue
				$results[] = array(
					'person_id' => $buchung->person_id,
					'fehlertext_params' => array('betrag' => $buchung->betrag_netto, 'referenz_betrag' => $buchung->studierendenbeitrag),
					'resolution_params' => array('buchungsnr' => $buchung->buchungsnr)
				);
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Charged amount not equal to specified amount for the semester.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param person_id int if check is to be executed only for one address
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	public function _getBuchungen(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$person_id = null,
		$exkludierte_studiengang_kz = null
	) {
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendCharge'] ?? null;

		$buchungstypenConfig = $this->_ci->config->item('fhc_dvuh_buchungstyp') ?? [];
		if (!isset($buchungstypenConfig['oehbeitrag'][0])) return success([]);
		$buchungstyp = $buchungstypenConfig['oehbeitrag'][0];

		$params = array();

		$buchungstyp_clause = 'AND kto.buchungstyp_kurzbz = ?';
		$params[] = $buchungstyp;
		$studiensemester_clause = '';

		if (isset($studiensemester_kurzbz))
		{
			$studiensemester_clause = "AND kto.studiensemester_kurzbz = ?";
			$params[] = $studiensemester_kurzbz;
		}

		$studiengang_clause = '';
		if (isset($studiengang_kz))
		{
			$studiengang_clause = " AND stg.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		$status_clause = '';
		if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
		{
			$status_clause = " AND status.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}

		$exkl_studiengang_clause = '';
		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$exkl_studiengang_clause = " AND stg.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		$buchungsnr_clause = '';
		if (isset($buchungsnr))
		{
			$buchungsnr_clause = " AND kto.buchungsnr = ?";
			$params[] = $buchungsnr;
		}

		$qry = "
				SELECT * FROM (
					SELECT
						DISTINCT ON (kto.buchungsnr) kto.buchungsnr, person_id, buchungstyp_kurzbz,
						kto.betrag, abs(kto.betrag) - betraege.versicherung AS betrag_netto, betraege.studierendenbeitrag, (
							SELECT
								studierendenbeitrag + versicherung
							FROM
								bis.tbl_oehbeitrag
								LEFT JOIN public.tbl_studiensemester sem_start ON von_studiensemester_kurzbz = sem_start.studiensemester_kurzbz
								LEFT JOIN public.tbl_studiensemester sem_ende ON bis_studiensemester_kurzbz = sem_ende.studiensemester_kurzbz
							WHERE
								(kto_sem.start::date >= sem_start.start OR sem_start.start IS NULL)
								AND (kto_sem.start::date <= sem_ende.ende OR sem_ende.ende IS NULL)
							ORDER BY
								sem_start.start DESC
							LIMIT 1
						) AS referenz_betrag
					FROM
						public.tbl_prestudent pre
						JOIN public.tbl_person pers USING(person_id)
						JOIN public.tbl_prestudentstatus status USING(prestudent_id)
						JOIN public.tbl_studiensemester sem ON status.studiensemester_kurzbz = sem.studiensemester_kurzbz
						JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
						JOIN public.tbl_konto kto USING(person_id)
						JOIN public.tbl_studiensemester kto_sem ON kto.studiensemester_kurzbz = kto_sem.studiensemester_kurzbz
						JOIN (
							SELECT
								studierendenbeitrag, versicherung, sem_start.start, sem_ende.ende
							FROM
								bis.tbl_oehbeitrag
								LEFT JOIN public.tbl_studiensemester sem_start ON von_studiensemester_kurzbz = sem_start.studiensemester_kurzbz
								LEFT JOIN public.tbl_studiensemester sem_ende ON bis_studiensemester_kurzbz = sem_ende.studiensemester_kurzbz
							/*WHERE
								(kto_sem.start::date >= sem_start.start OR sem_start.start IS NULL)
								AND (kto_sem.start::date <= sem_ende.ende OR sem_ende.ende IS NULL)*/
							ORDER BY
								sem_start.start DESC
							LIMIT 1
						) betraege ON (kto_sem.start::date >= betraege.start OR betraege.start IS NULL)
							AND (kto_sem.start::date <= betraege.ende OR betraege.ende IS NULL)
					WHERE
						stg.melderelevant
						AND pre.bismelden
						AND kto.betrag < 0
						AND kto.buchungsnr_verweis IS NULL
						AND EXISTS (
							SELECT 1 FROM public.tbl_prestudent
							JOIN public.tbl_prestudentstatus USING (prestudent_id)
							WHERE tbl_prestudent.person_id = kto.person_id
							AND tbl_prestudentstatus.studiensemester_kurzbz = kto.studiensemester_kurzbz
						)
						{$buchungstyp_clause}
						{$studiensemester_clause}
						{$studiengang_clause}
						{$status_clause}
						{$buchungsnr_clause}
						{$exkl_studiengang_clause}
				) buchungen
				WHERE betrag_netto <> studierendenbeitrag";

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
