<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Charge must be present before sending payment to DVUH
 */
class DVUH_SP_W_0002 implements IIssueResolvedChecker
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

		// resolved if there is a charge
		if (hasData($chargeRes))
			return success(true);
		else
			return success(false);
	}
}
