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
		$this->_url = 'zahlung.xml';
	}

	/**
	 * Send Payment Information.
	 * @param string $matrikelnummer Matrikelnummer
	 * @param string $be Code of Bildungseinrichtung
	 * @param string $semester Semester
	 * @param string $zahlungsart Type of Payment
	 * @param float $centbetrag Amount of payed money in cents
	 * @param string $buchungsdatum Date of payment
	 * @param string $referenznummer Reference Number
	 * @return object success or error
	 */
	public function post($be, $matrikelnummer, $semester, $zahlungsart, $centbetrag, $buchungsdatum, $referenznummer)
	{
		$postData = $this->retrievePostData($be, $matrikelnummer, $semester, $zahlungsart, $centbetrag, $buchungsdatum, $referenznummer);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->_call('POST', null, getData($postData));

		return $result;
	}

	/**
	 * Retrieves necessary xml data for sending payments for a student.
	 * @param $be
	 * @param $matrikelnummer
	 * @param $semester
	 * @param $zahlungsart
	 * @param $centbetrag
	 * @param $buchungsdatum
	 * @param $referenznummer
	 * @return object success or error
	 */
	public function retrievePostData($be, $matrikelnummer, $semester, $zahlungsart, $centbetrag, $buchungsdatum, $referenznummer)
	{
		if (isEmptyString($matrikelnummer))
			$result = error($this->p->t('dvuh', 'matrikelnummerNichtGesetzt'));
		elseif (isEmptyString($semester))
			$result = error($this->p->t('dvuh', 'semesterNichtGesetzt'));
		elseif (isEmptyString($zahlungsart))
			$result = error($this->p->t('dvuh', 'zahlungsartNichtGesetzt'));
		elseif (isEmptyString($centbetrag))
			$result = error($this->p->t('dvuh', 'centbetragNichtGesetzt'));
		elseif (isEmptyString($buchungsdatum))
			$result = error($this->p->t('dvuh', 'buchungsdatumNichtGesetzt'));
		elseif (isEmptyString($referenznummer))
			$result = error($this->p->t('dvuh', 'referenznummberNichtGesetzt'));
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
			$result = success($postData);
		}

		return $result;
	}
}
