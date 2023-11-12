<?php

require_once 'DVUHErrorProducerLib.php';

/**
 * Library for retrieving payment data from FHC for DVUH.
 * Extracts data from FHC db, performs data quality checks and puts data in DVUH form.
 */
class DVUHEkzLib extends DVUHErrorProducerLib
{
	const NATION_OESTERREICH = 'A';

	private $_ci;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');

		// load models
		$this->_ci->load->model('person/person_model', 'PersonModel');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');

		$this->_dbModel = new DB_Model(); // get db
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Get EKZ data for a person, which needs to be sent to DVUH.
	 * @param person_id
	 */
	public function getEkzData($person_id)
	{
		$stammdaten = $this->_ci->PersonModel->getPersonStammdaten($person_id);

		if (hasData($stammdaten))
		{
			$stammdaten = getData($stammdaten);

			// check that ekz can be requested
			$invalidField = null;
			if ($stammdaten->staatsbuergerschaft_code == self::NATION_OESTERREICH)
				$invalidField = 'staatsbuergerschaft_code';
			elseif (!isEmptyString($stammdaten->svnr))
				$invalidField = 'svnr';

			if (isset($invalidField))
				return error("EKZ Daten können nicht gesendet werden, ungültiges Feld: $invalidField");

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

				$addrCheck = $this->_ci->dvuhcheckinglib->checkAdresse($addr);

				if (isError($addrCheck))
				{
					$this->addError(
						"Adresse ungültig: " . getError($addrCheck),
						'adresseUngueltig',
						array(getError($addrCheck)),
						array('adresse_id' => $adresse->adresse_id)
					);
				}

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
				$this->addError('Heimatadresse fehlt', 'keineHeimatadresse');

			$geschlecht = $this->_ci->dvuhconversionlib->convertGeschlechtToDVUH($stammdaten->geschlecht);

			$ekzData = array(
				'adresse' => $heimatAdresse,
				'geburtsdatum' => $stammdaten->gebdatum,
				'geschlecht' => $geschlecht,
				'nachname' => $stammdaten->nachname,
				'vorname' => $stammdaten->vorname,
			);

			foreach ($ekzData as $idx => $item)
			{
				if (!isset($item) || isEmptyString($item))
					$this->addError('Stammdaten fehlen: ' . $idx, 'stammdatenFehlen', array($idx));
			}

			if (isset($stammdaten->svnr))
				$ekzData['svnr'] = $stammdaten->svnr;

			if ($this->hasError())
				return error($this->readErrors());

			return success($ekzData);
		}
		else
			return error("keine Stammdaten gefunden");
	}

	/**
	 * Saves Ekz in fhcomplete database.
	 * @param person_id
	 * @param ersatzkennzeichen
	 * @return object success or error
	 */
	public function saveEkz($person_id, $ersatzkennzeichen)
	{
		// check if different person already has the ekz
		$this->_ci->PersonModel->addSelect('person_id');
		$ekzExistsRes = $this->_ci->PersonModel->loadWhere(array('ersatzkennzeichen' => $ersatzkennzeichen, 'person_id <>' => $person_id));

		if (hasData($ekzExistsRes))
		{
			$otherPersonId = getData($ekzExistsRes)[0]->person_id;
			$this->addError(
				"Person (person Id $otherPersonId) mit EKZ $ersatzkennzeichen existiert bereits",
				'personMitEkzExistiert',
				array('otherPersonId' => $otherPersonId, 'ersatzkennzeichen' => $ersatzkennzeichen),
				array('otherPersonId' => $otherPersonId, 'ersatzkennzeichen' => $ersatzkennzeichen)
			);
		}

		if ($this->hasError())
			return error($this->readErrors());

		return $this->_ci->fhcmanagementlib->saveEkzInFhc($person_id, $ersatzkennzeichen);
	}
}
