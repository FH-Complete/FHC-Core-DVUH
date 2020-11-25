<?php


class DVUHManagementLib
{
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
		$this->_dbModel = new DB_Model(); // get db

		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');
		$this->_ci->load->model('accounting/Konto_model', 'KontoModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/DVUHZahlungen_model', 'DVUHZahlungenModel');

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$this->_be = $this->_ci->config->item('fhc_dvuh_be_code');
	}

	public function requestMatrikelnummer($person_id)
	{
		$result = null;

		$personResult = $this->_ci->PersonModel->load($person_id);// TODO only for persons with prestudent in aktuellem Semester!

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
						$studienjahrResult = $this->_ci->StudienjahrModel->getCurrStudienjahr();

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
									$result = $this->_updateMatrikelnummer($person->person_id, $reservedMatrnrStr, false);
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
							// TODO also save when already a matrnr savved?
							$result = $this->_updateMatrikelnummer($person->person_id, $matrikelnummer, true);
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
			$result = success("no person with id " . $person_id . " found");

		return $result;
	}

	public function sendStammdaten($person_id)
	{
		$result = null;

		$studiensemesterResult = $this->_ci->StudiensemesterModel->getAktOrNextSemester();

		if (hasData($studiensemesterResult))
		{
			$studiensemester = getData($studiensemesterResult)[0]->studiensemester_kurzbz;
			$studiensemester = $this->_convertSemesterToDVUH($studiensemester);

			$stammdatenResult = $this->_ci->StammdatenModel->post($this->_be, $person_id, $studiensemester);

			if (isError($stammdatenResult))
				$result = $stammdatenResult;
			elseif (hasData($stammdatenResult))
			{
				$xmlstr = getData($stammdatenResult);

				$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

				if (isError($parsedObj))
					$result = $parsedObj;
				else
					$result = success(array('person_id' => $person_id));
			}
			else
				$result = error('Error when sending Stammdaten');
		}
		else
			$result = error('No Studiensemester found');

		return $result;
	}

	public function sendCharge($person_id)
	{
		$result = null;
		$resultArr = array();

		$valutadatumnachfrist_days = $this->_ci->config->item('fhc_dvuh_sync_days_valutadatumnachfrist');
		$studiengebuehrnachfrist_euros = $this->_ci->config->item('fhc_dvuh_sync_euros_studiengebuehrnachfrist');

		// get offene Buchungen
		$buchungenResult = $this->_dbModel->execReadOnlyQuery("
								SELECT buchungsdatum, mahnspanne, betrag, buchungsnr, zahlungsreferenz, buchungstyp_kurzbz,
								       studiensemester_kurzbz, TO_CHAR(buchungsdatum + (mahnspanne::text || ' days')::INTERVAL, 'yyyy-mm-dd') as valutadatum
								FROM public.tbl_konto
								WHERE person_id = ?
								  AND buchungsnr_verweis IS NULL
								  AND betrag < 0
								  AND NOT EXISTS (SELECT 1 FROM public.tbl_konto kto 
								  					WHERE kto.person_id = tbl_konto.person_id
								      				AND kto.buchungsnr_verweis = tbl_konto.buchungsnr
								      				LIMIT 1)
								  AND EXISTS (SELECT 1 FROM public.tbl_prestudent
								      			JOIN public.tbl_prestudentstatus USING (prestudent_id)
								      			WHERE tbl_prestudent.person_id = tbl_konto.person_id
								      			AND tbl_prestudentstatus.studiensemester_kurzbz = tbl_konto.studiensemester_kurzbz
								  )
								  AND buchungstyp_kurzbz IN ('Studiengebuehr', 'OEH')
								  ORDER BY buchungsdatum, buchungsnr",
			array(
				$person_id
			)
		);

		// TODO get Kaution and add it to Studiengebuehr


		$vorschreibungen = array();
		// calculate values for ÖH-Beitrag, studiengebühr (inkl. Kaution)
		if (hasData($buchungenResult))
		{
			$buchungen = getData($buchungenResult);

			foreach ($buchungen as $buchung)
			{
				if (!isset($vorschreibungen[$buchung->studiensemester_kurzbz][$buchung->buchungstyp_kurzbz]))
					$vorschreibungen[$buchung->studiensemester_kurzbz][$buchung->buchungstyp_kurzbz] = 0;

				$vorschreibungen[$buchung->studiensemester_kurzbz][$buchung->buchungstyp_kurzbz] += $buchung->betrag * -100;

				if ($buchung->buchungstyp_kurzbz == 'OEH')
				{
					$vorschreibungen[$buchung->studiensemester_kurzbz]['valutadatum'] = $buchung->valutadatum;
					$vorschreibungen[$buchung->studiensemester_kurzbz]['valutadatumnachfrist'] =
						date('Y-m-d', strtotime($buchung->valutadatum . ' + ' . $valutadatumnachfrist_days . ' days'));
					$vorschreibungen[$buchung->studiensemester_kurzbz]['oehbuchungsnr'] = $buchung->buchungsnr;
				}
				elseif ($buchung->buchungstyp_kurzbz == 'Studiengebuehr')
				{
					$vorschreibungen[$buchung->studiensemester_kurzbz]['studiengebuehrnachfrist'] = ($buchung->betrag - $studiengebuehrnachfrist_euros) * -100;
					$vorschreibungen[$buchung->studiensemester_kurzbz]['studiengebuehrbuchungsnr'] = $buchung->buchungsnr;
				}
			}

			// send Stammdatenmeldung
			foreach ($vorschreibungen as $studiensemester => $vorschreibung)
			{
				if (isset($vorschreibung['OEH']) || isset($vorschreibung['Studiengebuehr']))
				{
					$dvuh_studiensemester = $this->_convertSemesterToDVUH($studiensemester);
					$oehbeitrag = isset($vorschreibung['OEH']) ? $vorschreibung['OEH'] : null;
					$studiengebuehr = isset($vorschreibung['Studiengebuehr']) ? $vorschreibung['Studiengebuehr'] : null;
					$valutdatum = isset($vorschreibung['valutadatum']) ? $vorschreibung['valutadatum'] : null;
					$valutdatumnachfrist = isset($vorschreibung['valutadatumnachfrist']) ? $vorschreibung['valutadatumnachfrist'] : null;
					$studiengebuehrnachfrist = isset($vorschreibung['studiengebuehrnachfrist']) ? $vorschreibung['studiengebuehrnachfrist'] : null;

					$stammdatenResult = $this->_ci->StammdatenModel->post($this->_be, $person_id, $dvuh_studiensemester, $oehbeitrag,
						$studiengebuehr, $valutdatum, $valutdatumnachfrist, $studiengebuehrnachfrist);

					if (isError($stammdatenResult))
						$resultArr[] = $stammdatenResult;
					elseif (hasData($stammdatenResult))
					{
						$xmlstr = getData($stammdatenResult);

						$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh($xmlstr, array('uuid'));

						if (isError($parsedObj))
							$resultArr[] = $parsedObj;
						else
						{
							$resultArr[] = success(array('person_id' => $person_id, 'studiensemester_kurzbz' => $studiensemester));

							if (isset($oehbeitrag) && isset($vorschreibungen[$studiensemester]['oehbuchungsnr']))
							{
								// save date Buchungsnr and Betrag in sync table
								$this->_ci->DVUHZahlungenModel->insert(
									array(
										'zahlung_datum' => date('Y-m-d'),
										'buchungsnr' => $vorschreibungen[$studiensemester]['oehbuchungsnr'],
										'betrag' => $oehbeitrag * (-1)
									)
								);
							}

							if (isset($studiengebuehr) && isset($vorschreibungen[$studiensemester]['studiengebuehrbuchungsnr']))
							{
								// save date Buchungsnr and Betrag in sync table
								$this->_ci->DVUHZahlungenModel->insert(
									array(
										'zahlung_datum' => date('Y-m-d'),
										'buchungsnr' => $vorschreibungen[$studiensemester]['studiengebuehrbuchungsnr'],
										'betrag' => $studiengebuehr * (-1)
									)
								);
							}
						}
					}
					else
						$resultArr[] = error('Error when sending Stammdaten');
				}
			}

			$result = success($resultArr);
		}
		else
			$result = success("no Buchungen found");

		return $result;
	}

	private function _updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv)
	{
		$updateResult = $this->_ci->PersonModel->update($person_id, array('matr_nr' => $matrikelnummer, 'matr_aktiv' => $matr_aktiv));

		if (hasData($updateResult))
		{
			$updateResObj = new StdClass();
			$updateResObj->matr_nr = $matrikelnummer;
			$updateResObj->matr_aktiv = $matr_aktiv;

			return success($updateResObj);
		}
		else
		{
			return error("Error while updating Matrikelnummer");
		}
	}

	private function _convertSemesterToDVUH($semester)
	{
		return mb_substr($semester, 2, strlen($semester) - 2).mb_substr($semester, 0,1);
	}
}
