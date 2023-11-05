<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Oehbeitrag amount not defined for studiensemester
 */
class DVUH_SC_0009 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['studiensemester_kurzbz']))
			return error('Studiensemester missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('codex/Oehbeitrag_model', 'OehbeitragModel');

		// get oehbeitrag defined for given student semester
		$oehbeitragRes = $this->_ci->OehbeitragModel->getByStudiensemester($params['studiensemester_kurzbz']);

		if (isError($oehbeitragRes))
			return $oehbeitragRes;

		// resolved if oehbeitrag found
		if (hasData($oehbeitragRes))
			return success(true);
		else
			return success(false);
	}
}
