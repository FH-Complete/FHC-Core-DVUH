<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Charge and payment for the buchung must be equal
 */
class DVUH_SP_0001 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['buchungsnr_verweis']) || !is_numeric($params['buchungsnr_verweis']))
			return error('Buchungsnr Verweis missing, issue_id: '.$params['issue_id']);

		$buchungsnr_verweis = $params['buchungsnr_verweis'];

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHZahlungen_model', 'DVUHZahlungenModel');

		// get charge for Buchung to check
		$chargeRes = $this->_ci->DVUHZahlungenModel->getLastCharge($buchungsnr_verweis);

		if (isError($chargeRes))
			return $chargeRes;

		// get sum of all payments for the Buchung to check
		$this->_ci->load->model('crm/Konto_model', 'KontoModel');

		$this->_ci->KontoModel->addSelect('sum(betrag) as summe_zahlungen');
		$this->_ci->KontoModel->addGroupBy('buchungsnr_verweis');
		$buchungVerweisResult = $this->_ci->KontoModel->loadWhere(array('buchungsnr_verweis' => $buchungsnr_verweis));

		if (isError($buchungVerweisResult))
			return $buchungVerweisResult;

		// check if charge and payment for the buchung are set and equal
		if (hasData($chargeRes) && hasData($buchungVerweisResult))
		{
			if (abs((float) getData($chargeRes)[0]->betrag) === (float) getData($buchungVerweisResult)[0]->summe_zahlungen)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false);
	}
}
