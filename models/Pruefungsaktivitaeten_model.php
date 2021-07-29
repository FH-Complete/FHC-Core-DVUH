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
		$this->_url = '/0.5/pruefungsaktivitaeten.xml';

		$this->load->model('education/Zeugnisnote_model', 'ZeugnisnoteModel');

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

	public function post($be, $person_id, $studiensemester)
	{
		$postData = $this->retrievePostData($be, $person_id, $studiensemester);

		if (hasData($postData))
			$result = $this->_call('POST', null, getData($postData));
		else
			$result = $postData;

		return $result;
	}

	public function retrievePostData($be, $person_id, $studiensemester)
	{
		if (isEmptyString($person_id))
			$result = error('personID nicht gesetzt');
		else
		{
			$dvuh_studiensemester = $this->dvuhsynclib->convertSemesterToDVUH($studiensemester);

			// get ects sums of Noten which are aktiv, offiziell, positiv (but both lehre and non-lehre)
			$ectsSumsResult = $this->ZeugnisnoteModel->getEctsSumsByPerson($person_id, $studiensemester, true, null, true, true);

			if (isError($ectsSumsResult))
				$result = $ectsSumsResult;
			elseif (hasData($ectsSumsResult))
			{
				$studiumpruefungen = array();

				$ectsSumsData = getData($ectsSumsResult);

				foreach ($ectsSumsData as $ectsSum)
				{
					// only send Pruefungsaktivitaeten if there is at least one Note
					if ($ectsSum->summe_ects == 0)
						continue;

					// studiengang kz
					$erhalter_kz = str_pad($ectsSum->erhalter_kz, 3, '0', STR_PAD_LEFT);
					$dvuh_stgkz = $erhalter_kz . str_pad(str_replace('-', '', $ectsSum->studiengang_kz), 4, '0', STR_PAD_LEFT);

					$studiumpruefungen[$ectsSum->prestudent_id]['matrikelnummer'] = $ectsSum->matr_nr;
					$studiumpruefungen[$ectsSum->prestudent_id]['studiensemester'] = $dvuh_studiensemester;
					$studiumpruefungen[$ectsSum->prestudent_id]['studiengang'] = $dvuh_stgkz;
					$studiumpruefungen[$ectsSum->prestudent_id]['ects'] = number_format($ectsSum->summe_ects, 1);
				}

				$params = array(
					'uuid' => getUUID(),
					'be' => $be,
					'studiumpruefungen' => $studiumpruefungen
				);

				$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/pruefungsaktivitaeten', $params, true);

				$result = success($postData);
			}
			else
				$result = success(array());
		}

		return $result;
	}
}
