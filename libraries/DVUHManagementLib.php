<?php


class DVUHManagementLib
{
	const STATUS_PAID_OTHER_UNIV = '8';

	private $_be;
	private $_matrnr_statuscodes = array(
		'assignNew' => array('1', '5'),
		'saveExisting' => array('3'),
		'error' => array('6')
	);

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FeedReaderLib');

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
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/DVUHZahlungen_model', 'DVUHZahlungenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/DVUHStudiumdaten_model', 'DVUHStudiumdatenModel');

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$this->_be = $this->_ci->config->item('fhc_dvuh_be_code');

		$this->_dbModel = new DB_Model(); // get db
	}

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
							$sj = substr($sj->studienjahr_kurzbz, 0, 4)/* . 'a'*/;

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
					elseif (in_array($statuscode, $this->_matrnr_statuscodes['saveExisting'])) // Matrikelnr already existing -> save in FHC
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
							$result = success($statusmeldung);
						else
							$result = error("unknown statuscode");
					}
				}
			}
		}
		else
			$result = $this->_getInfoObj("No valid person found for request Matrikelnummer");

		return $result;
	}

	public function sendMasterdata($person_id, $studiensemester_kurzbz, $matrikelnummer = null)
	{
		$result = null;

		/*$studiensemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();*/

		/*if (hasData($studiensemesterResult))
		{
			$studiensemester = getData($studiensemesterResult)[0]->studiensemester_kurzbz;*/
			$dvuh_studiensemester = $this->_convertSemesterToDVUH($studiensemester_kurzbz);

			$stammdatenResult = $this->_ci->StammdatenModel->post($this->_be, $person_id, $dvuh_studiensemester, $matrikelnummer);

			if (isError($stammdatenResult))
				$result = $stammdatenResult;
			elseif (hasData($stammdatenResult))
			{
				$xmlstr = getData($stammdatenResult);

				$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

				if (isError($parsedObj))
					$result = $parsedObj;
				else
					$result = success($xmlstr);
			}
			else
				$result = error('Error when sending Stammdaten');

		return $result;
	}

	public function sendCharge($person_id, $studiensemester_kurzbz, $preview = false)
	{
		$result = null;

		$valutadatumnachfrist_days = $this->_ci->config->item('fhc_dvuh_sync_days_valutadatumnachfrist');
		$studiengebuehrnachfrist_euros = $this->_ci->config->item('fhc_dvuh_sync_euros_studiengebuehrnachfrist');

		// get offene Buchungen
		$buchungenResult = $this->_dbModel->execReadOnlyQuery("
								SELECT person_id, studiengang_kz, buchungsdatum, mahnspanne, betrag, buchungsnr, zahlungsreferenz, buchungstyp_kurzbz,
								       studiensemester_kurzbz, buchungstext, buchungsnr_verweis, TO_CHAR(buchungsdatum + (mahnspanne::text || ' days')::INTERVAL, 'yyyy-mm-dd') as valutadatum
								FROM public.tbl_konto
								WHERE person_id = ?
								  AND studiensemester_kurzbz = ?
								  AND buchungsnr_verweis IS NULL
								  AND betrag < 0
								  AND NOT EXISTS (SELECT 1 FROM public.tbl_konto kto /* no Gegenbuchung yet */
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr
								      				LIMIT 1)
								  AND EXISTS (SELECT 1 FROM public.tbl_prestudent
								      			JOIN public.tbl_prestudentstatus USING (prestudent_id)
								      			WHERE tbl_prestudent.person_id = tbl_konto.person_id
								      			AND tbl_prestudentstatus.studiensemester_kurzbz = tbl_konto.studiensemester_kurzbz)
								  AND buchungstyp_kurzbz IN ('Studiengebuehr', 'OEH')
								  ORDER BY buchungsdatum, buchungsnr",
			array(
				$person_id,
				$studiensemester_kurzbz
			)
		);

		// TODO get Kaution and add it to Studiengebuehr


		$vorschreibung = array();
		// calculate values for ÖH-Beitrag, studiengebühr (inkl Kaution)
		if (hasData($buchungenResult))
		{
			$buchungen = getData($buchungenResult);

			$paidOtherUniv = $this->_checkIfPaidOtherUniv($person_id, $studiensemester_kurzbz);

			if (isError($paidOtherUniv))
				return $paidOtherUniv;

			if (hasData($paidOtherUniv))
			{
				$paidData = getData($paidOtherUniv)[0];
				if ($paidData == true)
				{
					foreach ($buchungen as $buchung)
					{
						$nullifyResult = $this->_nullifyBuchung($buchung); // set Buchungen to 0 since they don't need to be paid anymore

						if (isError($nullifyResult))
							return $nullifyResult;
					}

					return $this->_getInfoObj("Already paid in other university");
				}
			}

			foreach ($buchungen as $buchung)
			{
				if (!isset($vorschreibung[$buchung->buchungstyp_kurzbz]))
					$vorschreibung[$buchung->buchungstyp_kurzbz] = 0;

				$vorschreibung[$buchung->buchungstyp_kurzbz] += $buchung->betrag;

				if ($buchung->buchungstyp_kurzbz == 'OEH')
				{
					$vorschreibung['valutadatum'] = $buchung->valutadatum;
					$vorschreibung['valutadatumnachfrist'] =
						date('Y-m-d', strtotime($buchung->valutadatum . ' + ' . $valutadatumnachfrist_days . ' days'));
					$vorschreibung['oehbuchungsnr'] = $buchung->buchungsnr;
				}
				elseif ($buchung->buchungstyp_kurzbz == 'Studiengebuehr')
				{
					$vorschreibung['studiengebuehrnachfrist'] = ($buchung->betrag - $studiengebuehrnachfrist_euros);
					$vorschreibung['studiengebuehrbuchungsnr'] = $buchung->buchungsnr;
				}
			}

			// send Stammdatenmeldung
			if (isset($vorschreibung['OEH']) || isset($vorschreibung['Studiengebuehr']))
			{
				$dvuh_studiensemester = $this->_convertSemesterToDVUH($studiensemester_kurzbz);
				$oehbeitrag = isset($vorschreibung['OEH']) ? $vorschreibung['OEH'] * -100 : null;
				$studiengebuehr = isset($vorschreibung['Studiengebuehr']) ? $vorschreibung['Studiengebuehr'] * -100 : null;
				$valutdatum = isset($vorschreibung['valutadatum']) ? $vorschreibung['valutadatum'] : null;
				$valutdatumnachfrist = isset($vorschreibung['valutadatumnachfrist']) ? $vorschreibung['valutadatumnachfrist'] : null;
				$studiengebuehrnachfrist = isset($vorschreibung['studiengebuehrnachfrist']) ? $vorschreibung['studiengebuehrnachfrist']  * -100 : null;

				if ($preview)
				{
					return $this->_ci->StammdatenModel->retrievePostData($this->_be, $person_id, $dvuh_studiensemester, null, $oehbeitrag,
						$studiengebuehr, $valutdatum, $valutdatumnachfrist, $studiengebuehrnachfrist);
				}

				$stammdatenResult = $this->_ci->StammdatenModel->post($this->_be, $person_id, $dvuh_studiensemester, null, $oehbeitrag,
					$studiengebuehr, $valutdatum, $valutdatumnachfrist, $studiengebuehrnachfrist);

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
						//$result = success(array('person_id' => $person_id, 'studiensemester_kurzbz' => $studiensemester_kurzbz));
						$result = success($xmlstr);

						if (isset($vorschreibung['OEH']) && isset($vorschreibung['oehbuchungsnr']))
						{
							// save date Buchungsnr and Betrag in sync table
							$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
								array(
									'buchungsdatum' => date('Y-m-d'),
									'buchungsnr' => $vorschreibung['oehbuchungsnr'],
									'betrag' => $vorschreibung['OEH']
								)
							);

							if (isError($zahlungSaveResult))
								$result = error("Stammdaten save successfull, error when saving OEH-Beitrag Zahlung in FHC");
						}

						if (isset($vorschreibung['Studiengebuehr']) && isset($vorschreibung['studiengebuehrbuchungsnr']))
						{
							// save date Buchungsnr and Betrag in sync table
							$zahlungSaveResult = $this->_ci->DVUHZahlungenModel->insert(
								array(
									'buchungsdatum' => date('Y-m-d'),
									'buchungsnr' => $vorschreibung['studiengebuehrbuchungsnr'],
									'betrag' => $vorschreibung['Studiengebuehr']
								)
							);

							if (isError($zahlungSaveResult))
								$result = error("Stammdaten save successfull, error when saving Studiengebuehr Zahlung in FHC");
						}
					}
				}
				else
					$result = error('Error when sending Stammdaten');
			}
		}
		else
			$result = $this->_getInfoObj("No Buchungen found");

		return $result;
	}

	public function sendPayment($person_id, $studiensemester_kurzbz, $preview = false)
	{
		$result = null;
		$zahlungenResArr = array();

		// get paid Buchungen
		$buchungenResult = $this->_dbModel->execReadOnlyQuery("
								SELECT matr_nr, buchungsnr, buchungsdatum, betrag, buchungsnr_verweis, zahlungsreferenz, buchungstyp_kurzbz, studiensemester_kurzbz
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
								  AND buchungstyp_kurzbz IN ('Studiengebuehr', 'OEH')
								  ORDER BY buchungsdatum, buchungsnr",
			array(
				$person_id,
				$studiensemester_kurzbz
			)
		);

		// TODO get Kaution and add it to Studiengebuehr
		// TODO check if paid on other university

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
								  AND buchungstyp_kurzbz IN ('Studiengebuehr', 'OEH')
								  ORDER BY buchungsdatum, buchungsnr
								  LIMIT 1",
				array(
					$person_id, $studiensemester_kurzbz
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
					if (abs(getData($charges)[0]->betrag) != $buchung->betrag)
						return error("Buchung: $buchungsnr: payment not equal to charge amount");
				}
				else
					return error("Buchung $buchungsnr: no charge sent to DVUH before the payment");

				$payment = new stdClass();
				$payment->be = $this->_be;
				$payment->matrikelnummer = $buchung->matr_nr;
				$payment->semester = $this->_convertSemesterToDVUH($buchung->studiensemester_kurzbz);
				$payment->zahlungsart = '1';
				$payment->centbetrag = $buchung->betrag * 100;
				$payment->eurobetrag = $buchung->betrag;
				$payment->buchungsdatum = $buchung->buchungsdatum;
				$payment->buchungstyp = $buchung->buchungstyp_kurzbz;
				$payment->referenznummer = $buchung->buchungsnr;

				$paymentsToSend[] = $payment;
			}

			//TODO check: Sum of Betrag to send must be equal to sum of Betrag of Stammdatenmeldungen

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

				return success($resultarr);
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
					$zahlungenResArr[] = error('Error when sending Stammdaten');
			}
		}
		else
			return $this->_getInfoObj("No Buchungen found");

		return success($zahlungenResArr);
	}

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

		$dvuh_studiensemester = $this->_convertSemesterToDVUH($studiensemester);

		if ($preview)
		{
			return $this->_ci->StudiumModel->retrievePostData($this->_be, $person_id, $dvuh_studiensemester, $prestudent_id);
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
				$result = success($xmlstr);

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
							'studiensemester_kurzbz' => $studiensemester,
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

	private function _sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $matrikelnummer, $matr_aktiv)
	{
		$sendMasterDataResult = $this->sendMasterdata($person_id, $studiensemester_kurzbz, $matrikelnummer);

		if (isError($sendMasterDataResult))
			$result = $sendMasterDataResult;
		elseif (hasData($sendMasterDataResult))
		{
			$result = $this->_updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv);
		}
		else
			$result = error("An error occurred while sending Stammdaten");

		return $result;
	}

	private function _updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv)
	{
		$updateResult = $this->_ci->PersonModel->update($person_id, array('matr_nr' => $matrikelnummer, 'matr_aktiv' => $matr_aktiv));

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

	private function _checkIfPaidOtherUniv($person_id, $studiensemester_kurzbz)
	{
		$erstelltSeitResult = $this->_ci->StudiensemesterModel->load($studiensemester_kurzbz);

		if (hasData($erstelltSeitResult))
		{
			$erstelltSeit = getData($erstelltSeitResult)[0]->start;
		}
		else
			return error("No Studiensemester found when checking for payment");

		$matrnrResult = $this->_ci->PersonModel->load($person_id);

		if (hasData($matrnrResult))
		{
			$matrikelnummer = getData($matrnrResult)[0]->matr_nr;
		}
		else
			return error("No Matrikelnr found when checking for payment");

		$content = 'true';
		$markread = 'false';

		$queryResult = $this->_ci->FeedModel->get($this->_be, $content, $erstelltSeit, $markread);

		if (isError($queryResult))
			return $queryResult;

		if (hasData($queryResult))
		{
			$result = null;

			$feeds = $this->_ci->feedreaderlib->parseFeeds(getData($queryResult), $matrikelnummer);

			if (isError($feeds))
				$result = $feeds;
			elseif (hasData($feeds))
			{
				$feedData = getData($feeds);

				$lastFeedDate = '';
				$lastStatus = '';

				foreach ($feedData as $feed)
				{
					$status = $this->_ci->xmlreaderlib->parseXml($feed->contentXml, array('bezahlstatus', 'semester'));

					if (hasData($status))
					{
						$statusdata = getData($status);

						/*var_dump($feed->published);
						var_dump($lastFeedDate);*/

						if ($statusdata->semester[0] == $this->_convertSemesterToDVUH($studiensemester_kurzbz)
							&& ($lastFeedDate == '' || $feed->published > $lastFeedDate))
							{
								$lastFeedDate = $feed->published;
								$lastStatus = $statusdata->bezahlstatus[0];
							}
					}
				}

				if ($lastStatus ==  self::STATUS_PAID_OTHER_UNIV)
					$result = success(array(true));
				else
					$result = success(array(false));
			}
			else
				$result = success(array(false));
		}

		return $result;
	}

	private function _nullifyBuchung($buchung)
	{
		$buchungNullify = $this->_ci->KontoModel->update(array('buchungsnr' => $buchung->buchungsnr), array('betrag' => 0));

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
					"insertamum" => date('Y-m-d H:i:s')
				)
			);

			if (isError($gegenbuchungNullify))
				return $gegenbuchungNullify;

			return success("Successfully nullified");
		}
		else
			return error("Error when nullify Buchung");
	}

	private function _convertSemesterToDVUH($semester)
	{
		if (!preg_match("/^(S|W)S\d{4}$/", $semester))
			return $semester;

		return mb_substr($semester, 2, strlen($semester) - 2).mb_substr($semester, 0,1);
	}

	private function _getInfoObj($str)
	{
		return success(array('info' => $str));
	}
}
