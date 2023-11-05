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
		$this->_ci->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHPaymentLib');

		// load models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Zahlung_model', 'ZahlungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHZahlungen_model', 'DVUHZahlungenModel');
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
		$warnings = array();
		$errors = array();
		$zahlungenResArr = array();
		$studiensemester_kurzbz = $this->_ci->dvuhconversionlib->convertSemesterToFHC($studiensemester);

		$paymentsToSendRes = $this->_ci->dvuhpaymentlib->getPaymentData($person_id, $studiensemester_kurzbz);

		// get and reset warnings occured when getting payment data
		$errors = $this->_ci->dvuhpaymentlib->readErrors();
		$warnings = $this->_ci->dvuhpaymentlib->readWarnings();

		if (isError($paymentsToSendRes))
		{
			return $paymentsToSendRes;
		}
		elseif (hasData($paymentsToSendRes))
		{
			$paymentsToSend = getData($paymentsToSendRes);
			// preview - only show data to be sent
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
			return $this->getResponseArr(null, array("Keine zu meldenden Buchungen gefunden"), $warnings);

		return $this->getResponseArr($zahlungenResArr, $infos, $warnings, true);
	}
}
