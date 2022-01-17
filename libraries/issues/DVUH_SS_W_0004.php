<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ZGV master datum missing
 */
class DVUH_SS_W_0004 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['prestudent_id']) || !is_numeric($params['prestudent_id']))
			return error('Prestudent Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');

		// load zgv master datum for prestudent
		$this->_ci->PrestudentModel->addSelect('zgvmadatum');
		$prestudentRes = $this->_ci->PrestudentModel->load($params['prestudent_id']);

		if (isError($prestudentRes))
			return $prestudentRes;

		if (hasData($prestudentRes))
		{
			// if zgv master datum exists, resolve
			if (isEmptyString(getData($prestudentRes)[0]->zgvmadatum))
				return success(false);
			else
				return success(true);
		}
		else
			return success(false);
	}
}
