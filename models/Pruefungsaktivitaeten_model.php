<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * get Ersatzkennzeichen for Students
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
			$pruefungDataResult = $this->ZeugnisnoteModel->getByPerson($person_id, $studiensemester);
			$dvuh_studiensemester = $this->dvuhsynclib->convertSemesterToDVUH($studiensemester);

			if (isError($pruefungDataResult))
				$result = $pruefungDataResult;
			elseif (hasData($pruefungDataResult))
			{
				$studiumpruefungen = array();

				$pruefungData = getData($pruefungDataResult);

				foreach ($pruefungData as $prfg)
				{
					// studiengang kz
					$erhalter_kz = str_pad($prfg->erhalter_kz, 3, '0', STR_PAD_LEFT);
					$dvuh_stgkz = $erhalter_kz . str_pad(str_replace('-', '', $prfg->studiengang_kz), 4, '0', STR_PAD_LEFT);

					$studiumpruefungen[$prfg->prestudent_id]['matrikelnummer'] = $prfg->matr_nr;
					$studiumpruefungen[$prfg->prestudent_id]['studiensemester'] = $dvuh_studiensemester;
					$studiumpruefungen[$prfg->prestudent_id]['studiengang'] = $dvuh_stgkz;
					$studiumpruefungen[$prfg->prestudent_id]['pruefungen'][] = array(
						'ects' => $prfg->ects
					);
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
