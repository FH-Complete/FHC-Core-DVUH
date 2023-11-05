<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Studienkennung Uni invalid
 */
class DVUH_SS_0014 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['gsprogramm_id']))
			return error('Gsprogramm Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('codex/Gsprogramm_model', 'GsprogrammModel');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

		// load personenkennzeichen (saved in field matrikelnr) for a student uid
		$this->_ci->GsprogrammModel->addSelect('studienkennung_uni');
		$gsprogrammRes = $this->_ci->GsprogrammModel->load(array('gsprogramm_id' => $params['gsprogramm_id']));

		if (isError($gsprogrammRes))
			return $gsprogrammRes;

		if (hasData($gsprogrammRes))
		{
			// call method for checking Studienkennung Uni and resolve if valid
			$perskzCheck = $this->_ci->dvuhcheckinglib->checkStudienkennunguni(getData($gsprogrammRes)[0]->studienkennung_uni);

			if ($perskzCheck)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false);
	}
}
