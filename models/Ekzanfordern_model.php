<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';
/**
 * get Ersatzkennzeichen for Students
 */
class Ekzanfordern_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'ekzanfordern.xml';

		$this->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
	}

	/**
	 * Execute post call.
	 * @param int $person_id
	 * @param string $forcierungskey optional, for request of new EKZ when no results fit the person for which EKZ is needed.
	 * @return object success or error
	 */
	public function post($person_id, $forcierungskey = null)
	{
		$postData = $this->retrievePostData($person_id, $forcierungskey);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->_call('POST', null, getData($postData));

		return $result;
	}

	/**
	 * Retrieves necessary xml person and kontakt data for performing ekzanfordern call.
	 * @param int $person_id
	 * @param string $forcierungskey
	 * @return object success or error
	 */
	public function retrievePostData($person_id, $forcierungskey = null)
	{
		$result = null;

		if (isEmptyString($person_id))
			$result = error($this->p->t('dvuh', 'personIdNichtGesetzt'));
		else
		{
			$this->load->model('person/Person_model', 'PersonModel');
			$stammdaten = $this->PersonModel->getPersonStammdaten($person_id);

			if (hasData($stammdaten))
			{
				$stammdaten = getData($stammdaten);

				// adresses
				$heimatAdresse = null;
				$heimatInsertamum = null;

				foreach ($stammdaten->adressen as $adresse)
				{
					// get latest Heimatadresse
					if (!$adresse->heimatadresse)
						continue;

					$addr = array();
					$addr['ort'] = $adresse->ort;
					$addr['plz'] = $adresse->plz;
					$addr['strasse'] = $adresse->strasse;
					$addr['staat'] = $adresse->nation;

					$addrCheck = $this->dvuhsynclib->checkAdresse($addr);

					if (isError($addrCheck))
						return error($this->p->t('dvuh', 'adresseUngueltig').": " . getError($addrCheck));

					if ($adresse->heimatadresse)
					{
						if (is_null($heimatInsertamum) || $adresse->insertamum > $heimatInsertamum)
						{
							$heimatInsertamum = $adresse->insertamum;
							$heimatAdresse = $addr;
						}
					}
				}

				if (isEmptyString($heimatAdresse))
					return error($this->p->t('dvuh', 'heimatadresseFehlt'));

				$geschlecht = $this->dvuhsynclib->convertGeschlechtToDVUH($stammdaten->geschlecht);

				$ekzbasisdaten = array(
					'adresse' => $heimatAdresse,
					'geburtsdatum' => $stammdaten->gebdatum,
					'geschlecht' => $geschlecht,
					'nachname' => $stammdaten->nachname,
					'vorname' => $stammdaten->vorname,
				);

				foreach ($ekzbasisdaten as $idx => $item)
				{
					if (!isset($item) || isEmptyString($item))
						return error($this->p->t('dvuh', 'stammdatenFehlen').': ' . $idx);
				}

				if (isset($stammdaten->svnr))
					$ekzbasisdaten['svnr'] = $stammdaten->svnr;

				$params = array(
					'ekzbasisdaten' => $ekzbasisdaten
				);

				if (isset($forcierungskey))
					$params['forcierungskey'] = $forcierungskey;

				$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/ekzanfordern', $params, true);

				$result = success($postData);
			}
		}

		return $result;
	}
}
