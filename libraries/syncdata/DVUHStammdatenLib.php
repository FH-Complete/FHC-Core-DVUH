<?php

/**
 * Library for retrieving Stammdaten data from FHC for DVUH.
 * Extracts data from FHC db, performs data quality checks and puts data in DVUH form.
 */
class DVUHStammdatenLib
{
	private $_ci;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Retrieves Stammdaten inkl. contacts for a person, performs checks, prepares data for DVUH.
	 * @param $person_id
	 * @param $studiensemester_kurzbz
	 * @return object success with studentinfo or error
	 */
	public function getStammdatenData($person_id, $studiensemester_kurzbz)
	{
		$stammdaten = $this->_ci->PersonModel->getPersonStammdaten($person_id);

		if (hasData($stammdaten))
		{
			$stammdaten = getData($stammdaten);

			$adressen = array();
			$emailliste = array();

			// adresses
			$zustellAdresse = null;
			$heimatAdresse = null;
			$zustellInsertamum = null;
			$heimatInsertamum = null;

			foreach ($stammdaten->adressen as $adresse)
			{
				// only Heimat- or Zustelladressen are sent to DVUH
				if (!$adresse->zustelladresse && !$adresse->heimatadresse)
					continue;

				$addr = array();

				// ort - comes from Gemeinde Feld, from Ort if Gemeinde empty and address not austrian
				$ort = null;
				if (isset($adresse->gemeinde))
					$ort = $adresse->gemeinde;
				elseif($adresse->nation !== 'A')
					$ort = $adresse->ort;

				$addr['ort'] = $ort;
				$addr['plz'] = $adresse->plz;
				$addr['strasse'] = $adresse->strasse;
				$addr['staat'] = $adresse->nation;

				$addrCheck = $this->_ci->dvuhcheckinglib->checkAdresse($addr);

				if (isError($addrCheck))
				{
					return createError(
						"Adresse ungültig: " . getError($addrCheck),
						'adresseUngueltig',
						array(getError($addrCheck)),
						array('adresse_id' => $adresse->adresse_id)
					);
				}

				if ($adresse->zustelladresse)
				{
					if (is_null($zustellInsertamum) || $adresse->insertamum > $zustellInsertamum)
					{
						$addr['typ'] = 'S';
						$zustellInsertamum = $adresse->insertamum;
						$zustellAdresse = $addr;
					}
				}

				if ($adresse->heimatadresse)
				{
					if (is_null($heimatInsertamum) || $adresse->insertamum > $heimatInsertamum)
					{
						$addr['typ'] = 'H';
						$heimatInsertamum = $adresse->insertamum;
						$heimatAdresse = $addr;
					}
				}
			}

			if (isEmptyString($zustellAdresse))
				return createError('Zustelladresse fehlt', 'keineZustelladresse');

			if (isEmptyString($heimatAdresse))
				return createError('Heimatadresse fehlt', 'keineHeimatadresse');

			$adressen[] = $zustellAdresse;
			$adressen[] = $heimatAdresse;

			// private mail
			foreach ($stammdaten->kontakte as $kontakt)
			{
				if ($kontakt->kontakttyp == 'email')
				{
					if (!validateXmlTextValue($kontakt->kontakt))
						return createError(
							'Email enthält Sonderzeichen',
							'emailEnthaeltSonderzeichen',
							null, // issue_fehlertext_params
							array('kontakt_id' => $kontakt->kontakt_id) // issue_resolution_params
						);

					$knt = array();
					$knt['emailadresse'] = $kontakt->kontakt;
					$knt['emailtyp'] = 'PR';
					$emailliste[] = $knt;
				}
			}

			// university mail
			$uids = $this->_ci->fhcmanagementlib->getUids($person_id, $studiensemester_kurzbz);

			if (hasData($uids))
			{
				$uids = getData($uids);

				foreach ($uids as $uid)
				{
					$bsmail = array();
					$bsmail['emailadresse'] = $uid->uid . '@' . DOMAIN;
					$bsmail['emailtyp'] = 'BE';
					$emailliste[] = $bsmail;
				}
			}

			$geschlecht = $this->_ci->dvuhconversionlib->convertGeschlechtToDVUH($stammdaten->geschlecht);

			$studentinfo = array(
				'adressen' => $adressen,
				'beitragstatus' => 'X', // X gilt nur für FHs, Bei Uni anders
				'emailliste' => $emailliste,
				'geburtsdatum' => $stammdaten->gebdatum,
				'geschlecht' => $geschlecht,
				'nachname' => $stammdaten->nachname,
				'staatsbuergerschaft' => $stammdaten->staatsbuergerschaft_code,
				'vorname' => $stammdaten->vorname,
			);

			foreach ($studentinfo as $idx => $item)
			{
				if (!isset($item) || isEmptyString($item))
					return createError('Stammdaten fehlen: ' . $idx, 'stammdatenFehlen', array($idx));
			}

			if (isset($stammdaten->matr_nr))
				$studentinfo['matr_nr'] = $stammdaten->matr_nr;

			if (isset($stammdaten->titelpre))
				$studentinfo['akadgrad'] = $stammdaten->titelpre;

			if (isset($stammdaten->titelpost))
				$studentinfo['akadgradnach'] = $stammdaten->titelpost;

			if (isset($stammdaten->svnr))
				$studentinfo['svnr'] = $stammdaten->svnr;

			if (isset($stammdaten->ersatzkennzeichen) && isEmptyString($stammdaten->svnr))
			{
				if (!$this->_ci->dvuhcheckinglib->checkEkz($stammdaten->ersatzkennzeichen))
				{
					return createError(
						'Ersatzkennzeichen ungültig, muss aus 4 Grossbuchstaben gefolgt von 6 Zahlen bestehen',
						'ersatzkennzeichenUngueltig'
					);
				}

				$studentinfo['ekz'] = $stammdaten->ersatzkennzeichen;
			}

			if (isset($stammdaten->bpk))
			{
				if (!$this->_ci->dvuhcheckinglib->checkBpk($stammdaten->bpk))
				{
					return createError(
						'BPK ungültig, muss aus 27 Zeichen (alphanum. mit / +) gefolgt von = bestehen',
						'bpkUngueltig'
					);
				}

				$studentinfo['bpk'] = $stammdaten->bpk;
			}

			$textValues = array('vorname', 'nachname', 'akadgrad', 'akadgradnach', 'bpk', 'titelpre', 'titelpost', 'ersatzkennzeichen');

			foreach ($textValues as $textValue)
			{
				if (isset($studentinfo[$textValue]) && !validateXmlTextValue($studentinfo[$textValue]))
				{
					return createError("$textValue enthält ungültige Sonderzeichen", 'ungueltigeSonderzeichen', array($textValue));
				}
			}

			return success(
				$studentinfo
			);
		}
		else
			return error("keine Stammdaten gefunden");
	}
}
