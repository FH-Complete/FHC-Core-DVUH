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
			$result = error('Matrikelnummer not set');
		elseif (isEmptyString($semester))
			$result = error('Semester not set');
		elseif (isEmptyString($zahlungsart))
			$result = error('Zahlungsart not set');
		elseif (isEmptyString($centbetrag))
			$result = error('Centbetrag not set');
		elseif (isEmptyString($zahlungsart))
			$result = error('Buchungsdatum not set');
		elseif (isEmptyString($referenznummer))
			$result = error('Referenznummer not set');
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
