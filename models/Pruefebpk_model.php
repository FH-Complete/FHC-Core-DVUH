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

		$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
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
	public function get($vorname, $nachname, $geburtsdatum, $geschlecht = null,
						$strasse = null, $plz = null, $geburtsland = null, $akadgrad = null, $akadnach = null,
						$alternativname = null)
	{
		if (isEmptyString($vorname))
			$result = error($this->p->t('dvuh', 'vornameNichtGesetzt'));
		elseif (isEmptyString($nachname))
			$result = error($this->p->t('dvuh', 'nachnameNichtGesetzt'));
		elseif (isEmptyString($geburtsdatum))
			$result = error($this->p->t('dvuh', 'geburtsdatumNichtGesetzt'));
		else
		{
			$callParametersArray = array(
				'uuid' => getUUID(),
				'vorname' => $vorname,
				'nachname' => $nachname,
				'geburtsdatum' => $geburtsdatum
			);

			if (!is_null($geschlecht))
				$callParametersArray['geschlecht'] = $geschlecht;
			if (!is_null($strasse))
				$callParametersArray['strasse'] = $strasse;
			if (!is_null($plz))
				$callParametersArray['plz'] = $plz;
			if (!is_null($geburtsland))
				$callParametersArray['geburtsland'] = $geburtsland;
			if (!is_null($akadgrad))
				$callParametersArray['akadgrad'] = $akadgrad;
			if (!is_null($akadnach))
				$callParametersArray['akadnach'] = $akadnach;
			if (!is_null($alternativname))
				$callParametersArray['alternativname'] = $alternativname;

			$result = $this->_call('GET', $callParametersArray);
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
			return error($this->p->t('dvuh', 'personIdNichtGesetzt'));

		$stammdatenDataResult = $this->PersonModel->getPersonStammdaten($person_id);

		if (isError($stammdatenDataResult))
			return $stammdatenDataResult;
		elseif (hasData($stammdatenDataResult))
		{
			$stammdatenData = getData($stammdatenDataResult);

			return $this->get($stammdatenData->vorname, $stammdatenData->nachname, $stammdatenData->gebdatum,
				$stammdatenData->geschlecht, null, null, null, $stammdatenData->titelpre, $stammdatenData->titelpost);
		}
		else
			return error($this->p->t('dvuh', 'keineDatenFuerPerson'));
	}
}
