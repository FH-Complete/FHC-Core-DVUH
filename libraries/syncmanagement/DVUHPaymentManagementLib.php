<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncmanagement/DVUHManagementLib.php';

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying data in DVUH, and updating data in FHC accordingly.
 */
class DVUHPaymentManagementLib extends DVUHManagementLib
{
	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');

		// load models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Zahlung_model', 'ZahlungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHZahlungen_model', 'DVUHZahlungenModel');

		$this->_dbModel = new DB_Model(); // get db
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Sends payment to DVUH, one request for each payment type. Performs checks before payment.
	 * @param int $person_id
	 * @param string $studiensemester executed for a certain semester
	 * @param bool $preview if true, only data to post and infos are returned
	 * @return object error or success with infos
	 */
	public function sendPayment($person_id, $studiensemester, $preview = false)
	{
		$infos = array();
		$zahlungenResArr = array();
		$studiensemester_kurzbz = $this->_ci->dvuhconversionlib->convertSemesterToFHC($studiensemester);

		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$all_buchungstypen = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);

		// get paid Buchungen
		$buchungenResult = $this->_dbModel->execReadOnlyQuery(
			"SELECT *, sum(betrag) OVER (PARTITION BY buchungsnr_verweis) AS summe_zahlungen FROM
			(
				/* Use either amount of payment, or amount of charge if payment higher than charge */
				SELECT matr_nr, zlg.buchungsnr, zlg.buchungsdatum, zlg.buchungsnr_verweis, zlg.zahlungsreferenz, zlg.buchungstyp_kurzbz,
					   CASE WHEN zlg.betrag > abs(vorschr.betrag) THEN abs(vorschr.betrag) ELSE zlg.betrag END AS betrag,
					   zlg.studiensemester_kurzbz, matr_nr
				FROM public.tbl_konto zlg
				JOIN public.tbl_person USING (person_id)
				JOIN public.tbl_konto vorschr ON zlg.buchungsnr_verweis = vorschr.buchungsnr /* must have a charge */
				WHERE zlg.person_id = ?
				AND zlg.studiensemester_kurzbz = ?
				AND zlg.betrag > 0
				AND EXISTS (SELECT 1 FROM public.tbl_prestudent
								JOIN public.tbl_prestudentstatus USING (prestudent_id)
								WHERE tbl_prestudent.person_id = zlg.person_id
								AND tbl_prestudentstatus.studiensemester_kurzbz = zlg.studiensemester_kurzbz)
				AND NOT EXISTS (SELECT 1 from sync.tbl_dvuh_zahlungen /* payment not yet sent to DVUH */
								WHERE buchungsnr = zlg.buchungsnr
								AND betrag > 0)
				AND zlg.buchungstyp_kurzbz IN ?
			) zahlungen
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
				return $this->getResponseArr(
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
				$chargeRes = $this->_ci->DVUHZahlungenModel->getLastCharge(
					$buchung->buchungsnr_verweis
				);

				if (hasData($chargeRes))
				{
					$charge = getData($chargeRes)[0];

					if (abs($charge->betrag) != $buchung->summe_zahlungen)
					{
						return createError(
							"Buchung: ".$charge->buchungsnr.": Zahlungsbetrag abweichend von Vorschreibungsbetrag",
							'zlgUngleichVorschreibung',
							array($charge->buchungsnr), // text params
							array('buchungsnr_verweis' => $buchung->buchungsnr_verweis) // resolution params
						);
					}
				}
				else
				{
					// do not send payment and return warning if no charge was sent before payment
					return $this->getResponseArr(
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
				$payment->semester = $this->_ci->dvuhconversionlib->convertSemesterToDVUH($buchung->studiensemester_kurzbz);
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
					$postData = $this->_ci->ZahlungModel->retrievePostData(
						$this->_be,
						$payment->matrikelnummer,
						$payment->semester,
						$payment->zahlungsart,
						$payment->centbetrag,
						$payment->buchungsdatum,
						$payment->referenznummer
					);

					if (isError($postData))
						return $postData;

					$resultarr[] = $postData;
				}

				return $this->getResponseArr($resultarr);
			}

			foreach ($paymentsToSend as $payment)
			{
				$zahlungResult = $this->_ci->ZahlungModel->post(
					$this->_be,
					$payment->matrikelnummer,
					$payment->semester,
					$payment->zahlungsart,
					$payment->centbetrag,
					$payment->buchungsdatum,
					$payment->referenznummer
				);

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
							$zahlungenResArr[] = error("Zahlung erfolgreich, Fehler bei Speichern der Zahlung der Buchung "
								. $payment->buchungstyp . " in FHC");
						else
							$zahlungenResArr[] = success($xmlstr);
					}
				}
				else
					$zahlungenResArr[] = error("Fehler beim Senden der Zahlung");
			}
		}
		else
			return $this->getResponseArr(null, array("Keine nicht gemeldeten Buchungen gefunden"));

		return $this->getResponseArr($zahlungenResArr, $infos, null, true);
	}
}
