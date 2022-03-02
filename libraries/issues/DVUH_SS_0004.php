<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Personenkennzeichen invalid
 */
class DVUH_SS_0004 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['student_uid']))
			return error('Student uid missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('crm/Student_model', 'StudentModel');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');

		// load personenkennzeichen (saved in field matrikelnr) for a student uid
		$this->_ci->StudentModel->addSelect('matrikelnr');
		$studentRes = $this->_ci->StudentModel->load(array('student_uid' => $params['student_uid']));

		if (isError($studentRes))
			return $studentRes;

		if (hasData($studentRes))
		{
			// call method for checking if personenkennzeichen and resolve if valid
			$perskzCheck = $this->_ci->dvuhsynclib->checkPersonenkennzeichen(trim(getData($studentRes)[0]->matrikelnr));

			if ($perskzCheck)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false);
	}
}
