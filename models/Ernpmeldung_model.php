<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Manage Matrikelmeldung, used for ERNP Meldung
 */
class Ernpmeldung_model extends DVUHClientModel
{
	const NATION_OESTERREICH = 'A';

	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'ernpMeldung.xml';
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
	public function post($be, $person_id, $ausgabedatum = null, $ausstellBehoerde = null,
						 $ausstellland = null, $dokumentnr = null, $dokumenttyp = null)
	{
		$postData = $this->retrievePostData($be, $person_id, $ausgabedatum, $ausstellBehoerde,
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
	 * @param string $ausgabedatum
	 * @param string $ausstellBehoerde
	 * @param string $ausstellland
	 * @param string $dokumentnr
	 * @param string $dokumenttyp
	 * @return object success or error
	 */
	public function retrievePostData($be, $person_id, $ausgabedatum = null, $ausstellBehoerde = null,
									 $ausstellland = null, $dokumentnr = null, $dokumenttyp = null)
	{
		$result = null;

		if (isEmptyString($person_id))
			$result = error('personID nicht gesetzt');
		else
		{
			$this->load->model('person/Person_model', 'PersonModel');
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

				$params = array(
					"uuid" => getUUID()
				);

				if (isset($ausgabedatum))
				{
					$params['ernpmeldung']['idDokument'] = array(
						'ausgabedatum' => $ausgabedatum,
						'ausstellBehoerde' => $ausstellBehoerde,
						'ausstellland' => $ausstellland,
						'dokumentnr' => $dokumentnr,
						'dokumenttyp' => $dokumenttyp
					);
				}

				$geschlecht = $this->dvuhconversionlib->convertGeschlechtToDVUH($stammdatenData->geschlecht);

				$params['ernpmeldung'] = array_merge(
					$params['ernpmeldung'],
					array(
						'adresse' => array(
							'hausnummer' => 'N/A',
							'ort' => trim(substr($addressToSend->ort, 0, 60)),
							'plz' => $addressToSend->plz,
							'staat' => $addressToSend->nation,
							'strasse' => trim(substr($addressToSend->strasse, 0, 54))
						),
						'be' => $be,
						'gebdat' => $stammdatenData->gebdatum,
						'geburtsland' => $stammdatenData->geburtsnation_code,
						'geschlecht' => $geschlecht,
						'matrikelnummer' => $stammdatenData->matr_nr,
						'nachname' => $stammdatenData->nachname,
						'staatsangehoerigkeit' => $stammdatenData->staatsbuergerschaft_code,
						'vorname' => $stammdatenData->vorname
					)
				);

				$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/ernpmeldung', $params, true);

				$result = success($postData);
			}
		}

		return $result;
	}
}
