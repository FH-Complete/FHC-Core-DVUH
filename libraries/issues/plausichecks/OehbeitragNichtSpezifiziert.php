<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 * Oehbeitrag not specified for a Studiensemester.
 */
class OehbeitragNichtSpezifiziert extends PlausiChecker
{
	public function executePlausiCheck($params)
	{
		if (!isset($params['studiensemester_kurzbz'])) return error('Studiensemester missing');

		$results = array();

		$this->_ci->load->model('codex/Oehbeitrag_model', 'OehbeitragModel');

		// get oehbeitrag defined for given student semester
		$oehbeitragRes = $this->_ci->OehbeitragModel->getByStudiensemester($params['studiensemester_kurzbz']);

		if (isError($oehbeitragRes)) return $oehbeitragRes;

		if (!hasData($oehbeitragRes))
		{
			$prestudents = getData($oehbeitragRes);

			$this->_ci->load->model('organisation/Organisationseinheit_model', 'OrganisationseinheitModel');
			$oeRes = $this->_ci->OrganisationseinheitModel->getHeads();

			if (isError($oeRes)) return $oeRes;

			$oe_kurzbz = hasData($oeRes) ? getData($oeRes)[0]->oe_kurzbz : '';

			// populate results with data necessary for writing issues
			$results[] = array(
				'oe_kurzbz' => $oe_kurzbz,
				'fehlertext_params' =>
					array('studiensemester_kurzbz' => $params['studiensemester_kurzbz'], 'buchung' => '-'),
				'resolution_params' =>
					array('studiensemester_kurzbz' => $params['studiensemester_kurzbz'])
			);
		}

		// return the results
		return success($results);
	}
}
