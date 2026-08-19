<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class ZlgUngleichVorschreibung extends PlausiChecker
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
		$person_id = isset($params['person_id']) ? $params['person_id'] : null;

		// get all students failing the plausicheck
		$buchungenRes = $this->_getZlgUngleichVorschreibung(
			$studiensemester_kurzbz,
			$studiengang_kz,
			$buchungsnr,
			$person_id,
			$exkludierte_studiengang_kz
		);

		if (isError($buchungenRes)) return $buchungenRes;

		if (hasData($buchungenRes))
		{
			$buchungen = getData($buchungenRes);

			// populate results with data necessary for writing issues
			foreach ($buchungen as $buchung)
			{
				$results[] = array(
					'person_id' => $buchung->person_id,
					'fehlertext_params' => array('buchungsnr' => $buchung->buchungsnr),
					'resolution_params' => array('buchungsnr_verweis' => $buchung->buchungsnr)
				);
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Payment not equal to charge.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param buchungsnr int if check is to be executed only for one address
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	public function _getZlgUngleichVorschreibung(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$buchungsnr = null,
		$person_id = null,
		$exkludierte_studiengang_kz = null
	) {
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendPayment'] ?? null;

		$params = array();

		$studiensemester_clause = '';
		if (isset($studiensemester_kurzbz))
		{
			$studiensemester_clause = "AND kto.studiensemester_kurzbz = ?";
			$params[] = $studiensemester_kurzbz;
		}

		$buchungstypenConfig = $this->_ci->config->item('fhc_dvuh_buchungstyp') ?? [];
		$buchungstypen = array_merge($buchungstypenConfig['oehbeitrag'] ?? [], $buchungstypenConfig['studiengebuehr'] ?? []);


		$buchungstypen_clause = '';
		if (!isEmptyArray($buchungstypen))
		{
			$buchungstypen_clause = "AND kto.buchungstyp_kurzbz IN ?";
			$params[] = $buchungstypen;
		}

		$studiengang_clause = '';
		if (isset($studiengang_kz))
		{
			$studiengang_clause = "AND stg.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		$status_clause = '';
		if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
		{
			$status_clause = "AND status.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}

		$exkl_studiengang_clause = '';
		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$exkl_studiengang_clause = "AND stg.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		$buchungsnr_clause = '';
		if (isset($buchungsnr))
		{
			$buchungsnr_clause = "AND kto.buchungsnr = ?";
			$params[] = $buchungsnr;
		}

		$person_id_clause = '';
		if (isset($person_id))
		{
			$person_id_clause = "AND pre.person_id = ?";
			$params[] = $person_id;
		}

		$qry = "
				SELECT * FROM 
				(
					SELECT
						DISTINCT ON (buchungsnr) person_id, kto.buchungsnr, kto.buchungstyp_kurzbz, COALESCE(betrag, 0) AS vorgeschrieben,
						(SELECT COALESCE(SUM(betrag), 0) FROM public.tbl_konto WHERE buchungsnr_verweis = kto.buchungsnr) AS gezahlt
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
						AND betrag <= 0
						AND buchungsnr_verweis IS NULL
						AND EXISTS (
							SELECT 1 FROM public.tbl_prestudent
							JOIN public.tbl_prestudentstatus USING (prestudent_id)
							WHERE tbl_prestudent.person_id = kto.person_id
							AND tbl_prestudentstatus.studiensemester_kurzbz = kto.studiensemester_kurzbz
						)
						{$studiensemester_clause}
						{$buchungstypen_clause}
						{$studiengang_clause}
						{$status_clause}
						{$exkl_studiengang_clause}
						{$buchungsnr_clause}
						{$person_id_clause}
				) buchungen
				WHERE
					gezahlt > 0 
					AND vorgeschrieben + gezahlt <> 0";

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
