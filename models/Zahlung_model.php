<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Manage Payments
 */
class Zahlung_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/rws/0.5/zahlung.xml';
	}

	/**
	 * Send Payment Information
	 *
	 * @param $matrikelnummer Matrikelnummer
	 * @param $be Code of Bildungseinrichtung
	 * @param $semester Semester
	 * @param $zahlungsart Type of Payment
	 * @param $centbetrag Amount of payed money in cents
	 * @param $buchungsdatum Date of payment
	 * @param $referenznummer Reference Number
	 */
	public function post($be, $matrikelnummer, $semester, $zahlungsart, $centbetrag, $buchungsdatum, $referenznummer)
	{
		if (isEmptyString($matrikelnummer))
			$result = error('Matrikelnummer nicht gesetzt');
		elseif (isEmptyString($semester))
			$result = error('Semester nicht gesetzt');
		elseif (isEmptyString($zahlungsart))
			$result = error('Zahlungsart nicht gesetzt');
		elseif (isEmptyString($centbetrag))
			$result = error('Centbetrag nicht gesetzt');
		elseif (isEmptyString($zahlungsart))
			$result = error('Buchungsdatum nicht gesetzt');
		elseif (isEmptyString($referenznummer))
			$result = error('Referenznummer nicht gesetzt');
		else
		{
			$params = array(
				"uuid" => getUUID(),
				"be" => $be,
				"matrikelnummer" => $matrikelnummer,
				"semester" => $semester,
				"zahlungsart" => $zahlungsart, // 1 = Bankomatzahlung, 2 = Quickzahlung
				"centbetrag" => $centbetrag,
				"buchungsdatum" => $buchungsdatum,
				"referenznummer" => $referenznummer
			);

			$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/zahlung', $params, true);
			$result = $this->_call('POST', null, $postData);
		}

		return $result;
	}
}
