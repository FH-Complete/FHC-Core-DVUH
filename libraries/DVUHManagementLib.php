<?php

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying data in DVUH, and updating data in FHC accordingly.
 */
class DVUHManagementLib
{
	const STATUS_PAID_OTHER_UNIV = '8'; // payment status if paid on another university, for check
	const BUCHUNGSTYP_OEH = 'OEH'; // for nullifying Buchungen after paid on other univ. check
	const ERRORCODE_BPK_MISSING = 'AD10065'; // for auto-update of bpk in fhcomplete
	const STORNO_MELDESTATUS = 'O';

	private $_ci; // code igniter instance
	private $_be; // Bildungseinrichtung code

	// Statuscodes returned when checking Matrikelnr, resulting actions are array keys
	private $_matrnr_statuscodes = array(
		'assignNew' => array('1', '5'),
		'saveExisting' => array('3'),
		'error' => array('2', '4', '6')
	);

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FeedReaderLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/BPKManagementLib');

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('crm/Konto_model', 'KontoModel');
		$this->_ci->load->model('codex/Oehbeitrag_model', 'OehbeitragModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Zahlung_model', 'ZahlungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Studium_model', 'StudiumModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelmeldung_model', 'MatrikelmeldungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefungsaktivitaeten_model', 'PruefungsaktivitaetenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefungsaktivitaeten_loeschen_model', 'PruefungsaktivitaetenLoeschenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefebpk_model', 'PruefebpkModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Ekzanfordern_model', 'EkzanfordernModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Kontostaende_model', 'KontostaendeModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHZahlungen_model', 'DVUHZahlungenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHStammdaten_model', 'DVUHStammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHStudiumdaten_model', 'DVUHStudiumdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHPruefungsaktivitaeten_model', 'DVUHPruefungsaktivitaetenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHMatrikelnummerreservierung_model', 'DVUHMatrikelnummerreservierungModel');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$this->_be = $this->_ci->config->item('fhc_dvuh_be_code');

		$this->_dbModel = new DB_Model(); // get db
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Checks if student has a Matrikelnummer assigned in DVUH.
	 * If yes, existing Matrnr is saved in FHC.
	 * If no, new Matrnr is requested and saved with active=false in FHC.
	 * Sends Stammdatenmeldung (see sendAndUpdateMatrikelnummer).
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz executed for a certain semester
	 * @return object error or success with infos
	 */
	public function requestMatrikelnummer($person_id, $studiensemester_kurzbz)
	{
		$result = null;
		$infos = array();
		$warnings = array();

		// reset matrikelnr to NULL if it is an old, unused, non-active Matrikelnr so a new, current one can be assigned
		$resetMatrikelnummerRes = $this->_ci->fhcmanagementlib->resetInactiveMatrikelnummer($person_id, $studiensemester_kurzbz);

		if (isError($resetMatrikelnummerRes))
			return $resetMatrikelnummerRes;

		if (hasData($resetMatrikelnummerRes))
			$infos[] = "Alte, inaktive Matrikelnummer gelöscht für Person $person_id";

		// request Matrikelnr only for persons with prestudent in given Semester and no matrikelnr
		$personResult = $this->_dbModel->execReadOnlyQuery("
								SELECT tbl_person.*
								FROM public.tbl_person
								JOIN public.tbl_prestudent USING (person_id)
								JOIN public.tbl_prestudentstatus USING (prestudent_id)
								WHERE person_id = ?
									AND studiensemester_kurzbz = ?
									AND tbl_person.matr_nr IS NULL	
								LIMIT 1",
			array(
				$person_id, $studiensemester_kurzbz
			)
		);

		if (hasData($personResult))
		{
			$person = getData($personResult)[0];

			$matrPruefungResult = $this->_ci->MatrikelpruefungModel->get(
				!isEmptyString($person->bpk) ? $person->bpk : null,
				$person->ersatzkennzeichen,
				$person->gebdatum,
				null, // matrikelnummer
				$person->nachname,
				!isEmptyString($person->svnr) ? $person->svnr : null,
				$person->vorname
			);

			if (isError($matrPruefungResult))
			{
				return $matrPruefungResult;
			}
			elseif (hasData($matrPruefungResult))
			{
				$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh(getData($matrPruefungResult), array('statuscode', 'statusmeldung', 'matrikelnummer'));

				if (isError($parsedObj))
					return $parsedObj;

				if (hasData($parsedObj))
				{
					$parsedObj = getData($parsedObj);
					$statuscode = count($parsedObj->statuscode) > 0 ? $parsedObj->statuscode[0] : '';
					$statusmeldung = count($parsedObj->statusmeldung) > 0 ? $parsedObj->statusmeldung[0] : '';
					$matrikelnummer = count($parsedObj->matrikelnummer) > 0 ? $parsedObj->matrikelnummer[0] : '';

					/**
					 *
					 * Code 1: Keine Studierendendaten gefunden, neue Matrikelnummer mit matrikelreservierung.xml aus eigenen Kontigent anfordern.
					 *
					 * Code 2: Matrikelnummer gesperrt keine Alternative, BRZ verständigen
					 *
					 * Code 3: Matrikelnummer im Status vergeben gefunden, Matrikelnummer übernehmen
					 *
					 * Code 4: Zur Matrikelnummer liegt eine aktive Meldung im aktuellen Semester vor, Matrikelnummer in Evidenz halten
					 *
					 * Code 5: Zur Matrikelnummer liegt ausschließlich eine Meldung in einen vergangenen Semester vor, es kam daher nie zur Zulassung.Eine neue Matrikelnummer aus dem eigenen Kontigent kann vergeben werden.
					 *
					 * Code 6: Mehr als eine Matrikelnummer wurde gefunden. Der Datenverbund kann keine eindeutige Matrikelnummer feststellen.
					 */

					if (in_array($statuscode, $this->_matrnr_statuscodes['assignNew'])) // no existing Matrikelnr - new one must be assigned
					{
						$this->_ci->StudiensemesterModel->addSelect('studienjahr_kurzbz');
						$studienjahrResult = $this->_ci->StudiensemesterModel->load($studiensemester_kurzbz);

						if (hasData($studienjahrResult))
						{
							$sj = getData($studienjahrResult)[0];
							$sj = substr($sj->studienjahr_kurzbz, 0, 4);
							$reservedMatrnrStr = null;

							// check if there is a free Matrikelnummer in FHC reserved
							$this->_ci->DVUHMatrikelnummerreservierungModel->addOrder('insertamum, matrikelnummer');
							$this->_ci->DVUHMatrikelnummerreservierungModel->addLimit(1);
							$fhcMatrikelnummerreservierung = $this->_ci->DVUHMatrikelnummerreservierungModel->load(array('jahr' => $sj));

							if (isError($fhcMatrikelnummerreservierung))
								return $fhcMatrikelnummerreservierung;

							if (hasData($fhcMatrikelnummerreservierung))
							{
								$reservedMatrnrStr = getData($fhcMatrikelnummerreservierung)[0]->matrikelnummer;
							}
							else
							{
								// reserve new Matrikelnummer in DVUH
								$anzahlReservierungen = 1;

								$matrReservResult = $this->_ci->MatrikelreservierungModel->post($this->_be, $sj, $anzahlReservierungen);

								if (hasData($matrReservResult))
								{
									$reservedMatrnr = $this->_ci->xmlreaderlib->parseXMLDvuh(getData($matrReservResult), array('matrikelnummer'));

									if (isError($reservedMatrnr))
										return $reservedMatrnr;

									if (hasData($reservedMatrnr))
									{
										$reservedMatrnr = getData($reservedMatrnr);
										$reservedMatrnrStr = $reservedMatrnr->matrikelnummer[0];

										// save matrnr in (intermediary) FHC table
										$fhcAddMatrikelnummerreservierung = $this->_ci->DVUHMatrikelnummerreservierungModel->addMatrikelnummerreservierung($reservedMatrnrStr, $sj);

										if (isError($fhcAddMatrikelnummerreservierung))
											return $fhcAddMatrikelnummerreservierung;
									}
									else
										return error("Es konnte keine Matrikelnummer reserviert werden");
								}
								else
									return error("Fehler beim Reservieren der Matrikelnummer");
							}

							if (isEmptyString($reservedMatrnrStr))
								return error("Keine Matrikelnummer zum Zuweisen gefunden");

							// send Matrikelnummer to DVUH and update in FHC person
							$sendUpdateMatrRes = $this->_sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $reservedMatrnrStr, false, $infos);

							if (isError($sendUpdateMatrRes))
								return $sendUpdateMatrRes;

							if (hasData($sendUpdateMatrRes))
							{
								// if successfully assigned Matrikelnummer, delete it in FHC Matrikelnummerreservierung table
								$fhcAddMatrikelnummerreservierung = $this->_ci->DVUHMatrikelnummerreservierungModel->delete(
									array(
										$reservedMatrnrStr,
										$sj
									)
								);

								if (isError($fhcAddMatrikelnummerreservierung))
									return $fhcAddMatrikelnummerreservierung;

								$updateMatrnrObj = getData($sendUpdateMatrRes);

								// merge infos from save matrnr result
								$infos = array_merge($updateMatrnrObj['infos'], $infos);
								$warnings = $updateMatrnrObj['warnings'];
							}
							else
								return error("Fehler bei Matrikelnummeraktualisierung");

							$result = $this->_getResponseArr(null, $infos, $warnings);
						}
						else
						{
							return error("Studienjahr nicht gefunden");
						}
					}
					elseif (in_array($statuscode, $this->_matrnr_statuscodes['saveExisting'])) // Matrikelnr already existing in DVUH -> save in FHC
					{
						if (is_numeric($matrikelnummer))
						{
							$sendUpdateMatrRes = $this->_sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $matrikelnummer, true, $infos);

							if (isError($sendUpdateMatrRes))
								return $sendUpdateMatrRes;

							if (hasData($sendUpdateMatrRes))
							{
								$updateMatrnrObj = getData($sendUpdateMatrRes);

								// merge infos from save matrnr result
								$infos = array_merge($updateMatrnrObj['infos'], $infos);
								$warnings = $updateMatrnrObj['warnings'];
							}
							$result = $this->_getResponseArr(null, $infos, $warnings);
						}
						else
							return error("ungültige Matrikelnummer");
					}
					elseif (in_array($statuscode, $this->_matrnr_statuscodes['error']))
					{
						return createExternalError($statusmeldung, 'MATRNR_STATUS_'.$statuscode);
					}
					else
					{
						if (!isEmptyString($statusmeldung))
						{
							$infos[] = $statusmeldung;
							$result = $this->_getResponseArr(null, $infos);
						}
						else
							return error("Unbekannter Matrikelnr-Statuscode");
					}
				}
				else
					return error("Matrikelnummernanfrage konnte nicht geparst werden");
			}
			else
				return error("Matrikelnummernanfrage lieferte keine Daten");
		}
		else
		{
			$infos[] = "Keine valide Person für Matrikelnummernanfrage gefunden";
			$result = $this->_getResponseArr(null, $infos);
		}

		return $result;
	}

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
		$warnings = array();

		$valutadatum_days = $this->_ci->config->item('fhc_dvuh_sync_days_valutadatum');
		$valutadatumnachfrist_days = $this->_ci->config->item('fhc_dvuh_sync_days_valutadatumnachfrist');
		$studiengebuehrnachfrist_euros = $this->_ci->config->item('fhc_dvuh_sync_euros_studiengebuehrnachfrist');
		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$all_buchungstypen = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);
		$studiensemester_kurzbz = $this->_ci->dvuhsynclib->convertSemesterToFHC($studiensemester);
		$dvuh_studiensemester = $this->_ci->dvuhsynclib->convertSemesterToDVUH($studiensemester);

		// get Buchungen
		$buchungenResult = $this->_dbModel->execReadOnlyQuery("
								SELECT person_id, studiengang_kz, buchungsdatum, betrag, buchungsnr, zahlungsreferenz, buchungstyp_kurzbz,
								       studiensemester_kurzbz, buchungstext, buchungsdatum,
										(SELECT count(*) FROM public.tbl_konto kto /* no Gegenbuchung yet */
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr) AS bezahlt
								FROM public.tbl_konto
								WHERE person_id = ?
								  AND studiensemester_kurzbz = ?
								  AND buchungsnr_verweis IS NULL
								  AND betrag <= 0
								  /*AND NOT EXISTS (SELECT 1 FROM public.tbl_konto kto /* no Gegenbuchung yet */
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr
								      				LIMIT 1)*/
/*								  AND NOT EXISTS (SELECT 1 FROM sync.tbl_dvuh_zahlungen /* payment not yet sent to DVUH */
									WHERE buchungsnr = (SELECT kto.buchungsnr FROM public.tbl_konto kto
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr
								      				LIMIT 1)
									AND betrag > 0
									LIMIT 1)*/
								  AND EXISTS (SELECT 1 FROM public.tbl_prestudent
								      			JOIN public.tbl_prestudentstatus USING (prestudent_id)
								      			WHERE tbl_prestudent.person_id = tbl_konto.person_id
								      			AND tbl_prestudentstatus.studiensemester_kurzbz = tbl_konto.studiensemester_kurzbz)
								  AND buchungstyp_kurzbz IN ?
								  ORDER BY buchungsdatum, buchungsnr",
			array(
				$person_id,
				$studiensemester_kurzbz,
				$all_buchungstypen
			)
		);

		$vorschreibung = array();

		if (isError($buchungenResult))
			return $buchungenResult;

		// calculate values for ÖH-Beitrag, Studiengebühr
		if (hasData($buchungenResult))
		{
			$buchungen = getData($buchungenResult);

			foreach ($buchungen as $buchung)
			{
				// add Vorschreibung
				$studierendenBeitragAmount = 0;
				$versicherungBeitragAmount = 0;
				$beitragAmount = $buchung->betrag;

				if (in_array($buchung->buchungstyp_kurzbz, $buchungstypen['oehbeitrag']))
				{
					$oehbeitragAmountsRes = $this->_ci->OehbeitragModel->getByStudiensemester($studiensemester_kurzbz);

					if (isError($oehbeitragAmountsRes))
						return $oehbeitragAmountsRes;

					if (hasData($oehbeitragAmountsRes))
					{
						$oehbeitragAmounts = getData($oehbeitragAmountsRes)[0];
						$studierendenBeitragAmount = $oehbeitragAmounts->studierendenbeitrag;

						if ($beitragAmount < 0) // no insurance if oehbeitrag is 0
						{
							$versicherungBeitragAmount = $oehbeitragAmounts->versicherung;

							// plus because beitragAmount is negative
							$beitragAmount += $versicherungBeitragAmount;
						}
					}
					else
					{
						return createError(
							"Keine Höhe des Öhbeiträgs in Öhbeitragstabelle für Studiensemester $studiensemester_kurzbz spezifiziert, Buchung " . $buchung->buchungsnr,
							'oehbeitragNichtSpezifiziert',
							array($studiensemester_kurzbz, $buchung->buchungsnr),
							array('studiensemester_kurzbz' => $studiensemester_kurzbz)
						);
					}

					$dvuh_buchungstyp = 'oehbeitrag';
				}
				elseif ((in_array($buchung->buchungstyp_kurzbz, $buchungstypen['studiengebuehr'])))
					$dvuh_buchungstyp = 'studiengebuehr';

				if (!isset($vorschreibung[$dvuh_buchungstyp]))
					$vorschreibung[$dvuh_buchungstyp] = 0;

				$vorschreibung[$dvuh_buchungstyp] += $beitragAmount;

				if ($dvuh_buchungstyp == 'oehbeitrag')
				{
					if (!isset($vorschreibung['sonderbeitrag']))
						$vorschreibung['sonderbeitrag'] = 0;

					$vorschreibung['sonderbeitrag'] += (float) $versicherungBeitragAmount;
					$valutadatum = date('Y-m-d', strtotime($buchung->buchungsdatum . ' + ' . $valutadatum_days . ' days'));
					$vorschreibung['valutadatum'] = $valutadatum;
					$vorschreibung['valutadatumnachfrist'] = // Nachfrist is also taken into account by DVUH for Bezahlstatus
						date('Y-m-d', strtotime($valutadatum . ' + ' . $valutadatumnachfrist_days . ' days'));
					$vorschreibung['origoehbuchung'][] = $buchung;

					// warning if amount in Buchung after Versicherung deduction not equal to amount in oehbeitrag table
					if (-1 * $beitragAmount != $studierendenBeitragAmount && $beitragAmount < 0)
					{
						$vorgeschrBeitrag = number_format(-1 * $beitragAmount, 2, ',', '.');
						$festgesBeitrag = number_format($studierendenBeitragAmount, 2, ',', '.');
						$warnings[] = createError(
							"Vorgeschriebener Beitrag $vorgeschrBeitrag nach Abzug der Versicherung stimmt nicht mit festgesetztem Betrag für Semester, "
										. "$festgesBeitrag, überein",
							'vorgeschrBetragUngleichFestgesetzt',
							array($vorgeschrBeitrag, $festgesBeitrag),
							array('buchungsnr' => $buchung->buchungsnr, 'studiensemester_kurzbz' => $studiensemester_kurzbz)
						);
					}
				}
				elseif ($dvuh_buchungstyp == 'studiengebuehr')
				{
					$vorschreibung['studiengebuehrnachfrist'] = $vorschreibung[$dvuh_buchungstyp] - $studiengebuehrnachfrist_euros;
					$vorschreibung['origstudiengebuehrbuchung'][] = $buchung;
				}
			}
		}

		// convert Vorschreibungdata
		$oehbeitrag = isset($vorschreibung['oehbeitrag']) ? abs($vorschreibung['oehbeitrag']) * 100 : null;
		$sonderbeitrag = isset($vorschreibung['sonderbeitrag']) ? $vorschreibung['sonderbeitrag'] * 100 : null;
		$studiengebuehr = isset($vorschreibung['studiengebuehr']) ? abs($vorschreibung['studiengebuehr']) * 100 : null;
		$valutadatum = isset($vorschreibung['valutadatum']) ? $vorschreibung['valutadatum'] : null;
		$valutadatumnachfrist = isset($vorschreibung['valutadatumnachfrist']) ? $vorschreibung['valutadatumnachfrist'] : null;
		$studiengebuehrnachfrist = isset($vorschreibung['studiengebuehrnachfrist']) ? abs($vorschreibung['studiengebuehrnachfrist'])  * 100 : null;

		if ($preview)
		{
			$postData = $this->_ci->StammdatenModel->retrievePostData($this->_be, $person_id, $dvuh_studiensemester, $matrikelnummer, $oehbeitrag,
				$sonderbeitrag, $studiengebuehr, $valutadatum, $valutadatumnachfrist, $studiengebuehrnachfrist);

			if (isError($postData))
				return $postData;

			return $this->_getResponseArr(getData($postData), $infos, $warnings);
		}

		// send Stammdatenmeldung
		$stammdatenResult = $this->_ci->StammdatenModel->post($this->_be, $person_id, $dvuh_studiensemester, $matrikelnummer, $oehbeitrag,
			$sonderbeitrag, $studiengebuehr, $valutadatum, $valutadatumnachfrist, $studiengebuehrnachfrist);

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
				if (isset($vorschreibung['oehbeitrag']) && isset($vorschreibung['origoehbuchung'])
					&& $vorschreibung['oehbeitrag'] <= 0)
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
				if (isset($vorschreibung['studiengebuehr']) && isset($vorschreibung['origstudiengebuehrbuchung'])
					&& $vorschreibung['studiengebuehr'] <= 0)
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
						$warningCodesToExcludeFromIssues[] = self::ERRORCODE_BPK_MISSING; // if bpk already added automatically, no need to write issue
						$infos[] = "Neue Bpk ($bpkRes) gespeichert für Person mit Id $person_id";
					}
				}

				if (!isset($result))
					$result = $this->_getResponseArr($xmlstr, $infos, $warnings, true, $warningCodesToExcludeFromIssues);
			}
		}
		else
			$result = error('Fehler beim Senden der Stammdaten');

		return $result;
	}

	/**
	 * Sends payment to DVUH, one request for each payment type. Performs checks before payment.
	 * @param int $person_id
	 * @param string $studiensemester executed for a certain semester
	 * @param bool $preview if true, only data to post and infos are returned
	 * @return object error or success with infos
	 */
	public function sendPayment($person_id, $studiensemester, $preview = false)
	{
		$result = null;
		$infos = array();
		$zahlungenResArr = array();
		$studiensemester_kurzbz = $this->_ci->dvuhsynclib->convertSemesterToFHC($studiensemester);

		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$all_buchungstypen = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);

		// get paid Buchungen
		$buchungenResult = $this->_dbModel->execReadOnlyQuery("
								SELECT matr_nr, buchungsnr, buchungsdatum, betrag, buchungsnr_verweis, 
								       zahlungsreferenz, buchungstyp_kurzbz, studiensemester_kurzbz, matr_nr,
								       sum(betrag) OVER (PARTITION BY buchungsnr_verweis) AS summe_buchungen
								FROM public.tbl_konto
								JOIN public.tbl_person USING (person_id)
								WHERE person_id = ?
								  AND studiensemester_kurzbz = ?
								  AND buchungsnr_verweis IS NOT NULL
								  AND betrag > 0
								  AND EXISTS (SELECT 1 FROM public.tbl_prestudent
								      			JOIN public.tbl_prestudentstatus USING (prestudent_id)
								      			WHERE tbl_prestudent.person_id = tbl_konto.person_id
								      			AND tbl_prestudentstatus.studiensemester_kurzbz = tbl_konto.studiensemester_kurzbz)
								  AND NOT EXISTS (SELECT 1 from sync.tbl_dvuh_zahlungen /* payment not yet sent to DVUH */
												WHERE buchungsnr = tbl_konto.buchungsnr
												AND betrag > 0
												LIMIT 1)
								  AND buchungstyp_kurzbz IN ?
								  ORDER BY buchungsdatum, buchungsnr",
			array(
				$person_id,
				$studiensemester_kurzbz,
				$all_buchungstypen
			)
		);

		// calculate values for ÖH-Beitrag, studiengebühr
		if (hasData($buchungenResult))
		{
			// check: are there still unpaid Buchungen for the semester? Payment should only be sent if everything is paid
			// to avoid part payments
			$unpaidBuchungen = $this->_ci->fhcmanagementlib->getUnpaidBuchungen($person_id, $studiensemester_kurzbz, $all_buchungstypen);

			if (hasData($unpaidBuchungen))
			{
				// return warning
				return $this->_getResponseArr(
					null,
					null,
					array(
						createError(
							"Es gibt noch offene Buchungen.",
							'offeneBuchungen',
							null,
							array('studiensemester_kurzbz' => $studiensemester_kurzbz)
						)
					)
				);
			}

			$buchungen = getData($buchungenResult);

			$paymentsToSend = array();
			foreach ($buchungen as $buchung)
			{
				$buchungsnr = $buchung->buchungsnr;

				// check: all Buchungen to be paid must have been sent to DVUH as Vorschreibung in Stammdatenmeldung
				$charge = $this->_ci->DVUHZahlungenModel->getLastCharge(
					$buchung->buchungsnr_verweis
				);

				if (hasData($charge))
				{
					if (abs(getData($charge)[0]->betrag) != $buchung->summe_buchungen)
					{
						return createError(
							"Buchung: $buchungsnr: Zahlungsbetrag abweichend von Vorschreibungsbetrag",
							'zlgUngleichVorschreibung',
							array($buchungsnr), // text params
							array('buchungsnr_verweis' => $buchung->buchungsnr_verweis) // resolution params
						);
					}
				}
				else
				{
					return $this->_getResponseArr(
						null,
						null,
						array(
							createError(
								"Buchung $buchungsnr: Zahlung nicht gesendet, vor der Zahlung wurde keine Vorschreibung an DVUH gesendet",
								'zlgKeineVorschreibungGesendet',
								array($buchungsnr),
								array('buchungsnr_verweis' => $buchung->buchungsnr_verweis)
							)
						)
					);
				}

				$payment = new stdClass();
				$payment->be = $this->_be;
				$payment->matrikelnummer = $buchung->matr_nr;
				$payment->semester = $this->_ci->dvuhsynclib->convertSemesterToDVUH($buchung->studiensemester_kurzbz);
				$payment->zahlungsart = '1';
				$payment->centbetrag = $buchung->betrag * 100;
				$payment->eurobetrag = number_format($buchung->betrag, 2, '.', '');
				$payment->buchungsdatum = $buchung->buchungsdatum;
				$payment->buchungstyp = $buchung->buchungstyp_kurzbz;
				$payment->referenznummer = $buchung->buchungsnr;

				$paymentsToSend[] = $payment;
			}

			// preview - only show date to be sent
			if ($preview)
			{
				$resultarr = array();
				foreach ($paymentsToSend as $payment)
				{
					$postData = $this->_ci->ZahlungModel->retrievePostData($this->_be, $payment->matrikelnummer, $payment->semester, $payment->zahlungsart,
						$payment->centbetrag, $payment->buchungsdatum, $payment->referenznummer);

					if (isError($postData))
						return $postData;

					$resultarr[] = $postData;
				}

				return $this->_getResponseArr($resultarr);
			}

			foreach ($paymentsToSend as $payment)
			{
				$zahlungResult = $this->_ci->ZahlungModel->post($this->_be, $payment->matrikelnummer, $payment->semester, $payment->zahlungsart,
					$payment->centbetrag, $payment->buchungsdatum, $payment->referenznummer);

				if (isError($zahlungResult))
					$zahlungenResArr[] = $zahlungResult;
				elseif (hasData($zahlungResult))
				{
					$xmlstr = getData($zahlungResult);

					$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

					if (isError($parsedObj))
						$zahlungenResArr[] = $parsedObj;
					else
					{
						$infos[] = "Zahlung des Studenten mit Person Id $person_id, Studiensemester $studiensemester_kurzbz, Buchungsnr "
							. $payment->referenznummer . " erfolgreich gesendet";

						// save date Buchungsnr and Betrag in sync table
						$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
							array(
								'buchungsdatum' => date('Y-m-d'),
								'buchungsnr' => $payment->referenznummer,
								'betrag' => $payment->eurobetrag
							)
						);

						if (isError($zahlungSaveResult))
							$zahlungenResArr[] = error("Zahlung erfolgreich, Fehler bei Speichern der Zahlung der Buchung " . $payment->buchungstyp . " in FHC");
						else
							$zahlungenResArr[] = success($xmlstr);
					}
				}
				else
					$zahlungenResArr[] = error("Fehler beim Senden der Zahlung");
			}
		}
		else
			return $this->_getResponseArr(null, array("Keine nicht gemeldeten Buchungen gefunden"));

		return $this->_getResponseArr($zahlungenResArr, $infos, null, true);
	}

	/**
	 * Sends study data for prestudents to DVUH, activates Matrikelnummer in FHC.
	 * @param string $studiensemester executed for a certain semester
	 * @param int $person_id
	 * @param int $prestudent_id optionally, send only data for one prestudent. person_id or prestudent_id must be given!
	 * @param false $preview if true, only data to post and infos are returned
	 * @return object error or success with infos
	 */
	public function sendStudyData($studiensemester, $person_id = null, $prestudent_id = null, $preview = false)
	{
		if (!isset($person_id))
		{
			if (!isset($prestudent_id))
				return error("Person Id oder Prestudent Id muss angegeben werden");

			$this->_ci->PrestudentModel->addSelect('person_id');
			$personIdRes = $this->_ci->PrestudentModel->load($prestudent_id);

			if (hasData($personIdRes))
			{
				$person_id = getData($personIdRes)[0]->person_id;
			}
			else
				return error('Keine Person für Prestudent gefunden');
		}

		$result = null;
		$fhc_studiensemester = $this->_ci->dvuhsynclib->convertSemesterToFHC($studiensemester);
		$dvuh_studiensemester = $this->_ci->dvuhsynclib->convertSemesterToDVUH($studiensemester);

		if ($preview)
		{
			$postData = $this->_ci->StudiumModel->retrievePostData($this->_be, $person_id, $dvuh_studiensemester, $prestudent_id);

			if (isError($postData))
				return $postData;

			return $this->_getResponseArr(getData($postData));
		}

		// put if only for one prestudent, with post all data would be updated.
		if (isset($prestudent_id))
			$studiumResult = $this->_ci->StudiumModel->put($this->_be, $person_id, $dvuh_studiensemester, $prestudent_id);
		else
			$studiumResult = $this->_ci->StudiumModel->post($this->_be, $person_id, $dvuh_studiensemester, $prestudent_id);

		// get and reset warnings produced by dvuhsynclib
		$warnings = $this->_ci->dvuhsynclib->readWarnings();

		if (isError($studiumResult))
			$result = $studiumResult;
		elseif (hasData($studiumResult))
		{
			$xmlstr = getData($studiumResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($parsedObj))
				$result = $parsedObj;
			else
			{
				$result = $this->_getResponseArr(
					$xmlstr,
					array('Studiumdaten erfolgreich in DVUH gespeichert'),
					$warnings,
					true
				);

				// activate Matrikelnr
				$matrNrActivationResult = $this->_ci->PersonModel->update(
					array(
						'person_id' => $person_id,
						'matr_aktiv' => false
					),
					array(
						'matr_aktiv' => true
					)
				);

				if (isError($matrNrActivationResult))
					$result = error("Studiumdaten erfolgreich gespeichert, Fehler beim Scharfschalten der Matrikelnummer in FHC");

				$syncedPrestudentIds = $this->_ci->StudiumModel->retrieveSyncedPrestudentIds();

				foreach ($syncedPrestudentIds as $syncedPrestudentId)
				{
					// save info about saved studiumdata in sync table
					$studiumSaveResult = $this->_ci->DVUHStudiumdatenModel->insert(
						array(
							'prestudent_id' => $syncedPrestudentId,
							'studiensemester_kurzbz' => $fhc_studiensemester,
							'meldedatum' => date('Y-m-d')
						)
					);

					if (isError($studiumSaveResult))
						$result = error("Studiumdaten erfolgreich gespeichert, Fehler beim Speichern in der Synctabelle in FHC");
				}
			}
		}
		else
			$result = error("Fehler beim Senden der Studiumdaten");

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
		$result = null;
		$bpk = null;
		$infos = array();
		$warnings = array();

		// request BPK only for persons with no BPK
		$personResult = $this->_dbModel->execReadOnlyQuery("
										SELECT
											DISTINCT person_id, vorname, nachname, geschlecht, gebdatum, bpk, strasse, plz	        
										FROM
											public.tbl_person
											LEFT JOIN (SELECT DISTINCT ON (person_id) strasse, plz, person_id
														FROM public.tbl_adresse
											    		WHERE heimatadresse = TRUE
											    		ORDER BY person_id, insertamum DESC NULLS LAST
											    		) addr USING(person_id)
										WHERE
											tbl_person.person_id = ?
											AND (tbl_person.bpk IS NULL OR tbl_person.bpk = '')",
			array(
				$person_id
			)
		);

		if (hasData($personResult))
		{
			$person = getData($personResult)[0];

			if (!isEmptyString($person->geschlecht))
				$geschlecht = $this->_ci->dvuhsynclib->convertGeschlechtToDVUH($person->geschlecht);

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

				return $this->_getResponseArr($bpk, $infos, $warnings);
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
	public function sendMatrikelErnpMeldung($person_id, $writeonerror, $ausgabedatum, $ausstellBehoerde,
										$ausstellland, $dokumentnr, $dokumenttyp, $preview = false)
	{
		$infos = array();

		$requiredFields = array('ausgabedatum', 'ausstellBehoerde', 'ausstellland', 'dokumentnr', 'dokumenttyp');

		foreach ($requiredFields as $requiredField)
		{
			if (!isset($$requiredField))
				return error("Daten fehlen: ".ucfirst($requiredField));
		}

		if ($preview)
		{
			$postData = $this->_ci->MatrikelmeldungModel->retrievePostData($this->_be, $person_id, $writeonerror, $ausgabedatum, $ausstellBehoerde,
				$ausstellland, $dokumentnr, $dokumenttyp);

			if (isError($postData))
				return $postData;

			return $this->_getResponseArr(getData($postData), $infos);
		}

		$matrikelmeldungResult = $this->_ci->MatrikelmeldungModel->post($this->_be, $person_id, $writeonerror, $ausgabedatum, $ausstellBehoerde,
			$ausstellland, $dokumentnr, $dokumenttyp);

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
				$result = $this->_getResponseArr(
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
	 * Sends Pruefungsaktivitaeten to DVUH.
	 * @param int $person_id
	 * @param string $studiensemester
	 * @param bool $preview
	 * @return object error or success
	 */
	public function sendPruefungsaktivitaeten($person_id, $studiensemester, $preview = false)
	{
		$infos = array();
		$warnings = array();

		$requiredFields = array('person_id', 'studiensemester');

		foreach ($requiredFields as $requiredField)
		{
			if (!isset($$requiredField))
				return error("Daten fehlen: ".ucfirst($requiredField));
		}

		// TODO phrases
		$no_pruefungen_info = 'Keine Pruefungsaktivitäten vorhanden, in DVUH gespeicherte Aktivitäten werden gelöscht, wenn vorhanden';

		$studiensemester_kurzbz = $this->_ci->dvuhsynclib->convertSemesterToFHC($studiensemester);

		if ($preview)
		{
			$postData = $this->_ci->PruefungsaktivitaetenModel->retrievePostData($this->_be, $person_id, $studiensemester_kurzbz);

			if (isError($postData))
				return $postData;

			if (hasData($postData))
				$postData = getData($postData);
			else
				$infos[] = $no_pruefungen_info;

			return $this->_getResponseArr($postData, $infos);
		}

		$prestudentsToPost = array();

		$pruefungsaktivitaetenResult = $this->_ci->PruefungsaktivitaetenModel->post($this->_be, $person_id, $studiensemester_kurzbz, $prestudentsToPost);

		if (isError($pruefungsaktivitaetenResult))
			return $pruefungsaktivitaetenResult;

		$pruefungsaktivitaetenResultData = null;

		if (hasData($pruefungsaktivitaetenResult))
		{
			$xmlstr = getData($pruefungsaktivitaetenResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($parsedObj))
				return $parsedObj;
		}
		else
			$infos[] = $no_pruefungen_info;

		// check for each prestudent to post if deletion is needed
		foreach ($prestudentsToPost as $prestudent_id => $ects)
		{
			// if no ects were sent
			if ($ects['ects_angerechnet'] == 0 && $ects['ects_erworben'] == 0)
			{
				// get last sent ects
				$checkPruefungsaktivitaetenRes = $this->_ci->DVUHPruefungsaktivitaetenModel->getLastSentPruefungsaktivitaet($prestudent_id, $studiensemester_kurzbz);

				if (hasData($checkPruefungsaktivitaetenRes))
				{
					$checkPruefungsaktivitaeten = getData($checkPruefungsaktivitaetenRes)[0];

					// if there were ects sent before, delete all Pruefungsaktivitaeten for the prestudent
					if (isset($checkPruefungsaktivitaeten->ects_angerechnet) || isset($checkPruefungsaktivitaeten->ects_erworben))
					{
						$deletePruefunsaktivitaetenRes = $this->deletePruefungsaktivitaeten($person_id, $studiensemester_kurzbz, $prestudent_id);

						if (isError($deletePruefunsaktivitaetenRes))
							return $deletePruefunsaktivitaetenRes;

						if (hasData($deletePruefunsaktivitaetenRes))
						{
							$deletePruefungsaktivitaeten = getData($deletePruefunsaktivitaetenRes);

							$infos = array_merge($infos, $deletePruefungsaktivitaeten['infos']);
							$warnings = array_merge($warnings, $deletePruefungsaktivitaeten['warnings']);
						}
					}
				}
			}
			else
			{
				// if at least some ects were sent, write to sync table
				$ects_angerechnet_posted = $ects['ects_angerechnet'] == 0
					? null
					: number_format($ects['ects_angerechnet'], 2, '.', '');

				$ects_erworben_posted = $ects['ects_erworben'] == 0
					? null
					: number_format($ects['ects_erworben'], 2, '.', '');

				$pruefungsaktivitaetenSaveResult = $this->_ci->DVUHPruefungsaktivitaetenModel->insert(
					array(
						'prestudent_id' => $prestudent_id,
						'studiensemester_kurzbz' => $studiensemester_kurzbz,
						'ects_angerechnet' => $ects_angerechnet_posted,
						'ects_erworben' => $ects_erworben_posted,
						'meldedatum' => date('Y-m-d')
					)
				);

				if (isError($pruefungsaktivitaetenSaveResult))
					$warnings[] = error('Pruefungsaktivitätenmeldung erfolgreich, Fehler beim Speichern in der Synctabelle in FHC');
			}
		}

		$infos[] = 'Pruefungsaktivitätenmeldung erfolgreich';

		$result = $this->_getResponseArr(
			$pruefungsaktivitaetenResultData,
			$infos,
			$warnings,
			true
		);

		return $result;
	}

	/**
	 * Deletes all Pruefungsaktivitäten of a person in DVUH.
	 * @param $person_id
	 * @param $studiensemester
	 * @param $prestudent_id
	 * @return object
	 */
	public function deletePruefungsaktivitaeten($person_id, $studiensemester, $prestudent_id = null)
	{
		$infos = array();
		$warnings = array();

		$requiredFields = array('person_id', 'studiensemester');

		foreach ($requiredFields as $requiredField)
		{
			if (!isset($$requiredField))
				return error("Daten fehlen: ".ucfirst($requiredField));
		}

		$studiensemester_kurzbz = $this->_ci->dvuhsynclib->convertSemesterToFHC($studiensemester);

		$deleteRes = $this->_ci->PruefungsaktivitaetenLoeschenModel->delete($this->_be, $person_id, $studiensemester, $prestudent_id);

		if (isError($deleteRes))
			return $deleteRes;

		if (hasData($deleteRes))
		{
			$prestudentIdsResult = getData($deleteRes);

			// delete returns array of deleted prestudent ids
			foreach ($prestudentIdsResult as $prestudent_id)
			{
				// add entry to sync table with NULL to identify when Prüfungsaktivitäten were deleted
				$pruefungsaktivitaetenSaveResult = $this->_ci->DVUHPruefungsaktivitaetenModel->insert(
					array(
						'prestudent_id' => $prestudent_id,
						'studiensemester_kurzbz' => $studiensemester_kurzbz,
						'ects_angerechnet' => null,
						'ects_erworben' => null,
						'meldedatum' => date('Y-m-d')
					)
				);

				if (isError($pruefungsaktivitaetenSaveResult))
					$warnings[] = error('Pruefungsaktivitätenmeldung erfolgreich, Fehler beim Speichern in der Synctabelle in FHC');// TODO phrases
			}

			$infos[] = "Prüfungsaktivitäten in Datenverbund gelöscht, prestudent Id(s): " . implode(', ', $prestudentIdsResult); // TODO phrases
		}
		else
			$infos[] = "No Prüfungsaktivitäten found for deletion"; // TODO phrases

		return $this->_getResponseArr(
			null,
			$infos,
			$warnings
		);
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

			return $this->_getResponseArr(getData($postData), $infos);
		}

		$ekzanfordernResult = $this->_ci->EkzanfordernModel->post($person_id, $forcierungskey);

		if (isError($ekzanfordernResult))
			$result = $ekzanfordernResult;
		elseif (hasData($ekzanfordernResult))
		{
			$xmlstr = getData($ekzanfordernResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid', 'responsecode', 'returncode', 'returntext', 'ekz', 'forcierungskey'));

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

				if (isset($parsedObj->returntext[0]))
					$infomsg .= ", " . $parsedObj->returntext[0];

				if (isset($parsedObj->ekz[0]))
					$infomsg .= ", EKZ: " . $parsedObj->ekz[0];

				if (isset($parsedObj->forcierungskey[0]))
					$infomsg .= ", Forcierungskey für Anfrage eines neuen EKZ: " . $parsedObj->forcierungskey[0];

				$infos[] = $infomsg;

				$result = $this->_getResponseArr(
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

	/**
	 * Cancels study data in DVUH.
	 * @param string $matrikelnummer
	 * @param string $semester
	 * @param int $studiengang_kz if passed, only study data for this studiengang is cancelled
	 * @param bool $preview
	 * @return object error or success
	 */
	public function cancelStudyData($matrikelnummer, $semester, $studiengang_kz = null, $preview = false)
	{
		$result = null;
		$infos = array();

		if (!isset($matrikelnummer))
		{
			return error("Matrikelnummer muss angegeben werden"); // TODO phrase
		}

		if (!isset($semester))
		{
			return error("Semester muss angegeben werden"); // TODO phrase
		}

		// get xml of study data in DVUH
		$studyData = $this->_ci->StudiumModel->get($this->_be, $matrikelnummer, $semester);

		if (hasData($studyData))
		{
			$xmlstr = getData($studyData);

			// parse the received data
			$studienRes = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('studiengang', 'lehrgang'));

			if (isError($studienRes))
				return $studienRes;

			if (hasData($studienRes))
			{
				$studiengaenge = array();
				$lehrgaenge = array();

				$studienData = getData($studienRes);

				$studien = array_merge($studienData->studiengang, $studienData->lehrgang);

				$params = array(
					"uuid" => getUUID(),
					"studierendenkey" => array(
						"matrikelnummer" => $matrikelnummer,
						"be" => $this->_be,
						"semester" => $semester
					)
				);

				// default send method post for all Studiengänge of person
				$sendMethodName = 'postManually';
				$studiengangIdName = 'stgkz';
				$lehrgangIdName = 'lehrgangsnr';

				foreach ($studien as $studium)
				{
					$studiumIdName = null;
					if (isset($studium->{$studiengangIdName}))
					{
						$studiumIdName = $studiengangIdName;
					}
					elseif (isset($studium->{$lehrgangIdName}))
					{
						$studiumIdName = $lehrgangIdName;
					}

					if (!isset($studiumIdName))
						return error("Studium Id fehlt"); //TODO phrases

					// if studiengang passed, use put method to cancel only one Studiengang
					if (isset($studiengang_kz))
					{
						// use put if update only one studium
						$sendMethodName = 'putManually';

						$dvuh_stgkz = $this->_ci->dvuhsynclib->convertStudiengangskennzahlToDVUH($studiengang_kz);

						// only send one studiengang if studiengang_kz passed
						if ($dvuh_stgkz !== substr($studium->{$studiumIdName}, -1 * strlen($dvuh_stgkz)))
							continue;
					}

					// add storno data
					$kodex_studstatuscode_array = $this->_ci->config->item('fhc_dvuh_sync_student_statuscode');
					$studium->studstatuscode = $studium->studstatuscode == $kodex_studstatuscode_array['Absolvent'] ? $kodex_studstatuscode_array['Absolvent'] : $kodex_studstatuscode_array['Abbrecher'];
					$studium->meldestatus = self::STORNO_MELDESTATUS;

					// convert object data to assoc array
					$stdArr = json_decode(json_encode($studium), true);

					if (isset($studium->{$studiengangIdName}))
					{
						$studiengaenge[] = $stdArr;
					}
					elseif (isset($studium->{$lehrgangIdName}))
					{
						$lehrgaenge[] = $stdArr;
					}
				}

				$params['studiengaenge'] = $studiengaenge;
				$params['lehrgaenge'] = $lehrgaenge;

				// abort if no studien found
				if (isEmptyArray($studiengaenge) && isEmptyArray($lehrgaenge))
				{
					$infos[] = 'keine Studien in DVUH gefunden';// TODO phrases
					return $this->_getResponseArr(null, $infos);
				}

				// show preview
				if ($preview)
				{
					$postData = $this->_ci->StudiumModel->retrievePostDataString($params);
					return $this->_getResponseArr($postData);
				}

				// send study data with modified storno data
				$studiumPostRes = $this->_ci->StudiumModel->{$sendMethodName}($params);

				if (isError($studiumPostRes))
					return $studiumPostRes;

				$infos[] = "Studiumdaten erfolgreich storniert"; // TODO phrases

				$result = getData($studiumPostRes);
			}
			else
			{
				$infos[] = "Keine Studiumdaten zum Stornieren gefunden"; // TODO phrase
			}
		}
		else
			$infos[] = "Keine Studiumdaten zum Stornieren gefunden"; // TODO phrase

		return $this->_getResponseArr(
			$result,
			$infos,
			null,
			true
		);
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Saves masterdata with Matrikelnr in DVUH, sets Matrikelnr in FHC.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz semester for which stammdaten are sent
	 * @param string $matrikelnummer
	 * @param bool $matr_aktiv wether Matrnr is already active (or not yet valid)
	 * @param array $infos for storing info messages
	 * @return object
	 */
	private function _sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $matrikelnummer, $matr_aktiv, &$infos)
	{
		$sendMasterDataResult = $this->sendMasterdata($person_id, $studiensemester_kurzbz, $matrikelnummer);

		if (isError($sendMasterDataResult))
			$result = $sendMasterDataResult;
		elseif (hasData($sendMasterDataResult))
		{
			$sendRes = getData($sendMasterDataResult);
			$updateMatrResult = $this->_ci->fhcmanagementlib->updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv);
			$infos[] = "Stammdaten mit Matrikelnr $matrikelnummer erfolgreich für Person Id $person_id gesendet";

			if (!hasData($updateMatrResult))
				$result = error("Fehler beim Updaten der Matrikelnummer");
			else
			{
				$matrnr = getData($updateMatrResult)['matr_nr'];
				$matrnr_aktiv = getData($updateMatrResult)['matr_aktiv'];

				if ($matrnr_aktiv == true)
				{
					$infos[] = "Bestehende Matrikelnr $matrnr der Person Id $person_id zugewiesen";
				}
				elseif ($matrnr_aktiv == false)
				{
					$infos[] = "Neue Matrikelnr $matrnr erfolgreich der Person Id $person_id vorläufig zugewiesen";
				}

				$result = success($sendRes);
			}
		}
		else
			$result = error("Fehler beim Senden der Stammdaten");

		return $result;
	}

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
								$warnings[] = createError(
									"Buchung $buchungsnr ist in SAP gespeichert, obwohl ÖH-Beitrag bereits an anderer Bildungseinrichtung bezahlt wurde",
									'andereBeBezahltSapGesendet',
									array($buchungsnr)
								);
							}
						}

						// check for nullify flag to see if no nullification is needed
						$nullifyFlag = $this->_ci->config->item('fhc_dvuh_sync_nullify_buchungen_paid_other_univ');

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
	 * Saves bpk in FHC db if DVUH returned bpk and there is no bpk in FHC.
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

	/**
	 * Constructs response array consisting of information and the result itself.
	 * Info is passed for logging/displaying.
	 * @param object $result main response data
	 * @param array $infos array with info strings
	 * @param array $warnings array with warning strings
	 * @param bool $getWarningsFromResult if true, parse the result for warnings and include them in response
	 * @param array $warningCodesToExcludeFromIssues
	 * @return object response object with result, infos and warnings
	 */
	private function _getResponseArr($result, $infos = null, $warnings = null, $getWarningsFromResult = false, $warningCodesToExcludeFromIssues = array())
	{
		$responseArr = array();
		$responseArr['infos'] = isset($infos) ? $infos : array();
		$responseArr['result'] = $result;
		$responseArr['warnings'] = isset($warnings) ? $warnings : array();

		if ($getWarningsFromResult === true && !isEmptyString($result))
		{
			if (!is_array($result))
				$result = array(success($result));

			foreach ($result as $xmlstr)
			{
				if (hasData($xmlstr))
				{
					$xmlstr = getData($xmlstr);
					$warningsRes = $this->_ci->xmlreaderlib->parseXmlDvuhWarnings($xmlstr);

					if (isError($warningsRes))
						return error('Fehler beim Auslesen der Warnungen');

					if (hasData($warningsRes))
					{
						$warningtext = '';
						$parsedWarnings = array();

						foreach (getData($warningsRes) as $warning)
						{
							if (!isEmptyString($warningtext))
								$warningtext .= ', ';
							$warningtext .= $warning->fehlertextKomplett;
							if (!isEmptyArray($warningCodesToExcludeFromIssues)
								&& in_array($warning->fehlernummer, $warningCodesToExcludeFromIssues))
							{
								unset($warning->fehlernummer); // unset fehlernummer if it doesn't need to be written as issue
							}

							$parsedWarnings[] = $warning;
						}
						$responseArr['warnings'][] = error($warningtext, $parsedWarnings);
					}
				}
			}
		}

		return success($responseArr);
	}
}
