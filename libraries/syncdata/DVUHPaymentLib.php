<?php

require_once 'DVUHErrorProducerLib.php';

/**
 * Library for retrieving payment data from FHC for DVUH.
 * Extracts data from FHC db, performs data quality checks and puts data in DVUH form.
 */
class DVUHPaymentLib extends DVUHErrorProducerLib
{
	protected $_ci;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');

		// load models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHZahlungen_model', 'DVUHZahlungenModel');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');

		$this->_dbModel = new DB_Model(); // get db
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Retrieves payment data, performs checks, prepares data for DVUH.
	 * @param $person_id
	 * @param $studiensemester_kurzbz
	 * @return object success with payment info or error
	 */
	public function getPaymentData($person_id, $studiensemester_kurzbz)
	{
		$paymentsToSend = array();

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
				$this->addWarning(
					"Es gibt noch offene Buchungen.",
					'offeneBuchungen',
					null,
					array('studiensemester_kurzbz' => $studiensemester_kurzbz)
				);
				// do not send any payment if at least one payment still open
				return success();
			}

			$buchungen = getData($buchungenResult);

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
						$this->addError(
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
					$this->addWarning(
						"Buchung $buchungsnr: Zahlung nicht gesendet, vor der Zahlung wurde keine Vorschreibung an DVUH gesendet",
						'zlgKeineVorschreibungGesendet',
						array($buchungsnr),
						array('buchungsnr_verweis' => $buchung->buchungsnr_verweis)
					);
					return success();
				}

				$payment = new stdClass();
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
		}

		if ($this->hasError())
			return error($this->readErrors());

		return success($paymentsToSend);
	}
}
