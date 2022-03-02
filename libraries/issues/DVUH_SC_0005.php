<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Ersatzkennzeichen invalid
 */
class DVUH_SC_0005 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');

		// load ersatzkennzeichen for the given person
		$this->_ci->PersonModel->addSelect('ersatzkennzeichen');
		$personRes = $this->_ci->PersonModel->load($params['issue_person_id']);

		if (isError($personRes))
			return $personRes;

		if (hasData($personRes))
		{
			// call ersatzkennzeichen check method
			$ekzCheck = $this->_ci->dvuhsynclib->checkEkz(getData($personRes)[0]->ersatzkennzeichen);

			// if returns true, ekz is valid, issue resolved
			if ($ekzCheck)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false); // if no ekz found, not resolved
	}
}
