<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Berufstätigkeit code missing
 */
class DVUH_SS_W_0005 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['prestudent_id']) || !is_numeric($params['prestudent_id']))
			return error('Prestudent Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');

		// load berufstätigkeit code for prestudent
		$this->_ci->PrestudentModel->addSelect('berufstaetigkeit_code');
		$prestudentRes = $this->_ci->PrestudentModel->load($params['prestudent_id']);

		if (isError($prestudentRes))
			return $prestudentRes;

		if (hasData($prestudentRes))
		{
			$berufstaetigkeit_code = getData($prestudentRes)[0]->berufstaetigkeit_code;

			// only if berufstaetigkeit code exists (0 code also counts!), resolve
			if (!isset($berufstaetigkeit_code) || !is_numeric($berufstaetigkeit_code))
				return success(false);
			else
				return success(true);
		}
		else
			return success(false);
	}
}
