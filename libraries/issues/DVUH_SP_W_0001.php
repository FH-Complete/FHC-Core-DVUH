<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Fixed Studierendenbeitrag for semester must be equal to charge amount (without insurance)
 */
class DVUH_SP_W_0001 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['buchungsnr']) || !is_numeric($params['buchungsnr']))
			return error('Buchungsnr missing, issue_id: '.$params['issue_id']);

		if (!isset($params['studiensemester_kurzbz']))
			return error('Studiensemester missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		// get buchung for check
		$this->_ci->load->model('crm/Konto_model', 'KontoModel');

		$this->_ci->KontoModel->addSelect('betrag');
		$buchungResult = $this->_ci->KontoModel->load($params['buchungsnr']);

		if (isError($buchungResult))
			return $buchungResult;

		// get Oehbeitrag amounts fixed for semester for check
		$this->_ci->load->model('codex/Oehbeitrag_model', 'OehbeitragModel');

		$oehbeitragRes = $this->_ci->OehbeitragModel->getByStudiensemester($params['studiensemester_kurzbz']);

		if (isError($oehbeitragRes))
			return $oehbeitragRes;

		// check if Buchung amount is equal to fixed Oehbeitrag amount
		if (hasData($buchungResult) && hasData($oehbeitragRes))
		{
			// get oehbeitrag versicherung and studierendenbeitrag
			$oehbeitragData = getData($oehbeitragRes);
			$oehbeitragVersicherung = $oehbeitragData[0]->versicherung;
			$oehbeitragStudierendenbeitrag = $oehbeitragData[0]->studierendenbeitrag;

			// get the betrag after subtraction of versicherung
			$buchungBetrag = getData($buchungResult)[0]->betrag;
			$buchungStudierendenBetrag = abs((float) $buchungBetrag) - (float) $oehbeitragVersicherung;

			// check if betrag and studierendenbeitrag are equal
			if (abs($buchungStudierendenBetrag) === (float) $oehbeitragStudierendenbeitrag)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false);
	}
}
