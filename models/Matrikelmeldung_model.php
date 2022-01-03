<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Manage Matrikelmeldung, used for ERNP Meldung
 */
class Matrikelmeldung_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'matrikelmeldung.xml';
	}

	/**
	 * Posts ERNP Meldung.
	 * @param string $be
	 * @param int $person_id
	 * @param string $writeonerror 'true' |'false' write data to DVUH even despite minor errors.
	 * @param string $ausgabedatum
	 * @param string $ausstellBehoerde
	 * @param string $ausstellland
	 * @param string $dokumentnr
	 * @param string $dokumenttyp
	 * @return object success or error
	 */
	public function post($be, $person_id, $writeonerror = null, $ausgabedatum = null, $ausstellBehoerde = null,
						 $ausstellland = null, $dokumentnr = null, $dokumenttyp = null)
	{
		$postData = $this->retrievePostData($be, $person_id, $writeonerror, $ausgabedatum, $ausstellBehoerde,
			$ausstellland, $dokumentnr, $dokumenttyp);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->_call('POST', null, getData($postData));

		return $result;
	}

	/**
	 * Retrieves xml data necessary for ERNP Meldung.
	 * @param string $be
	 * @param int $person_id
	 * @param string
	 * @param string $ausgabedatum
	 * @param string $ausstellBehoerde
	 * @param string $ausstellland
	 * @param string $dokumentnr
	 * @param string $dokumenttyp
	 * @return object success or error
	 */
	public function retrievePostData($be, $person_id, $writeonerror = null, $ausgabedatum = null, $ausstellBehoerde = null,
									 $ausstellland = null, $dokumentnr = null, $dokumenttyp = null)
	{
		$result = null;

		if (isEmptyString($person_id))
			$result = error($this->p->t('dvuh', 'personIdNichtGesetzt'));
		else
		{
			$this->load->model('person/Person_model', 'PersonModel');
			$this->load->model('crm/Prestudent_model', 'PrestudentModel');
			$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');

			$stammdatenDataResult = $this->PersonModel->getPersonStammdaten($person_id, true);

			if (isError($stammdatenDataResult))
				$result = $stammdatenDataResult;
			elseif (hasData($stammdatenDataResult))
			{
				$stammdatenData = getData($stammdatenDataResult);

				$this->PrestudentModel->addSelect('zgvdatum');
				$this->PrestudentModel->addOrder('zgvdatum', 'DESC');
				$this->PrestudentModel->addLimit(1);
				$lastZgvdatumRes = $this->PrestudentModel->loadWhere(array('person_id' => $person_id, 'zgvdatum <>' => null));

				if (isError($lastZgvdatumRes))
					return $lastZgvdatumRes;

				$lastZgvdatum = '000000';

				if (hasData($lastZgvdatumRes))
				{
					if (isset(getData($lastZgvdatumRes)[0]->zgvdatum))
						$lastZgvdatum = getData($lastZgvdatumRes)[0]->zgvdatum;
				}

				$svnr = isset($stammdatenData->svnr) ? $stammdatenData->svnr : $stammdatenData->ersatzkennzeichen;

				$params = array(
					"uuid" => getUUID()
				);

				if (isset($ausgabedatum))
				{
					$params['ernpmeldung'] = array(
						'ausgabedatum' => $ausgabedatum,
						'ausstellBehoerde' => $ausstellBehoerde,
						'ausstellland' => $ausstellland,
						'dokumentnr' => $dokumentnr,
						'dokumenttyp' => $dokumenttyp
					);
				}

				$geschlecht = $this->dvuhsynclib->convertGeschlechtToDVUH($stammdatenData->geschlecht);

				$params['personmeldung'] = array(
					'be' => $be,
					'bpk' => $stammdatenData->bpk,
					'gebdat' => $stammdatenData->gebdatum,
					'geschlecht' => $geschlecht,
					'matrikelnummer' => $stammdatenData->matr_nr,
					'nachname' => $stammdatenData->nachname,
					'plz' => $stammdatenData->adressen[0]->plz,
					'staat' => $stammdatenData->adressen[0]->nation,
					'svnr' => $svnr,
					'vorname' => $stammdatenData->vorname,
					'writeonerror' => $writeonerror,
					'matura' => $lastZgvdatum
				);

				$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/matrikelmeldung', $params, true);

				$result = success($postData);
			}
		}

		return $result;
	}
}
