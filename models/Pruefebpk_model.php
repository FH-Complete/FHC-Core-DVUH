<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Request BPK BF of a Student
 */
class Pruefebpk_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'pruefebpk.xml';

		// load models
		$this->load->model('person/Person_model', 'PersonModel');

		// load libraries
		$this->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
	}

	/**
	 * Performs request with person data to check if bpk exists.
	 * @param string $vorname
	 * @param string $nachname
	 * @param string $geburtsdatum
	 * @param string $geschlecht
	 * @param string $strasse
	 * @param string $plz
	 * @param string $geburtsland
	 * @param string $akadgrad
	 * @param string $akadnach
	 * @param string $alternativname
	 * @return object success or error
	 */
	public function get(
		$vorname,
		$nachname,
		$geburtsdatum,
		$geschlecht,
		$strasse = null,
		$hausnummer = null,
		$plz = null,
		$staat = null,
		$frueherername = null,
		$sonstigername = null
	) {
		if (isEmptyString($vorname))
			$result = error('Vorname nicht gesetzt');
		elseif (isEmptyString($nachname))
			$result = error('Nachname nicht gesetzt');
		elseif (isEmptyString($geburtsdatum))
			$result = error('Geburtsdatum nicht gesetzt');
		elseif (isEmptyString($geschlecht))
			$result = error('Geschlecht nicht gesetzt');
		else
		{
			$callParametersArray = array(
				'uuid' => getUUID(),
				'vorname' => $vorname,
				'nachname' => $nachname,
				'geburtsdatum' => $geburtsdatum,
				'geschlecht' => $this->dvuhconversionlib->convertGeschlechtToDVUH($geschlecht)
			);

			if (!is_null($strasse))
				$callParametersArray['strasse'] = $strasse;
			if (!is_null($hausnummer))
				$callParametersArray['hausnummer'] = $hausnummer;
			if (!is_null($plz))
				$callParametersArray['plz'] = $plz;
			if (!is_null($staat))
				$callParametersArray['staat'] = $staat;
			if (!is_null($frueherername))
				$callParametersArray['frueherername'] = $frueherername;
			if (!is_null($sonstigername))
				$callParametersArray['sonstigername'] = $sonstigername;

			$result = $this->_call(ClientLib::HTTP_GET_METHOD, $callParametersArray);
		}

		return $result;
	}

	/**
	 * Performs request to check if bpk exists, retrieving necessary person data from person_id.
	 * @param int $person_id
	 * @return object success or error
	 */
	public function getByPersonId($person_id)
	{
		if (!isset($person_id))
			return error("Person muss angegeben werden");

		$stammdatenDataResult = $this->PersonModel->getPersonStammdaten($person_id);

		if (isError($stammdatenDataResult))
			return $stammdatenDataResult;
		elseif (hasData($stammdatenDataResult))
		{
			$stammdatenData = getData($stammdatenDataResult);

			return $this->get(
				$stammdatenData->vorname,
				$stammdatenData->nachname,
				$stammdatenData->gebdatum,
				$stammdatenData->geschlecht
			);
		}
		else
			return error("No data found for person.");
	}
}
