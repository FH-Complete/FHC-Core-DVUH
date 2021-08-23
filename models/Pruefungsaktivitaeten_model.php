<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * manage Pruefungsaktivitäten for Students
 */
class Pruefungsaktivitaeten_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'pruefungsaktivitaeten.xml';

		$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
	}

	public function get($be, $semester, $matrikelnummer = null)
	{
		if (isEmptyString($semester))
			$result = error('Matrikelnummer nicht gesetzt');
		else
		{
			$callParametersArray = array(
				'be' => $be,
				'semester' => $semester,
				'uuid' => getUUID()
			);

			if (!is_null($matrikelnummer))
				$callParametersArray['matrikelnummer'] = $matrikelnummer;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}

	public function post($be, $person_id, $studiensemester, &$posted)
	{
		$postData = $this->retrievePostData($be, $person_id, $studiensemester, $posted);

		if (hasData($postData))
			$result = $this->_call('POST', null, getData($postData));
		else
			$result = $postData;

		return $result;
	}

	public function retrievePostData($be, $person_id, $studiensemester, &$toPost = array())
	{
		if (isEmptyString($person_id))
			$result = error('personID nicht gesetzt');
		else
		{
			$dvuh_studiensemester = $this->dvuhsynclib->convertSemesterToDVUH($studiensemester);

			// get ects sums for Noten of the person
			$pruefungsaktivitaetenDataResult = $this->dvuhsynclib->getPruefungsaktivitaetenData($person_id, $studiensemester);

			if (isError($pruefungsaktivitaetenDataResult))
				$result = $pruefungsaktivitaetenDataResult;
			elseif (hasData($pruefungsaktivitaetenDataResult))
			{
				$studiumpruefungen = array();

				$pruefungsaktivitaetenData = getData($pruefungsaktivitaetenDataResult);

				foreach ($pruefungsaktivitaetenData as $prestudent_id => $pruefungsaktivitaeten)
				{
					// saved ects to post to variable
					$toPost[$prestudent_id]['ects_angerechnet'] = $pruefungsaktivitaeten->ects_angerechnet;
					$toPost[$prestudent_id]['ects_erworben'] = $pruefungsaktivitaeten->ects_erworben;

					// only send Pruefungsaktivitaeten if there are ects
					if ($pruefungsaktivitaeten->ects_angerechnet == 0 && $pruefungsaktivitaeten->ects_erworben == 0)
						continue;

					// studiengang kz
					$erhalter_kz = str_pad($pruefungsaktivitaeten->erhalter_kz, 3, '0', STR_PAD_LEFT);
					$dvuh_stgkz = $erhalter_kz . str_pad(str_replace('-', '', $pruefungsaktivitaeten->studiengang_kz), 4, '0', STR_PAD_LEFT);

					// format ects
					$ectsSums = new stdClass();
					$ectsSums->ects_angerechnet = number_format($pruefungsaktivitaeten->ects_angerechnet, 1);
					$ectsSums->ects_erworben = number_format($pruefungsaktivitaeten->ects_erworben, 1);

					$studiumpruefungen[$prestudent_id]['matrikelnummer'] = $pruefungsaktivitaeten->matr_nr;
					$studiumpruefungen[$prestudent_id]['studiensemester'] = $dvuh_studiensemester;
					$studiumpruefungen[$prestudent_id]['studiengang'] = $dvuh_stgkz;
					$studiumpruefungen[$prestudent_id]['ects'] = $ectsSums;
				}

				if (isEmptyArray($studiumpruefungen))
				{
					// TODO if no pruefungen found for person, and there were ects sent last sync
					// delete pruefungsaktivitaeten

					$result = success(array());
				}
				else
				{

					$params = array(
						'uuid' => getUUID(),
						'be' => $be,
						'studiumpruefungen' => $studiumpruefungen
					);

					$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/pruefungsaktivitaeten', $params, true);

					$result = success($postData);
				}
			}
			else
				$result = success(array());
		}

		return $result;
	}
}
