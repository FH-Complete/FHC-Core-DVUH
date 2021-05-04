<?php


/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying data in DVUH, and updating data in FHC accordingly.
 */
class DVUHManagementLib
{
	const STATUS_PAID_OTHER_UNIV = '8';
	const ERRORCODE_BPK_MISSING = 'AD10065';

	private $_be;
	private $_matrnr_statuscodes = array(
		'assignNew' => array('1', '5'),
		'saveExisting' => array('3'),
		'error' => array('4', '6')
	);

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FeedReaderLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('crm/Konto_model', 'KontoModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Zahlung_model', 'ZahlungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Studium_model', 'StudiumModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelmeldung_model', 'MatrikelmeldungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefungsaktivitaeten_model', 'PruefungsaktivitaetenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefebpk_model', 'PruefebpkModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Ekzanfordern_model', 'EkzanfordernModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Kontostaende_model', 'KontostaendeModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/DVUHZahlungen_model', 'DVUHZahlungenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/DVUHStammdaten_model', 'DVUHStammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/DVUHStudiumdaten_model', 'DVUHStudiumdatenModel');

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
	 * Sends Stammdatenmeldung (see saveAndUpdateMatrikelnummer).
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz executed for a certain semester
	 * @return object error or success with infos
	 */
	public function requestMatrikelnummer($person_id, $studiensemester_kurzbz)
	{
		$result = null;

		// request Matrikelnr only for persons with prestudent in given Semester and no matrikelnr
		$personResult = $this->_dbModel->execReadOnlyQuery("
								SELECT tbl_person.*
								FROM public.tbl_person
								JOIN public.tbl_prestudent USING (person_id)
								JOIN public.tbl_prestudentstatus USING (prestudent_id)
								JOIN public.tbl_student USING (prestudent_id)
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
				$bpk = null,
				$ekz = $person->ersatzkennzeichen,
				$geburtsdatum = $person->gebdatum,
				$matrikelnummer = null,
				$nachname = $person->nachname,
				$svnr = !isEmptyString($person->svnr) ? $person->svnr : null,
				$vorname = $person->vorname
			);

			if (isError($matrPruefungResult))
			{
				$result = $matrPruefungResult;
			}
			else
			{
				$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh(getData($matrPruefungResult), array('statuscode', 'statusmeldung', 'matrikelnummer'));

				if (isError($parsedObj))
					$result = $parsedObj;
				elseif (hasData($parsedObj))
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

							// reserve new Matrikelnummer
							$anzahl = 1;

							$matrReservResult = $this->_ci->MatrikelreservierungModel->post($this->_be, $sj, $anzahl);

							if (hasData($matrReservResult))
							{
								$reservedMatrnr = $this->_ci->xmlreaderlib->parseXMLDvuh(getData($matrReservResult), array('matrikelnummer'));

								if (isError($reservedMatrnr))
									$result = $reservedMatrnr;
								elseif (hasData($reservedMatrnr))
								{
									$reservedMatrnr = getData($reservedMatrnr);
									$reservedMatrnrStr = $reservedMatrnr->matrikelnummer[0];
									$result = $this->_sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $reservedMatrnrStr, false);
								}
								else
									$result = error("No Matrikelnummer could be reserved");
							}
							else
								$result = error("Error when reserving Matrikelnummer");
						}
						else
						{
							$result = error("Studienjahr not found");
						}
					}
					elseif (in_array($statuscode, $this->_matrnr_statuscodes['saveExisting'])) // Matrikelnr already existing in DVUH -> save in FHC
					{
						if (is_numeric($matrikelnummer))
						{
							$result = $this->_sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $matrikelnummer, true);
						}
						else
							$result = error("invalid Matrikelnummer");
					}
					elseif (in_array($statuscode, $this->_matrnr_statuscodes['error']))
					{
						$result = error($statusmeldung);
					}
					else
					{
						if (!isEmptyString($statusmeldung))
							$result = $this->_getResponseArr(null, array($statusmeldung));
						else
							$result = error("unknown statuscode");
					}
				}
			}
		}
		else
			$result = $this->_getResponseArr(null, array("No valid person found for Matrikelnummer request"));

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

		$valutadatumnachfrist_days = $this->_ci->config->item('fhc_dvuh_sync_days_valutadatumnachfrist');
		$studiengebuehrnachfrist_euros = $this->_ci->config->item('fhc_dvuh_sync_euros_studiengebuehrnachfrist');
		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$all_buchungstypen = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);
		$studiensemester_kurzbz = $this->_ci->dvuhsynclib->convertSemesterToFHC($studiensemester);
		$dvuh_studiensemester = $this->_ci->dvuhsynclib->convertSemesterToDVUH($studiensemester);

		// get Buchungen
		$buchungenResult = $this->_dbModel->execReadOnlyQuery("
								SELECT person_id, studiengang_kz, buchungsdatum, mahnspanne, betrag, buchungsnr, zahlungsreferenz, buchungstyp_kurzbz,
								       studiensemester_kurzbz, buchungstext, TO_CHAR(buchungsdatum + (mahnspanne::text || ' days')::INTERVAL, 'yyyy-mm-dd') as valutadatum,
										(SELECT count(*) FROM public.tbl_konto kto /* no Gegenbuchung yet */
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr) AS bezahlt
								FROM public.tbl_konto
								WHERE person_id = ?
								  AND studiensemester_kurzbz = ?
								  AND buchungsnr_verweis IS NULL
								  AND betrag < 0
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

			// check if paid on another university - if yes, Vorschreibung might not be necessary
			$paidOtherUnivRes = $this->_checkIfPaidOtherUniv($person_id, $dvuh_studiensemester, $matrikelnummer);

			if (isError($paidOtherUnivRes))
				return $paidOtherUnivRes;

			$paidOtherUniv = false;
			if (hasData($paidOtherUnivRes))
			{
				$paidOtherUniv = getData($paidOtherUnivRes)[0];
			}

			if ($paidOtherUniv === true)
				$warnings[] = "Already paid in other university";
			foreach ($buchungen as $buchung)
			{
				// if already paid on other university but not at own university, not send Vorschreibung
				if ($paidOtherUniv === true && $buchung->bezahlt == '0')
					continue;

				// add Vorschreibung
				if (in_array($buchung->buchungstyp_kurzbz, $buchungstypen['oehbeitrag']))
					$dvuh_buchungstyp = 'oehbeitrag';
				elseif ((in_array($buchung->buchungstyp_kurzbz, $buchungstypen['studiengebuehr'])))
					$dvuh_buchungstyp = 'studiengebuehr';

				if (!isset($vorschreibung[$dvuh_buchungstyp]))
					$vorschreibung[$dvuh_buchungstyp] = 0;

				$vorschreibung[$dvuh_buchungstyp] += $buchung->betrag;

				if ($dvuh_buchungstyp == 'oehbeitrag')
				{
					$vorschreibung['valutadatum'] = $buchung->valutadatum;
					$vorschreibung['valutadatumnachfrist'] =
						date('Y-m-d', strtotime($buchung->valutadatum . ' + ' . $valutadatumnachfrist_days . ' days'));
					$vorschreibung['origoehbuchung'][] = $buchung;
				}
				elseif ($dvuh_buchungstyp == 'studiengebuehr')
				{
					$vorschreibung['studiengebuehrnachfrist'] = $vorschreibung[$dvuh_buchungstyp] - $studiengebuehrnachfrist_euros;
					$vorschreibung['origstudiengebuehrbuchung'][] = $buchung;
				}
			}
		}

		// convert Vorschreibungdata
		$oehbeitrag = isset($vorschreibung['oehbeitrag']) ? $vorschreibung['oehbeitrag'] * -100 : null;
		$studiengebuehr = isset($vorschreibung['studiengebuehr']) ? $vorschreibung['studiengebuehr'] * -100 : null;
		$valutadatum = isset($vorschreibung['valutadatum']) ? $vorschreibung['valutadatum'] : null;
		$valutadatumnachfrist = isset($vorschreibung['valutadatumnachfrist']) ? $vorschreibung['valutadatumnachfrist'] : null;
		$studiengebuehrnachfrist = isset($vorschreibung['studiengebuehrnachfrist']) ? $vorschreibung['studiengebuehrnachfrist']  * -100 : null;

		if ($preview)
		{
			$postData = $this->_ci->StammdatenModel->retrievePostData($this->_be, $person_id, $dvuh_studiensemester, $matrikelnummer, $oehbeitrag,
				$studiengebuehr, $valutadatum, $valutadatumnachfrist, $studiengebuehrnachfrist);

			if (isError($postData))
				return $postData;

			return $this->_getResponseArr(getData($postData), $infos, $warnings);
		}

		// send Stammdatenmeldung
		$stammdatenResult = $this->_ci->StammdatenModel->post($this->_be, $person_id, $dvuh_studiensemester, $matrikelnummer, $oehbeitrag,
			$studiengebuehr, $valutadatum, $valutadatumnachfrist, $studiengebuehrnachfrist);

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
				$infos[] = "Stammdaten for person_id $person_id successfully saved in DVUH";

				// write Stammdatenmeldung in FHC db
				$stammdatenSaveResult = $this->_ci->DVUHStammdatenModel->insert(
					array(
						'person_id' => $person_id,
						'studiensemester_kurzbz' => $studiensemester_kurzbz,
						'meldedatum' => date('Y-m-d')
					)
				);

				if (isError($stammdatenSaveResult))
					$result = error("Stammdaten save in DVUH successfull, error when saving Stammdaten in FHC");

				if (isset($vorschreibung['oehbeitrag']) && isset($vorschreibung['origoehbuchung'])
					&& $vorschreibung['oehbeitrag'] < 0)
				{
					foreach ($vorschreibung['origoehbuchung'] as $bchng)
					{
						// save date, Buchungsnr and Betrag in sync table
						$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
							array(
								'buchungsdatum' => date('Y-m-d'),
								'buchungsnr' => $bchng->buchungsnr,
								'betrag' => $bchng->betrag
							)
						);

						if (isError($zahlungSaveResult))
							$result = error("Stammdaten save in DVUH successfull, error when saving ÖH-Beitrag Zahlung in FHC");
					}
				}

				if (isset($vorschreibung['studiengebuehr']) && isset($vorschreibung['origstudiengebuehrbuchung'])
					&& $vorschreibung['studiengebuehr'] < 0)
				{
					foreach ($vorschreibung['origstudiengebuehrbuchung'] as $bchng)
					{
						// save date, Buchungsnr and Betrag in sync table
						$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
							array(
								'buchungsdatum' => date('Y-m-d'),
								'buchungsnr' => $bchng->buchungsnr,
								'betrag' => $bchng->betrag
							)
						);

						if (isError($zahlungSaveResult))
							$result = error("Stammdaten save in DVUH successfull, error when saving Studiengebuehr Zahlung in FHC");
					}
				}

				// check if already paid on another university and nullify open buchung
				if (isset($buchungen))
				{
					$paidOtherUnivRes = $this->_checkIfPaidOtherUnivAndNullify($person_id, $dvuh_studiensemester, $matrikelnummer, $buchungen, $infos);

					if (isError($paidOtherUnivRes))
						return $paidOtherUnivRes;
				}

				// get warnings from result
				$warningsRes = $this->_ci->xmlreaderlib->parseXmlDvuhWarnings($xmlstr);

				if (isError($warningsRes))
					return error('error when parsing warnings');

				if (hasData($warningsRes))
				{
					$parsedWarnings = getData($warningsRes);

					foreach ($parsedWarnings as $warning)
					{
						$warnings[] = $warning->full_error_text;
					}

					// if no bpk saved in FHC, but a BPK is returned by DVUH, save it in FHC
					$saveBpkRes = $this->_saveBpkInFhc($person_id, $parsedWarnings);

					if (isError($saveBpkRes))
						return error('error when saving bpk in FHC');

					if (hasData($saveBpkRes))
					{
						$bpkRes = getData($saveBpkRes);
						$infos[] = "New Bpk ($bpkRes) saved for person with id $person_id";
					}
				}

				if (!isset($result))
					$result = $this->_getResponseArr($xmlstr, $infos, $warnings);
			}
		}
		else
			$result = error('Error when sending Stammdaten');

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
								JOIN public.tbl_person using(person_id)
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
			$openPayments = $this->_dbModel->execReadOnlyQuery("
								SELECT buchungsnr
								FROM public.tbl_konto
								WHERE person_id = ?
								  AND studiensemester_kurzbz = ?
								  AND buchungsnr_verweis IS NULL
								  AND betrag < 0
								  AND NOT EXISTS (SELECT 1 FROM public.tbl_konto kto 
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr
								      				LIMIT 1)
								  AND buchungstyp_kurzbz IN ?
								  ORDER BY buchungsdatum, buchungsnr
								  LIMIT 1",
				array(
					$person_id, $studiensemester_kurzbz, $all_buchungstypen
				)
			);

			if (hasData($openPayments))
			{
				return error("There are still open Buchungen.");
			}

			$buchungen = getData($buchungenResult);

			$paymentsToSend = array();
			foreach ($buchungen as $buchung)
			{
				$buchungsnr = $buchung->buchungsnr;

				// check: all Buchungen to be paid must have been sent to DVUH as Vorschreibung in Stammdatenmeldung
				$charges = $this->_dbModel->execReadOnlyQuery("
								SELECT betrag
								FROM sync.tbl_dvuh_zahlungen
								WHERE buchungsnr = ?
								AND betrag < 0
								ORDER BY buchungsdatum DESC, insertamum DESC
								LIMIT 1",
					array(
						$buchung->buchungsnr_verweis
					)
				);

				if (hasData($charges))
				{
					if (abs(getData($charges)[0]->betrag) != $buchung->summe_buchungen)
						return error("Buchung: $buchungsnr: payment not equal to charge amount");
				}
				else
				{
					return $this->_getResponseArr(
						null,
						null,
						array("Buchung $buchungsnr: no charge sent to DVUH before the payment")
					);
				}

				// check if already paid on other university
/*				$paidOtherUniv = $this->_checkIfPaidOtherUniv($person_id, $studiensemester_kurzbz, $buchung->matr_nr);

				if (isError($paidOtherUniv))
					return $paidOtherUniv;

				if (hasData($paidOtherUniv))
				{
					$paidData = getData($paidOtherUniv)[0];
					if ($paidData == true)
					{
						/*foreach ($buchungen as $buchung)
						{
							if ($buchung->buchungstyp_kurzbz == 'OEH')
							{
								// set Buchungen to 0 since they don't need to be paid anymore
								$nullifyResult = $this->_nullifyBuchung($buchung);

								if (isError($nullifyResult))
									return $nullifyResult;
							}
						}*/

						/*return error("Buchung $buchungsnr: in FHC bereits bezahlt, in DVUH wurde aber von anderer BE bezahlt");
					}
				}*/

				$payment = new stdClass();
				$payment->be = $this->_be;
				$payment->matrikelnummer = $buchung->matr_nr;
				$payment->semester = $this->_ci->dvuhsynclib->convertSemesterToDVUH($buchung->studiensemester_kurzbz);
				$payment->zahlungsart = '1';
				$payment->centbetrag = $buchung->betrag * 100;
				$payment->eurobetrag = $buchung->betrag;
				$payment->buchungsdatum = $buchung->buchungsdatum;
				$payment->buchungstyp = $buchung->buchungstyp_kurzbz;
				$payment->referenznummer = $buchung->buchungsnr;

				$paymentsToSend[] = $payment;
			}

			//TODO check: Sum of Betrag to send must be equal to sum of Betrag of Stammdatenmeldungen

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
						$infos[] = "Payment of student with person Id $person_id, studiensemester $studiensemester_kurzbz, Buchungsnr "
							. $payment->referenznummer . " successfully sent";

						// save date Buchungsnr and Betrag in sync table
						$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
							array(
								'buchungsdatum' => date('Y-m-d'),
								'buchungsnr' => $payment->referenznummer,
								'betrag' => $payment->eurobetrag
							)
						);

						if (isError($zahlungSaveResult))
							$zahlungenResArr[] = error("Payment successfull, error when saving " . $payment->buchungstyp . " payment in FHC");
						else
							$zahlungenResArr[] = success($xmlstr);
					}
				}
				else
					$zahlungenResArr[] = error('Error when sending Zahlung');
			}
		}
		else
			return $this->_getResponseArr(null, array("No Buchungen found"));

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
				return error("Person Id or prestudent Id must be given");

			$this->_ci->PrestudentModel->addSelect('person_id');
			$personIdRes = $this->_ci->PrestudentModel->load($prestudent_id);

			if (hasData($personIdRes))
			{
				$person_id = getData($personIdRes)[0]->person_id;
			}
			else
				return error('No person found for prestudent');
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

		$studiumResult = $this->_ci->StudiumModel->post($this->_be, $person_id, $dvuh_studiensemester, $prestudent_id);

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
					array('Study data successfully saved in DVUH'),
					null,
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
					$result = error("Study data save successfull, error when activating Matrikelnummer");

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
						$result = error("Study data save successfull, error when saving info in FHC");
				}
			}
		}
		else
			$result = error('Error when sending study data');

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

		// request BPK only for persons with prestudent in given Semester and no BPK
		$personResult = $this->_dbModel->execReadOnlyQuery("
										SELECT
											DISTINCT person_id, vorname, nachname, geschlecht, gebdatum, bpk, tbl_benutzer.aktiv, strasse, plz	        
										FROM
											public.tbl_person
											JOIN public.tbl_benutzer USING(person_id)
											LEFT JOIN (SELECT DISTINCT ON (person_id) strasse, plz, person_id
														FROM public.tbl_adresse
											    		WHERE heimatadresse = TRUE
											    		ORDER BY person_id, insertamum DESC NULLS LAST
											    		) addr USING(person_id)
										WHERE
											public.tbl_person.person_id = ?
										  	AND tbl_benutzer.aktiv = TRUE
											AND tbl_person.bpk IS NULL",
			array(
				$person_id
			)
		);

		if (hasData($personResult))
		{
			$person = getData($personResult)[0];

			if (!isEmptyString($person->geschlecht))
				$geschlecht = $this->_ci->dvuhsynclib->convertGeschlechtToDVUH($person->geschlecht);

			$pruefeBpkResult = $this->_ci->PruefebpkModel->get(
				$person->vorname,
				$person->nachname,
				$person->gebdatum,
				$geschlecht
			);

			if (isError($pruefeBpkResult))
			{
				return $pruefeBpkResult;
			}

			if (hasData($pruefeBpkResult))
			{
				$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh(getData($pruefeBpkResult), array('bpk', 'person'));

				if (isError($parsedObj))
					return $parsedObj;

				if (hasData($parsedObj))
				{
					$parsedObj = getData($parsedObj);

					// no bpk found
					if (isEmptyArray($parsedObj->bpk))
					{
						// if multiple bpks, at least 2 person tags are present
						if (!isEmptyArray($parsedObj->person) && count($parsedObj->person) > 1)
						{
							$warnings[] = 'Multiple bpks found in DVUH. Retrying using address.';

							// remove any non-letter character (unicode for german)
							$strasse = preg_replace('/[\P{L}]/u', '', $person->strasse);

							// retry getting single bpk with adress
							$pruefeBpkWithAddrResult = $this->_ci->PruefebpkModel->get(
								$person->vorname,
								$person->nachname,
								$person->gebdatum,
								$geschlecht,
								$strasse,
								$person->plz
							);

							if (isError($pruefeBpkWithAddrResult))
							{
								return $pruefeBpkWithAddrResult;
							}

							if (hasData($pruefeBpkWithAddrResult))
							{
								$parsedObjAddr = $this->_ci->xmlreaderlib->parseXmlDvuh(getData($pruefeBpkWithAddrResult), array('bpk', 'person'));

								if (hasData($parsedObjAddr))
								{
									$parsedObjAddr = getData($parsedObjAddr);

									if (isEmptyArray($parsedObjAddr->bpk))
									{
										if (!isEmptyArray($parsedObjAddr->person) && count($parsedObjAddr->person) > 1)
											$warnings[] = 'Multiple bpks found in DVUH, even after retry using address.';
										else
											$warnings[] = 'No bpk found in DVUH after retry using address.';
									}
									else // single bpk found using adress
									{
										$infos[] = "Successfully retrieved Bpk after retry using address!";
										$bpk = $parsedObj->bpk[0];
									}
								}
								else
									return error("Error when parsing bpk response (request with address)");
							}
							else
								return error("Error when getting bpk by address");
						}
						else
							$warnings[] = "No bpk found in DVUH";
					}
					else // bpk found on first try
					{
						$infos[] = "Successfully retrieved Bpk!";
						$bpk = $parsedObj->bpk[0];
					}

					// if bpk found, save it in FHC db
					if (isset($bpk))
					{
						$bpkSaveResult = $this->_ci->PersonModel->update(
							array(
								'person_id' => $person_id,
								'bpk' => null
							),
							array(
								'bpk' => $bpk,
								'updateamum' => date('Y-m-d H:i:s'),
								'updatevon' => 'dvuhsync'
							)
						);

						if (!hasData($bpkSaveResult))
							return error("Error when saving bpk in FHC");

						$infos[] = "Successfully saved Bpk in FHC!";
					}

					return $this->_getResponseArr($bpk, $infos, $warnings);
				}
				else
					return error("Error when parsing bpk response");
			}
			else
				return error("Error when getting bpk");
		}
		else
			return error("No active person without bpk found for bpk request");

		return $result;
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
				return error("Data missing: ".ucfirst($requiredField));
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
				$infos[] = 'Personenmeldung successful';
				$result = $this->_getResponseArr(
					$xmlstr,
					$infos,
					null,
					true
				);
			}
		}
		else
			$result = error("Error when sending Matrikelmeldung");

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

		$requiredFields = array('person_id', 'studiensemester');

		foreach ($requiredFields as $requiredField)
		{
			if (!isset($$requiredField))
				return error("Data missing: ".ucfirst($requiredField));
		}

		$no_pruefungen_info = "Keine Pruefungsaktivitäten vorhanden";

		$studiensemester_kurzbz = $this->_ci->dvuhsynclib->convertSemesterToFHC($studiensemester);

		if ($preview)
		{
			$postData = $this->_ci->PruefungsaktivitaetenModel->retrievePostData($this->_be, $person_id, $studiensemester_kurzbz);

			if (isError($postData))
				return $postData;

			if (!hasData($postData))
				$infos[] = $no_pruefungen_info;

			$postData = getData($postData);
			return $this->_getResponseArr($postData, $infos);
		}

		$pruefungsaktivitaetenResult = $this->_ci->PruefungsaktivitaetenModel->post($this->_be, $person_id, $studiensemester_kurzbz);

		if (isError($pruefungsaktivitaetenResult))
			$result = $pruefungsaktivitaetenResult;
		elseif (hasData($pruefungsaktivitaetenResult))
		{
			$xmlstr = getData($pruefungsaktivitaetenResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

			if (isError($parsedObj))
				$result = $parsedObj;
			else
			{
				$infos[] = 'Pruefungsaktivitätenmeldung successful';
				$result = $this->_getResponseArr(
					$xmlstr,
					$infos,
					null,
					true
				);
			}
		}
		else
		{
			$infos[] = $no_pruefungen_info;
			$result = $this->_getResponseArr(null, $infos);
		}

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
					$errortext = 'Error response from EKZ request.';
					if (isset($parsedObj->responsetext[0]))
						$errortext .= ' ' . $parsedObj->responsetext[0];

					return error($errortext);
				}

				$infomsg = "EKZ request executed";

				if (isset($parsedObj->returntext[0]))
					$infomsg .= ", " . $parsedObj->returntext[0];

				if (isset($parsedObj->ekz[0]))
					$infomsg .= ", EKZ: " . $parsedObj->ekz[0];

				if (isset($parsedObj->forcierungskey[0]))
					$infomsg .= ", forcierungskey for requesting new EKZ: " . $parsedObj->forcierungskey[0];

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
			$result = error("Error when requesting Ekz");

		return $result;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Saves masterdata with Matrikelnr in DVUH, sets Matrikelnr in DVUH.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz semester for which stammdaten are sent
	 * @param string $matrikelnummer
	 * @param bool $matr_aktiv wether Matrnr is already active (or not yet valid)
	 * @return object
	 */
	private function _sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $matrikelnummer, $matr_aktiv)
	{
		$sendMasterDataResult = $this->sendMasterdata($person_id, $studiensemester_kurzbz, $matrikelnummer);

		if (isError($sendMasterDataResult))
			$result = $sendMasterDataResult;
		elseif (hasData($sendMasterDataResult))
		{
			$resArr = getData($sendMasterDataResult);
			$updateMatrResult = $this->_updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv);
			$resArr['infos'][] = 'Stammdaten with Matrikelnr ' . $matrikelnummer . ' successfully sent for person Id ' . $person_id;

			if (!hasData($updateMatrResult))
				$result = error("An error occured while updating Matrikelnummer");
			else
			{
				$matrnr = getData($updateMatrResult)['matr_nr'];
				$matrnr_aktiv = getData($updateMatrResult)['matr_aktiv'];

				if ($matrnr_aktiv == true)
				{
					$resArr['infos'][] = 'Existing Matrikelnr ' . $matrnr . ' assigned to person Id ' . $person_id;
				}
				elseif ($matrnr_aktiv == false)
				{
					$resArr['infos'][] = 'New Matrikelnr ' . $matrnr . ' preliminary assigned to person Id ' . $person_id;
				}

				$result = success($resArr);
			}

		}
		else
			$result = error("An error occurred while sending Stammdaten");

		return $result;
	}

	/**
	 * Updates a Matrikelnummer in FHC database
	 * @param int $person_id
	 * @param string $matrikelnummer
	 * @param bool $matr_aktiv
	 * @return object success or error
	 */
	private function _updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv)
	{
		$updateResult = $this->_ci->PersonModel->update(
			$person_id,
			array(
				'matr_nr' => $matrikelnummer,
				'matr_aktiv' => $matr_aktiv,
				'updateamum' => date('Y-m-d H:i:s'),
				'updatevon' => 'dvuhsync'
			)
		);

		if (hasData($updateResult))
		{
			$updateResArr = array('matr_nr' => $matrikelnummer, 'matr_aktiv' => $matr_aktiv);

			return success($updateResArr);
		}
		else
		{
			return error("Error while updating Matrikelnummer");
		}
	}

	/**
	 * Checks if paid on another university, and nullifys Buchung if yes.
	 * @param int $person_id
	 * @param string $dvuh_studiensemester
	 * @param array $buchungen contains Buchung to nullify
	 * @param array $infos for adding log infos
	 * @return object success or error with boolean (paid or not)
	 */
	private function _checkIfPaidOtherUnivAndNullify($person_id, $dvuh_studiensemester, $matrikelnummer, $buchungen, &$infos)
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
				$alreadyPaidStr = 'Already paid at other university.';
				foreach ($buchungen as $buchung)
				{
					if ($buchung->buchungstyp_kurzbz == 'OEH' && $buchung->bezahlt == '0')
					{
						// set Buchungen to 0 since they don't need to be paid anymore
						$nullifyResult = $this->_nullifyBuchung($buchung);

						if (isError($nullifyResult))
							return $nullifyResult;

						$alreadyPaidStr .= ' Buchung nullified.';
					}
				}

				if (!in_array($alreadyPaidStr, $infos))
					$infos[] = $alreadyPaidStr;
			}
		}

		return success(array($isPaid));
	}

	/**
	 * Checks if student already paid charges on another university for the semester by calling feed.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz
	 * @param string $matrikelnummer filter by matrikelnummmer
	 * @return object success with true/false or error
	 */
/*	private function _checkIfPaidOtherUniv($person_id, $studiensemester_kurzbz, $matrikelnummer = null)
	{
		$erstelltSeitResult = $this->_ci->StudiensemesterModel->load($studiensemester_kurzbz);

		if (hasData($erstelltSeitResult))
		{
			$erstelltSeit = getData($erstelltSeitResult)[0]->start;
		}
		else
			return error("No Studiensemester found when checking for payment");

		$this->_ci->PersonModel->addSelect('matr_nr');
		$matrnrResult = $this->_ci->PersonModel->load($person_id);

		if (!isset($matrikelnummer))
		{
			if (hasData($matrnrResult))
			{
				$matrikelnummer = getData($matrnrResult)[0]->matr_nr;

				if (!isset($matrikelnummer))
					return error('no Matrikelnummer for checking for payment');
			}
			else
				return error("No Person found when checking for payment");
		}

		$feeds = $this->_ci->feedreaderlib->getFeedsByType(
			$this->_be,
			date("Y-m-d", strtotime("-7 days")),
			self::KONTOSTAND_FEED_TYPE,
			array('matrikelnummer' => $matrikelnummer)
		);

		if (isError($feeds))
			return $feeds;

		if (hasData($feeds))
		{
			$result = null;

			$feedData = getData($feeds);

			$lastFeedDate = '';
			$lastStatus = '';

			foreach ($feedData as $feed)
			{
				$status = $this->_ci->xmlreaderlib->parseXml($feed->contentXml, array('bezahlstatus', 'semester'));

				if (hasData($status))
				{
					$statusdata = getData($status);

					if ($statusdata->semester[0] == $this->_ci->dvuhsynclib->convertSemesterToDVUH($studiensemester_kurzbz)
						&& ($lastFeedDate == '' || $feed->published > $lastFeedDate))
						{
							$lastFeedDate = $feed->published;
							$lastStatus = $statusdata->bezahlstatus[0];
						}
				}
			}

			if ($lastStatus == self::STATUS_PAID_OTHER_UNIV)
				$result = success(array(true));
			else
				$result = success(array(false));
		}
		else
			$result = success(array(false));

		return $result;
	}*/

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
				$result = error('Error when parsing Kontostand');
		}
		else
			$result = success(array(false));


		return $result;
	}

	/**
	 * Sets a Buchung in FHC to 0 and creates a Gegenbuchung with 0.
	 * @param object $buchung contains buchungsdata
	 * @return object success or error
	 */
	private function _nullifyBuchung($buchung)
	{
		$andereBeBezahltTxt = 'An anderer Bildungseinrichtung bezahlt';
		$buchungNullify = $this->_ci->KontoModel->update(
			array('buchungsnr' => $buchung->buchungsnr),
			array(
				'betrag' => 0,
				'anmerkung' => $andereBeBezahltTxt,
				'updateamum' => date('Y-m-d H:i:s'),
				'updatevon' => 'dvuhsync'
			)
		);

		if (hasData($buchungNullify))
		{
			$gegenbuchungNullify = $this->_ci->KontoModel->insert(array(
					"person_id" => $buchung->person_id,
					"studiengang_kz" => $buchung->studiengang_kz,
					"studiensemester_kurzbz" => $buchung->studiensemester_kurzbz,
					"betrag" => 0,
					"buchungsdatum" => date('Y-m-d'),
					"buchungstext" => $buchung->buchungstext,
					"buchungstyp_kurzbz" => $buchung->buchungstyp_kurzbz,
					"buchungsnr_verweis" => $buchung->buchungsnr,
					"insertvon" => 'dvuhJob',
					"insertamum" => date('Y-m-d H:i:s'),
					'anmerkung' => $andereBeBezahltTxt
				)
			);

			if (isError($gegenbuchungNullify))
				return $gegenbuchungNullify;

			return success("Successfully nullified");
		}
		else
			return error("Error when nullify Buchung");
	}

	/**
	 * Saves bpk in FHC db if not present.
	 * @param int $person_id
	 * @param array $parsedWarnings contains warning objects with error info and bpk, as returned from DVUH
	 * @return object success with bpk if saved, or error
	 */
	private function _saveBpkInFhc($person_id, $parsedWarnings)
	{
		$result = success(array());
		$this->_ci->PersonModel->addSelect('bpk');
		$bpkRes = $this->_ci->PersonModel->load($person_id);

		if (isError($bpkRes))
			return $bpkRes;

		if (hasData($bpkRes) && getData($bpkRes)[0]->bpk == null)
		{
			foreach ($parsedWarnings as $warning)
			{
				if ($warning->fehlernummer == self::ERRORCODE_BPK_MISSING && isset($warning->feldinhalt))
				{
					$bpkUpdateRes = $this->_ci->PersonModel->update(
						array(
							'person_id' => $person_id,
							'bpk' => null
						),
						array(
							'bpk' => $warning->feldinhalt,
							'updateamum' => date('Y-m-d H:i:s'),
							'updatevon' => 'dvuhsync'
						)
					);

					if (hasData($bpkUpdateRes))
						$result = success($warning->feldinhalt);
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
	 * @return object response object with result, infos and warnings
	 */
	private function _getResponseArr($result, $infos = null, $warnings = null, $getWarningsFromResult = false)
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
						return error('error when parsing warnings');

					if (hasData($warningsRes))
					{
						$warningtext = '';

						foreach (getData($warningsRes) as $warning)
						{
							if (!isEmptyString($warningtext))
								$warningtext .= ', ';
							$warningtext .= $warning->full_error_text;
						}
						$responseArr['warnings'][] = $warningtext;
					}
				}
			}
		}

		return success($responseArr);
	}
}
