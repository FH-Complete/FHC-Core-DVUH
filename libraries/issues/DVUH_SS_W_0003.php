<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ZGV master missing
 */
class DVUH_SS_W_0003 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['prestudent_id']) || !is_numeric($params['prestudent_id']))
			return error('Prestudent Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');

		// load zgv master code for prestudent
		$this->_ci->PrestudentModel->addSelect('zgvmas_code');
		$prestudentRes = $this->_ci->PrestudentModel->load($params['prestudent_id']);

		if (isError($prestudentRes))
			return $prestudentRes;

		// if zgv master exists, resolve
		if (hasData($prestudentRes))
		{
			if (isEmptyString(getData($prestudentRes)[0]->zgvmas_code))
				return success(false);
			else
				return success(true);
		}
		else
			return success(false);
	}
}
