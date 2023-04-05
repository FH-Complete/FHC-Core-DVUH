<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ZGV Datum missing
 */
class DVUH_SS_W_0002 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['prestudent_id']) || !is_numeric($params['prestudent_id']))
			return error('Prestudent Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');

		// load zgvdatum for prestudent
		$this->_ci->PrestudentModel->addSelect('zgvdatum');
		$prestudentRes = $this->_ci->PrestudentModel->load($params['prestudent_id']);

		if (isError($prestudentRes))
			return $prestudentRes;

		// if zgv datum exists, resolve
		if (hasData($prestudentRes))
		{
			if (isEmptyString(getData($prestudentRes)[0]->zgvdatum))
				return success(false);
			else
				return success(true);
		}
		else
			return success(false);
	}
}
