<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncmanagement/DVUHManagementLib.php';

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying MasterDat data in DVUH, and updating data in FHC accordingly.
 */
class DVUHMasterDataManagementLib extends DVUHManagementLib
{
	const STATUS_PAID_OTHER_UNIV = '8'; // payment status if paid on another university, for check
	const BUCHUNGSTYP_OEH = 'OEH'; // for nullifying Buchungen after paid on other univ. check
	const ERRORCODE_BPK_MISSING = 'AD10065'; // for auto-update of bpk in fhcomplete

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/BPKManagementLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHStammdatenLib');

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('codex/Oehbeitrag_model', 'OehbeitragModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelmeldung_model', 'MatrikelmeldungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Ekzanfordern_model', 'EkzanfordernModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Kontostaende_model', 'KontostaendeModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHZahlungen_model', 'DVUHZahlungenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHStammdaten_model', 'DVUHStammdatenModel');

		$this->_dbModel = new DB_Model(); // get db
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Sends master data to DVUH. If present, charges are sent with the master data.
	 * @param int $person_id
	 * @param string $studiensemester executed for a certain semester
	 * @param string $matrikelnummer
	 * @param bool $preview if true, only data to post and infos are returned
	 * @return object error or success with infos
	 */
	public function sendMasterData($person_id, $studiensemester, $matrikelnummer = null, $preview = false)
	{
		$infos = array();

		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$all_buchungstypen_kurzbz = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);
		$studiensemester_kurzbz = $this->_ci->dvuhconversionlib->convertSemesterToFHC($studiensemester);
		$dvuh_studiensemester = $this->_ci->dvuhconversionlib->convertSemesterToDVUH($studiensemester);

		// get Buchungen for Vorschreibung
		$buchungenResult = $this->_ci->fhcmanagementlib->getBuchungenOfStudent($person_id, $studiensemester_kurzbz, $all_buchungstypen_kurzbz);

		$vorschreibung = array();

		if (isError($buchungenResult))
			return $buchungenResult;

		// calculate values for ÖH-Beitrag, Studiengebühr
		if (hasData($buchungenResult))
		{
			$buchungen = getData($buchungenResult);

			$vorschreibung = $this->_ci->dvuhstammdatenlib->getVorschreibungData($buchungen, $studiensemester_kurzbz);

			if (isError($vorschreibung))
				return $vorschreibung;

			if (hasData($vorschreibung))
				$vorschreibung = getData($vorschreibung);
		}

		// set Vorschreibungdata
		$oehbeitrag = isset($vorschreibung['oehbeitrag']) ? $vorschreibung['oehbeitrag'] : null;
		$sonderbeitrag = isset($vorschreibung['sonderbeitrag']) ? $vorschreibung['sonderbeitrag'] : null;
		$studiengebuehr = isset($vorschreibung['studiengebuehr']) ? $vorschreibung['studiengebuehr'] : null;
		$valutadatum = isset($vorschreibung['valutadatum']) ? $vorschreibung['valutadatum'] : null;
		$valutadatumnachfrist = isset($vorschreibung['valutadatumnachfrist']) ? $vorschreibung['valutadatumnachfrist'] : null;
		$studiengebuehrnachfrist = isset($vorschreibung['studiengebuehrnachfrist']) ? $vorschreibung['studiengebuehrnachfrist'] : null;

		$studentinfoRes = $this->_ci->dvuhstammdatenlib->getStammdatenData($person_id, $studiensemester_kurzbz);

		// get and reset warnings produced by Stammdatenlib
		$warnings = $this->_ci->dvuhstammdatenlib->readWarnings();

		if (isError($studentinfoRes))
			return $studentinfoRes;

		if (!hasData($studentinfoRes))
			return error('Keine Stammdaten gefunden');

		$studentinfo = getData($studentinfoRes);

		if ($preview)
		{
			$postData = $this->_ci->StammdatenModel->retrievePostData(
				$this->_be,
				$studentinfo,
				$dvuh_studiensemester,
				$matrikelnummer,
				$oehbeitrag,
				$sonderbeitrag,
				$studiengebuehr,
				$valutadatum,
				$valutadatumnachfrist,
				$studiengebuehrnachfrist
			);

			if (isError($postData))
				return $postData;

			return $this->getResponseArr(getData($postData), $infos, $warnings);
		}

		// send Stammdatenmeldung
		$stammdatenResult = $this->_ci->StammdatenModel->post(
			$this->_be,
			$studentinfo,
			$dvuh_studiensemester,
			$matrikelnummer,
			$oehbeitrag,
			$sonderbeitrag,
			$studiengebuehr,
			$valutadatum,
			$valutadatumnachfrist,
			$studiengebuehrnachfrist
		);

		if (isError($stammdatenResult))
			$result = $stammdatenResult;
		elseif (hasData($stammdatenResult))
		{
			$xmlstr = getData($stammdatenResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($parsedObj))
				$result = $parsedObj;
			else
			{
				$infos[] = "Stammdaten für Person Id $person_id erfolgreich in DVUH gespeichert";

				// write Stammdatenmeldung in FHC db
				$stammdatenSaveResult = $this->_ci->DVUHStammdatenModel->insert(
					array(
						'person_id' => $person_id,
						'studiensemester_kurzbz' => $studiensemester_kurzbz,
						'meldedatum' => date('Y-m-d')
					)
				);

				if (isError($stammdatenSaveResult))
					$result = error("Stammdaten erfolgreich in DVUH gespeichert, Fehler beim Speichern der Stammdaten in FHC");

				//if oehbeitrag was sent
				if (isset($oehbeitrag) && isset($vorschreibung['origoehbuchung']) && $oehbeitrag >= 0)
				{
					foreach ($vorschreibung['origoehbuchung'] as $bchng)
					{
						// save date, Buchungsnr and Betrag in sync table
						$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
							array(
								'buchungsdatum' => date('Y-m-d'),
								'buchungsnr' => $bchng->buchungsnr,
								'betrag' => number_format($bchng->betrag, 2, '.', '') // number_format to be sure database saves decimal correclty
							)
						);

						if (isError($zahlungSaveResult))
							$result = error("Speichern der Stammdaten in DVUH erfolgreich, Fehler beim Speichern der ÖH-Beitragszahlung in FHC");
					}
				}

				// if studiengebuehr was sent
				if (isset($studiengebuehr) && isset($vorschreibung['origstudiengebuehrbuchung']) && $studiengebuehr >= 0)
				{
					foreach ($vorschreibung['origstudiengebuehrbuchung'] as $bchng)
					{
						// save date, Buchungsnr and Betrag in sync table
						$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
							array(
								'buchungsdatum' => date('Y-m-d'),
								'buchungsnr' => $bchng->buchungsnr,
								'betrag' => number_format($bchng->betrag, 2, '.', '') // number_format to be sure database saves decimal correclty
							)
						);

						if (isError($zahlungSaveResult))
							$result = error("Speichern der Stammdaten in DVUH erfolgreich, Fehler beim Speichern der Studiengebührzahlung in FHC");
					}
				}

				// check if already paid on another university and nullify open buchung -
				// because payment status gets refreshed after Stammdatenmeldung
				if (isset($buchungen))
				{
					$paidOtherUnivRes = $this->_checkIfPaidOtherUnivAndNullify(
						$person_id,
						$dvuh_studiensemester,
						$matrikelnummer,
						$buchungen,
						$warnings
					);

					if (isError($paidOtherUnivRes))
						return $paidOtherUnivRes;
				}

				// get warnings from result
				$warningsRes = $this->_ci->xmlreaderlib->parseXmlDvuhWarnings($xmlstr);

				if (isError($warningsRes))
					return error('Fehler beim Auslesen der Warnungen');

				$warningCodesToExcludeFromIssues = array();

				if (hasData($warningsRes))
				{
					$parsedWarnings = getData($warningsRes);

					// if no bpk saved in FHC, but a BPK is returned by DVUH in a warning, save it in FHC
					$saveBpkRes = $this->_saveBpkFromDvuhWarning($person_id, $parsedWarnings);

					if (isError($saveBpkRes))
						return error('Fehler beim Speichern der bpk in FHC');

					if (hasData($saveBpkRes))
					{
						$bpkRes = getData($saveBpkRes);
						// if bpk already added automatically, no need to write issue
						$warningCodesToExcludeFromIssues[] = self::ERRORCODE_BPK_MISSING;
						$infos[] = "Neue Bpk ($bpkRes) gespeichert für Person mit Id $person_id";
					}
				}

				if (!isset($result))
					$result = $this->getResponseArr($xmlstr, $infos, $warnings, true, $warningCodesToExcludeFromIssues);
			}
		}
		else
			$result = error('Fehler beim Senden der Stammdaten');

		return $result;
	}


	/**
	 * Checks if student has a Bpk assigned in DVUH.
	 * If yes, existing Bpk is saved in FHC.
	 * If no, Info is logged.
	 * @param int $person_id
	 * @return object error or success with infos
	 */
	public function requestBpk($person_id)
	{
		$bpk = null;
		$infos = array();
		$warnings = array();

		// request BPK only for persons with no BPK
		$personResult = $this->_dbModel->execReadOnlyQuery(
			"SELECT DISTINCT person_id, vorname, nachname, geschlecht, gebdatum, bpk, strasse, plz
				FROM
				public.tbl_person
				LEFT JOIN (SELECT DISTINCT ON (person_id) strasse, plz, person_id
							FROM public.tbl_adresse
							WHERE heimatadresse = TRUE
							ORDER BY person_id, insertamum DESC NULLS LAST
							) addr USING(person_id)
				WHERE tbl_person.person_id = ?
				AND (tbl_person.bpk IS NULL OR tbl_person.bpk = '')",
			array(
				$person_id
			)
		);

		if (hasData($personResult))
		{
			$person = getData($personResult)[0];

			if (!isEmptyString($person->geschlecht))
				$geschlecht = $this->_ci->dvuhconversionlib->convertGeschlechtToDVUH($person->geschlecht);

			$pruefeBpkResult = $this->_ci->bpkmanagementlib->executeBpkRequest(
				array(
					'vorname' => $person->vorname,
					'nachname' => $person->nachname,
					'geburtsdatum' => $person->gebdatum,
					'geschlecht' => $geschlecht
				)
			);

			if (isError($pruefeBpkResult))
			{
				return $pruefeBpkResult;
			}

			if (hasData($pruefeBpkResult))
			{
				$pruefeBpkResultData = getData($pruefeBpkResult);

				// no bpk found
				if (isEmptyString($pruefeBpkResultData['bpk']))
				{
					// if multiple bpks, at least 2 person tags are present
					if ($pruefeBpkResultData['numberPersonsFound'] > 1)
					{
						$warnings[] = error("Mehrere Bpks in DVUH gefunden. Erneuter Versuch mit Adresse.");

						$strasse = getStreetFromAddress($person->strasse);

						// retry getting single bpk with adress
						$pruefeBpkWithAddrResult = $this->_ci->bpkmanagementlib->executeBpkRequest(
							array(
								'vorname' => $person->vorname,
								'nachname' => $person->nachname,
								'geburtsdatum' => $person->gebdatum,
								'geschlecht' => $geschlecht,
								'strasse' => $strasse,
								'plz' => $person->plz
							)
						);

						if (isError($pruefeBpkWithAddrResult))
						{
							return $pruefeBpkWithAddrResult;
						}

						if (hasData($pruefeBpkWithAddrResult))
						{
							$parsedObjAddr = getData($pruefeBpkWithAddrResult);

							if (isEmptyArray($parsedObjAddr['bpk']))
							{
								if ($parsedObjAddr['numberPersonsFound'] > 1)
									$warnings[] = error("Mehrere bPK in DVUH gefunden, auch nach erneuter Anfrage mit Adresse.");
								else
									$warnings[] = error("Keine bPK in DVUH bei Neuanfrage mit Adresse gefunden.");
							}
							else // single bpk found using adress
							{
								$infos[] = "bPK nach Neuanfrage mit Adresse erfolgreich ermittelt!";
								$bpk = $parsedObjAddr['bpk'];
							}
						}
						else
							return error("Fehler bei bPK-Neuanfrage mit Adresse");
					}
					else
						$warnings[] = error("Keine bPK in DVUH gefunden");
				}
				else // bpk found on first try
				{
					$infos[] = "Bpk erfolgreich ermittelt!";
					$bpk = $pruefeBpkResultData['bpk'];
				}

				// if bpk found, save it in FHC db
				if (isset($bpk))
				{
					$bpkSaveResult = $this->_ci->fhcmanagementlib->saveBpkInFhc($person_id, $bpk);

					if (!hasData($bpkSaveResult))
						return error("Fehler beim Speichern der Bpk in FHC");

					$infos[] = "Bpk erfolgreich in FHC gespeichert!";
				}

				return $this->getResponseArr($bpk, $infos, $warnings);
			}
			else
				return error("Fehler beim Ermitteln der Bpk");
		}
		else
			return error("Keine Person ohne Bpk gefunden");
	}

	/**
	 * Sends Matrikelmeldung with ERnP to DVUH. Checks if data is missing.
	 * @param $person_id
	 * @param string $writeonerror
	 * @param string $ausgabedatum Y-m-d
	 * @param string $ausstellBehoerde
	 * @param string $ausstellland country code
	 * @param int $dokumentnr
	 * @param string $dokumenttyp e.g. Reisepass, Personalausweis
	 * @param false $preview if true, only data to post and infos are returned
	 * @return object error or success
	 */
	public function sendMatrikelErnpMeldung(
		$person_id,
		$writeonerror,
		$ausgabedatum,
		$ausstellBehoerde,
		$ausstellland,
		$dokumentnr,
		$dokumenttyp,
		$preview = false
	) {
		$infos = array();

		$requiredFields = array('ausgabedatum', 'ausstellBehoerde', 'ausstellland', 'dokumentnr', 'dokumenttyp');

		foreach ($requiredFields as $requiredField)
		{
			if (!isset($$requiredField))
				return error("Daten fehlen: ".ucfirst($requiredField));
		}

		if ($preview)
		{
			$postData = $this->_ci->MatrikelmeldungModel->retrievePostData(
				$this->_be,
				$person_id,
				$writeonerror,
				$ausgabedatum,
				$ausstellBehoerde,
				$ausstellland,
				$dokumentnr,
				$dokumenttyp
			);

			if (isError($postData))
				return $postData;

			return $this->getResponseArr(getData($postData), $infos);
		}

		$matrikelmeldungResult = $this->_ci->MatrikelmeldungModel->post(
			$this->_be,
			$person_id,
			$writeonerror,
			$ausgabedatum,
			$ausstellBehoerde,
			$ausstellland,
			$dokumentnr,
			$dokumenttyp
		);

		if (isError($matrikelmeldungResult))
			$result = $matrikelmeldungResult;
		elseif (hasData($matrikelmeldungResult))
		{
			$xmlstr = getData($matrikelmeldungResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($parsedObj))
				$result = $parsedObj;
			else
			{
				$infos[] = 'Personenmeldung erfolgreich';
				$result = $this->getResponseArr(
					$xmlstr,
					$infos,
					null,
					true
				);
			}
		}
		else
			$result = error("Fehler beim Senden der Matrikelmeldung");

		return $result;
	}

	/**
	 * Requests EKZ from DVUH, parses returned XML object and returns info/error messages.
	 * @param $person_id
	 * @param string $forcierungskey
	 * @param bool $preview
	 * @return object error or success
	 */
	public function requestEkz($person_id, $forcierungskey = null, $preview = false)
	{
		$infos = array();

		if ($preview)
		{
			$postData = $this->_ci->EkzanfordernModel->retrievePostData($person_id, $forcierungskey);

			if (isError($postData))
				return $postData;

			return $this->getResponseArr(getData($postData), $infos);
		}

		$ekzanfordernResult = $this->_ci->EkzanfordernModel->post($person_id, $forcierungskey);

		if (isError($ekzanfordernResult))
			$result = $ekzanfordernResult;
		elseif (hasData($ekzanfordernResult))
		{
			$xmlstr = getData($ekzanfordernResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh(
				$xmlstr,
				array('uuid', 'responsecode', 'returncode', 'returntext', 'ekz', 'forcierungskey')
			);

			if (isError($parsedObj))
				$result = $parsedObj;
			else
			{
				$parsedObj = getData($parsedObj);

				if (!isset($parsedObj->responsecode[0]) || $parsedObj->responsecode[0] != '200')
				{
					$errortext = 'Fehlerantwort bei EKZ-Anfrage.';
					if (isset($parsedObj->responsetext[0]))
						$errortext .= ' ' . $parsedObj->responsetext[0];

					return error($errortext);
				}

				$infomsg = "EKZanfrage ausgeführt";

				if (isset($parsedObj->returntext[0]->text))
					$infomsg .= ", " . $parsedObj->returntext[0]->text;

				if (isset($parsedObj->ekz[0]))
					$infomsg .= ", EKZ: " . $parsedObj->ekz[0];

				if (isset($parsedObj->forcierungskey[0]))
					$infomsg .= ", Forcierungskey für Anfrage eines neuen EKZ: " . $parsedObj->forcierungskey[0];

				$infos[] = $infomsg;

				$result = $this->getResponseArr(
					$xmlstr,
					$infos,
					null,
					true
				);
			}
		}
		else
			$result = error("Fehler bei EKZ-Anfrage");

		return $result;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Checks if paid on another university, and nullifys Buchung if yes.
	 * @param int $person_id
	 * @param string $dvuh_studiensemester
	 * @param string $matrikelnummer
	 * @param array $buchungen contains Buchungen to nullify
	 * @param array $warnings
	 * @return object success or error with boolean (paid or not)
	 */
	private function _checkIfPaidOtherUnivAndNullify($person_id, $dvuh_studiensemester, $matrikelnummer, $buchungen, &$warnings)
	{
		// check if already paid on another university
		$paidOtherUniv = $this->_checkIfPaidOtherUniv($person_id, $dvuh_studiensemester, $matrikelnummer);

		if (isError($paidOtherUniv))
			return $paidOtherUniv;

		$isPaid = false;

		if (hasData($paidOtherUniv))
		{
			$isPaid = getData($paidOtherUniv)[0];
			if ($isPaid == true)
			{
				$alreadyPaidStr = 'Bereits an anderer Bildungseinrichtung bezahlt.';
				foreach ($buchungen as $buchung)
				{
					$isSentToSap = false;
					$buchungsnr = $buchung->buchungsnr;

					// For ÖH-payments paid on other BE, check if already sent to SAP, add warning if yes
					if ($buchung->buchungstyp_kurzbz == self::BUCHUNGSTYP_OEH)
					{
						$sentToSap = $this->_ci->fhcmanagementlib->checkIfSentToSap($buchungsnr);

						if (isError($sentToSap))
							return $sentToSap;

						if (hasData($sentToSap))
						{
							$isSentToSap = getData($sentToSap)[0];
							if ($isSentToSap === true)
							{
								$warnings[] = createIssueObj(
									"Buchung $buchungsnr ist in SAP gespeichert,"
									." obwohl ÖH-Beitrag bereits an anderer Bildungseinrichtung bezahlt wurde",
									'andereBeBezahltSapGesendet',
									array($buchungsnr)
								);
							}
						}

						// check for nullify flag to see if no nullification is needed
						$nullifyFlag = $this->_ci->config->item('fhc_dvuh_sync_nullify_buchungen_paid_other_univ');

						// if buchung not paid yet, not sent to sap already, nullify the buchung which was already paid
						if ($buchung->bezahlt == '0' && $isSentToSap === false && $nullifyFlag === true)
						{
							// set ÖH-Buchungen to 0 since they don't need to be paid anymore
							$nullifyResult = $this->_ci->fhcmanagementlib->nullifyBuchung($buchung);

							if (isError($nullifyResult))
								return $nullifyResult;

							$alreadyPaidStr .= ' Buchung nullifiziert.';
						}
					}
				}

				$warnings[] = error($alreadyPaidStr);
			}
		}

		return success(array($isPaid));
	}

	/**
	 * Checks if student already paid charges on another university for the semester by calling kontostand.
	 * @param int $person_id
	 * @param string $dvuh_studiensemester
	 * @param string $matrikelnummer passed to kontostaende request, if not set, matrikelnr of person is taken
	 * @return object success with true/false or error
	 */
	private function _checkIfPaidOtherUniv($person_id, $dvuh_studiensemester, $matrikelnummer = null)
	{
		if (!isset($matrikelnummer))
		{
			$this->_ci->PersonModel->addSelect('matr_nr');
			$matrikelnummerRes = $this->_ci->PersonModel->load($person_id);

			if (isError($matrikelnummerRes))
				return $matrikelnummerRes;

			if (hasData($matrikelnummerRes))
			{
				$matrikelnummer = getData($matrikelnummerRes)[0]->matr_nr;
			}
		}

		$kontostandRes = $this->_ci->KontostaendeModel->get($this->_be, $dvuh_studiensemester, $matrikelnummer);

		if (isError($kontostandRes))
			$result = $kontostandRes;
		elseif (hasData($kontostandRes))
		{
			$statusRes = $this->_ci->xmlreaderlib->parseXml(getData($kontostandRes), array('bezahlstatus'));

			if (hasData($statusRes))
			{
				$status = getData($statusRes);

				if (!isEmptyArray($status->bezahlstatus))
				{
					if ($status->bezahlstatus[0] == self::STATUS_PAID_OTHER_UNIV)
						$result = success(array(true));
					else
						$result = success(array(false));
				}
				else
					$result = success(array(false));
			}
			else
				$result = error('Fehler beim Auslesen des Kontostandes');
		}
		else
			$result = success(array(false));

		return $result;
	}

	/**
	 * Saves bPK in FHC db if DVUH returned warning with bPK and there is no bpk in FHC.
	 * @param int $person_id
	 * @param array $parsedWarnings contains warning objects with error info and bpk, as returned from DVUH
	 * @return object success with bpk if saved, or error
	 */
	private function _saveBpkFromDvuhWarning($person_id, $parsedWarnings)
	{
		$result = success(array());
		$this->_ci->PersonModel->addSelect('bpk');
		$bpkRes = $this->_ci->PersonModel->load($person_id);

		if (isError($bpkRes))
			return $bpkRes;

		if (hasData($bpkRes) && isEmptyString(getData($bpkRes)[0]->bpk))
		{
			foreach ($parsedWarnings as $warning)
			{
				if ($warning->fehlernummer == self::ERRORCODE_BPK_MISSING &&
					isset($warning->feldinhalt) && !isEmptyString($warning->feldinhalt))
				{
					$bpkUpdateRes = $this->_ci->fhcmanagementlib->saveBpkInFhc($person_id, $warning->feldinhalt);

					if (hasData($bpkUpdateRes))
					{
						$result = success($warning->feldinhalt);
					}
				}
			}
		}

		return $result;
	}
}
