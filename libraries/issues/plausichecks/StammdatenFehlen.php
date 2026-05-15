<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class StammdatenFehlen extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$person_id = isset($params['person_id']) ? $params['person_id'] : null;
		$fehlendes_feld = isset($params['fehlendes_feld']) ? $params['fehlendes_feld'] : null;

		// get all students failing the plausicheck
		$personRes = $this->_getStammdatenFehlen(
			$studiensemester_kurzbz,
			$studiengang_kz,
			$person_id,
			$fehlendes_feld,
			$exkludierte_studiengang_kz
		);

		if (isError($personRes)) return $personRes;

		if (hasData($personRes))
		{
			$persons = getData($personRes);

			// populate results with data necessary for writing issues
			foreach ($persons as $person)
			{
				$results[] = array(
					'person_id' => $person->person_id,
					'fehlertext_params' => array('fehlendes_feld' => $person->fehlendes_feld),
					'resolution_params' => array('person_id' => $person->person_id, 'fehlendes_feld' => $person->fehlendes_feld)
				);
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Missing Stammdaten.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param person_id int if check is to be executed only for one address
	 * @param fehlendes_feld string the particular field which is missing
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	private function _getStammdatenFehlen(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$person_id = null,
		$fehlendes_feld = null,
		$exkludierte_studiengang_kz = null
	) {

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendCharge'] ?? null;

		$params = array();

		$studiensemester_clause = '';
		if (isset($studiensemester_kurzbz))
		{
			$studiensemester_clause = "AND status.studiensemester_kurzbz = ?";
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
					DISTINCT ON (person_id) person_id,
					(
						CASE
							WHEN pers.vorname IS NULL OR pers.vorname = '' THEN 'vorname'
							WHEN pers.nachname IS NULL OR pers.nachname = '' THEN 'nachname'
							WHEN pers.gebdatum IS NULL THEN 'gebdatum'
							WHEN pers.geschlecht IS NULL THEN 'geschlecht'
							WHEN pers.staatsbuergerschaft IS NULL THEN 'staatsbuergerschaft'
							ELSE NULL
						END
					) AS fehlendes_feld
				FROM
					public.tbl_prestudent pre
					JOIN public.tbl_person pers USING(person_id)
					JOIN public.tbl_prestudentstatus status USING(prestudent_id)
					JOIN public.tbl_studiensemester sem ON status.studiensemester_kurzbz = sem.studiensemester_kurzbz
					JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
					LEFT JOIN public.tbl_adresse addr USING(person_id)
				WHERE
					stg.melderelevant
					AND pre.bismelden
					{$studiensemester_clause}
					{$status_clause}
			) persons
			WHERE fehlendes_feld IS NOT NULL";

		if (isset($studiengang_kz))
		{
			$qry .= " AND persons.studiengang_kz = ?";
			$params[] = $studiengang_kz;
		}

		if (isset($person_id))
		{
			$qry .= " AND persons.person_id = ?";
			$params[] = $person_id;
		}

		if (isset($fehlendes_feld))
		{
			$qry .= " AND persons.fehlendes_feld = ?";
			$params[] = $fehlendes_feld;
		}

		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND persons.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
