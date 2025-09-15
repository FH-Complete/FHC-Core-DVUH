<?php

require_once 'DVUHErrorProducerLib.php';

/**
 * Library for retrieving Stammdaten data from FHC for DVUH.
 * Extracts data from FHC db, performs data quality checks and puts data in DVUH form.
 */
class DVUHStammdatenLib extends DVUHErrorProducerLib
{
	protected $_ci;

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
		$this->_ci->load->model('codex/Oehbeitrag_model', 'OehbeitragModel');

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
		// get config flag for sending university mails
		$send_university_mails = $this->_ci->config->item('fhc_dvuh_send_university_mail');

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
					$this->addError(
						"Adresse ungültig: " . getError($addrCheck),
						'adresseUngueltig',
						array(getError($addrCheck)),
						array('adresse_id' => $adresse->adresse_id)
					);
				}

				// get newest Zustelladresse (defined by insertamum field)
				if ($adresse->zustelladresse)
				{
					if (is_null($zustellInsertamum) || $adresse->insertamum > $zustellInsertamum)
					{
						$addr['typ'] = 'S';
						$zustellInsertamum = $adresse->insertamum;
						$zustellAdresse = $addr;
					}
				}

				// and Heimatadresse
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
				$this->addError('Zustelladresse fehlt', 'keineZustelladresse');

			if (isEmptyString($heimatAdresse))
				$this->addError('Heimatadresse fehlt', 'keineHeimatadresse');

			$adressen[] = $zustellAdresse;
			$adressen[] = $heimatAdresse;

			// private mail
			foreach ($stammdaten->kontakte as $kontakt)
			{
				if ($kontakt->kontakttyp == 'email')
				{
					if (!validateXmlTextValue($kontakt->kontakt))
					{
						$this->addError(
							'Email enthält Sonderzeichen',
							'emailEnthaeltSonderzeichen',
							null, // issue_fehlertext_params
							array('kontakt_id' => $kontakt->kontakt_id) // issue_resolution_params
						);
					}

					$knt = array();
					$knt['emailadresse'] = $kontakt->kontakt;
					$knt['emailtyp'] = 'PR';
					$emailliste[] = $knt;
				}
			}

			// university mail
			if ($send_university_mails !== false)
			{
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
					$this->addError('Stammdaten fehlen: ' . $idx, 'stammdatenFehlen', array($idx));
			}

			if (isset($stammdaten->matr_nr))
				$studentinfo['matr_nr'] = $stammdaten->matr_nr;

			if (isset($stammdaten->titelpre))
			{
				if (!$this->_ci->dvuhcheckinglib->checkTitel($stammdaten->titelpre))
				{
					$this->addError(
						'Titel pre hat ungültiges Format',
						'titelpreUngueltig'
					);
				}

				$studentinfo['akadgrad'] = $stammdaten->titelpre;
			}

			if (isset($stammdaten->titelpost))
			{
				if (!$this->_ci->dvuhcheckinglib->checkTitel($stammdaten->titelpost))
				{
					$this->addError(
						'Titel post hat ungültiges Format',
						'titelpostUngueltig'
					);
				}

				$studentinfo['akadgradnach'] = $stammdaten->titelpost;
			}

			if (isset($stammdaten->ersatzkennzeichen) && isEmptyString($stammdaten->svnr))
			{
				if (!$this->_ci->dvuhcheckinglib->checkEkz($stammdaten->ersatzkennzeichen))
				{
					$this->addError(
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
					$this->addError(
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
					$this->addError("$textValue enthält ungültige Sonderzeichen", 'ungueltigeSonderzeichen', array($textValue));
				}
			}

			if ($this->hasError())
				return error($this->readErrors());

			return success($studentinfo);
		}
		else
			return error("keine Stammdaten gefunden");
	}

	/*
	 * Gets Charge (Vorschreibung) data from FHC Buchungen as needed by DVUH.
	 * @param int $person_id only amounts for this student are returned
	 * @param string $studiensemester_kurzbz mainly for getting pre-defined amounts for a semester
	 * @param array $buchungstypen_kurzbz get only Buchungen with this types
	 * @return success with vorschreibung data or error (e.g. when checks failed)
	 */
	public function getVorschreibungData($person_id, $studiensemester_kurzbz, $buchungstypen_kurzbz = null)
	{
		// get configs
		$valutadatum_days = $this->_ci->config->item('fhc_dvuh_sync_days_valutadatum');
		$valutadatumnachfrist_days = $this->_ci->config->item('fhc_dvuh_sync_days_valutadatumnachfrist');
		$studiengebuehrnachfrist_euros = $this->_ci->config->item('fhc_dvuh_sync_euros_studiengebuehrnachfrist');
		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');

		if (!isset($buchungstypen_kurzbz))
			$buchungstypen_kurzbz = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);

		// get Buchungen for Vorschreibung
		$buchungen = array();
		$vorschreibung = array();

		$buchungenRes = $this->_ci->fhcmanagementlib->getBuchungenOfStudent($person_id, $studiensemester_kurzbz, $buchungstypen_kurzbz);

		if (isError($buchungenRes))
			return $buchungenRes;

		if (hasData($buchungenRes))
			$buchungen = getData($buchungenRes);

		// fill vorschreibung data from Buchungen
		foreach ($buchungen as $buchung)
		{
			// add Vorschreibung
			$studierendenBeitragAmount = 0;
			$versicherungBeitragAmount = 0;
			$beitragAmount = $buchung->betrag;

			// if buchung is oehbeitrag
			if (isset($buchungstypen['oehbeitrag']) && in_array($buchung->buchungstyp_kurzbz, $buchungstypen['oehbeitrag']))
			{
				// get pre-defined oehbeitrag amounts from FHC db
				$oehbeitragAmountsRes = $this->_ci->OehbeitragModel->getByStudiensemester($studiensemester_kurzbz);

				if (isError($oehbeitragAmountsRes))
					return $oehbeitragAmountsRes;

				if (hasData($oehbeitragAmountsRes))
				{
					$oehbeitragAmounts = getData($oehbeitragAmountsRes)[0];
					$studierendenBeitragAmount = $oehbeitragAmounts->studierendenbeitrag;

					// no insurance if oehbeitrag is 0 or insurance is greater than ÖH-Beitrag
					if ($beitragAmount < 0 && $oehbeitragAmounts->versicherung < abs($beitragAmount))
					{
						$versicherungBeitragAmount = $oehbeitragAmounts->versicherung;

						// insurance must be deducted, plus because beitragAmount is negative
						$beitragAmount += $versicherungBeitragAmount;
					}
				}
				else
				{
					// no oehbeitrag amounts predefined
					$this->addError(
						"Keine Höhe des Öhbeiträgs in Öhbeitragstabelle für Studiensemester $studiensemester_kurzbz spezifiziert,"
						."Buchung " . $buchung->buchungsnr,
						'oehbeitragNichtSpezifiziert',
						array($studiensemester_kurzbz, $buchung->buchungsnr),
						array('studiensemester_kurzbz' => $studiensemester_kurzbz)
					);
				}

				$dvuh_buchungstyp = 'oehbeitrag';
			}
			// if studiengebuehr, just normally add the amount
			elseif (isset($buchungstypen['studiengebuehr']) && in_array($buchung->buchungstyp_kurzbz, $buchungstypen['studiengebuehr']))
				$dvuh_buchungstyp = 'studiengebuehr';

			if (!isset($vorschreibung[$dvuh_buchungstyp]))
				$vorschreibung[$dvuh_buchungstyp] = 0;

			// add the amount to sum
			$vorschreibung[$dvuh_buchungstyp] += $beitragAmount;

			// add additional fields depending on oehbeitrag/studiengebuehr
			if ($dvuh_buchungstyp == 'oehbeitrag')
			{
				if (!isset($vorschreibung['sonderbeitrag']))
					$vorschreibung['sonderbeitrag'] = 0;

				$vorschreibung['sonderbeitrag'] += (float)$versicherungBeitragAmount;
				$valutadatum = date('Y-m-d', strtotime($buchung->buchungsdatum . ' + ' . $valutadatum_days . ' days'));
				$vorschreibung['valutadatum'] = $valutadatum;
				$vorschreibung['valutadatumnachfrist'] = // Nachfrist is also taken into account by DVUH for Bezahlstatus
					date('Y-m-d', strtotime($valutadatum . ' + ' . $valutadatumnachfrist_days . ' days'));
				$vorschreibung['origoehbuchung'][] = $buchung;// add original buchung e.g. for tracking back original data

				// warning if amount in Buchung after Versicherung deduction not equal to amount in oehbeitrag table
				if (-1 * $beitragAmount != $studierendenBeitragAmount && $beitragAmount < 0)
				{
					$vorgeschrBeitrag = number_format(-1 * $beitragAmount, 2, ',', '.');
					$festgesBeitrag = number_format($studierendenBeitragAmount, 2, ',', '.');
					$this->addWarning(
						"Vorgeschriebener Beitrag $vorgeschrBeitrag nach Abzug der Versicherung stimmt nicht mit"
						." festgesetztem Betrag für Semester, $festgesBeitrag, überein",
						'vorgeschrBetragUngleichFestgesetzt',
						array($vorgeschrBeitrag, $festgesBeitrag),
						array('buchungsnr' => $buchung->buchungsnr, 'studiensemester_kurzbz' => $studiensemester_kurzbz)
					);
				}
			}
			elseif ($dvuh_buchungstyp == 'studiengebuehr')
			{
				$vorschreibung['studiengebuehrnachfrist'] = $vorschreibung[$dvuh_buchungstyp] - $studiengebuehrnachfrist_euros;
				$vorschreibung['origstudiengebuehrbuchung'][] = $buchung; // add original buchung e.g. for tracking back original data
			}
		}

		// convert Vorschreibungdata to DVUH format

		$invalidBuchungstypen = array();
		$invalidBuchungstypenKeys = array();
		if (isset($vorschreibung['oehbeitrag']))
		{
			$vorschreibung['oehbeitrag'] = abs($vorschreibung['oehbeitrag']) * 100;

			// check oehbeitrag for validity
			if (!$this->_ci->dvuhcheckinglib->checkOehBeitrag($vorschreibung['oehbeitrag']))
			{
				$invalidBuchungstypen = array_merge($invalidBuchungstypen, $buchungstypen['oehbeitrag']);
				$invalidBuchungstypenKeys[] = 'oehbeitrag';
			}
		}

		if (isset($vorschreibung['studiengebuehr']))
		{
			$vorschreibung['studiengebuehr'] = abs($vorschreibung['studiengebuehr']) * 100;

			// check studiengebuehr for validity
			if (!$this->_ci->dvuhcheckinglib->checkStudiengebuehr($vorschreibung['studiengebuehr']))
			{
				$invalidBuchungstypen = array_merge($invalidBuchungstypen, $buchungstypen['studiengebuehr']);
				$invalidBuchungstypenKeys[] = 'studiengebuehr';
			}
		}

		// write error if invalid, pass concerning Buchungstypen for resolving
		if (!isEmptyArray($invalidBuchungstypen))
		{
			$buchungstypen_str = implode(', ', $invalidBuchungstypenKeys);

			$this->addError(
				"Vorschreibung ungültig, Zahlungstypen: ".$buchungstypen_str,
				'vorschreibungUngueltig',
				array($buchungstypen_str),
				array('studiensemester_kurzbz' => $studiensemester_kurzbz, 'buchungstypen' => $invalidBuchungstypen)
			);
		}

		if (isset($vorschreibung['sonderbeitrag']))
			$vorschreibung['sonderbeitrag'] *= 100;

		if (isset($vorschreibung['studiengebuehrnachfrist']))
			$vorschreibung['studiengebuehrnachfrist'] = abs($vorschreibung['studiengebuehrnachfrist']) * 100;

		if ($this->hasError())
			return error($this->readErrors());

		return success($vorschreibung);
	}
}
