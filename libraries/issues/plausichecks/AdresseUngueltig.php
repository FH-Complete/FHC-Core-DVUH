<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 *
 */
class AdresseUngueltig extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// pass parameters needed for plausicheck
		$studiensemester_kurzbz = isset($params['studiensemester_kurzbz']) ? $params['studiensemester_kurzbz'] : null;
		$studiengang_kz = isset($params['studiengang_kz']) ? $params['studiengang_kz'] : null;
		$adresse_id = isset($params['adresse_id']) ? $params['adresse_id'] : null;

		// get all students failing the plausicheck
		$personRes = $this->_getPersons($studiensemester_kurzbz, $studiengang_kz, $adresse_id, $exkludierte_studiengang_kz);

		if (isError($personRes)) return $personRes;

		if (hasData($personRes))
		{
			$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

			$persons = getData($personRes);

			// populate results with data necessary for writing issues
			foreach ($persons as $person)
			{
				// ort - comes from Gemeinde Feld, from Ort if Gemeinde empty and address not austrian
				$ort = null;
				if (isset($person->gemeinde))
					$ort = $person->gemeinde;
				elseif ($person->nation !== 'A')
					$ort = $person->ort;

				$addr = array();
				$addr['ort'] = $ort;
				$addr['plz'] = $person->plz;
				$addr['strasse'] = $person->strasse;
				$addr['staat'] = $person->nation;

				// call check method
				$addrCheck = $this->_ci->dvuhcheckinglib->checkAdresse($addr);

				// if check failed, produce issue
				if (isError($addrCheck))
				{
					$results[] = array(
						'person_id' => $person->person_id,
						'resolution_params' => array('adresse_id' => $person->adresse_id),
						'fehlertext_params' => array('fehlertext' => getError($addrCheck))
					);
				}

			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Invalid adress.
	 * @param studiensemester_kurzbz string if check is to be executed for certain Studiensemester
	 * @param studiengang_kz int if check is to be executed for certain Studiengang
	 * @param adresse_id int if check is to be executed only for one address
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	public function _getPersons(
		$studiensemester_kurzbz = null,
		$studiengang_kz = null,
		$adresse_id = null,
		$exkludierte_studiengang_kz = null
	) {
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendCharge'] ?? null;

		$params = array();

		$qry = "
			SELECT
				DISTINCT person_id, adresse_id, addr.strasse, addr.plz, addr.gemeinde, addr.nation, addr.ort
			FROM
				public.tbl_prestudent pre
				JOIN public.tbl_person USING(person_id)
				JOIN public.tbl_adresse addr USING(person_id)
				JOIN public.tbl_prestudentstatus status USING(prestudent_id)
				JOIN public.tbl_studiengang stg ON pre.studiengang_kz = stg.studiengang_kz
			WHERE
				stg.melderelevant
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

		if (isset($status_kurzbz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND status.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}

		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND stg.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		if (isset($adresse_id))
		{
			$qry .= " AND pers.adresse_id = ?";
			$params[] = $adresse_id;
		}

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
