<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Manage Matrikelmeldung, used for ERNP Meldung
 */
class Matrikelmeldung_model extends DVUHClientModel
{
	const NATION_OESTERREICH = 'A';

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
	public function post($be, $person_id, $matrikelnummer = null, $writeonerror = null, $ausgabedatum = null, $ausstellBehoerde = null,
						 $ausstellland = null, $dokumentnr = null, $dokumenttyp = null)
	{
		$postData = $this->retrievePostData($be, $person_id, $matrikelnummer, $writeonerror, $ausgabedatum, $ausstellBehoerde,
			$ausstellland, $dokumentnr, $dokumenttyp);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->_call(ClientLib::HTTP_POST_METHOD, null, getData($postData));

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
	public function retrievePostData($be, $person_id, $matrikelnummer = null, $writeonerror = null, $ausgabedatum = null, $ausstellBehoerde = null,
									 $ausstellland = null, $dokumentnr = null, $dokumenttyp = null)
	{
		$result = null;

		if (isEmptyString($person_id))
			$result = error('personID nicht gesetzt');
		else
		{
			$this->load->model('person/Person_model', 'PersonModel');
			$this->load->model('crm/Prestudent_model', 'PrestudentModel');
			$this->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');

			// get person Stammdaten
			$stammdatenDataResult = $this->PersonModel->getPersonStammdaten($person_id);

			if (isError($stammdatenDataResult))
				$result = $stammdatenDataResult;
			elseif (hasData($stammdatenDataResult))
			{
				$stammdatenData = getData($stammdatenDataResult);

				if (!isset($stammdatenData->adressen) || isEmptyArray($stammdatenData->adressen)) return error('Keine Adressen vorhanden');

				// get latest Heimatadresse, preferably not austrian
				$addressToSend = null;
				foreach ($stammdatenData->adressen as $adresse)
				{
					if ($adresse->heimatadresse === true)
					{
						if ($adresse->nation !== self::NATION_OESTERREICH)
						{
							$addressToSend = $adresse;
							break;
						}
						if (!isset($addressToSend)) $addressToSend = $adresse;
					}
				}
				// fallback: first address
				if (!isset($addressToSend)) $addressToSend = $stammdatenData->adressen[0];

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

				$geschlecht = $this->dvuhconversionlib->convertGeschlechtToDVUH($stammdatenData->geschlecht);

				$params['personmeldung'] = array(
					'adresseAusland' => $addressToSend->strasse,
					'be' => $be,
					'bpk' => $stammdatenData->bpk,
					'ekz' => $stammdatenData->ersatzkennzeichen,
					'gebdat' => $stammdatenData->gebdatum,
					'geschlecht' => $geschlecht,
					'matrikelnummer' => $matrikelnummer ?? $stammdatenData->matr_nr,
					'matura' => $lastZgvdatum,
					'nachname' => $stammdatenData->nachname,
					'plz' => $addressToSend->plz,
					'staat' => $addressToSend->nation,
					'vorname' => $stammdatenData->vorname,
					'writeonerror' => $writeonerror
				);

				$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/matrikelmeldung', $params, true);

				$result = success($postData);
			}
		}

		return $result;
	}
}
