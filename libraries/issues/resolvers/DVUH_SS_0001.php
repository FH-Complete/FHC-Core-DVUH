<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Matrikelnummer invalid
 */
class DVUH_SS_0001 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

		// load Matrikelnummer of given person
		$this->_ci->PersonModel->addSelect('matr_nr');
		$personRes = $this->_ci->PersonModel->load($params['issue_person_id']);

		if (isError($personRes))
			return $personRes;

		if (hasData($personRes))
		{
			// check Matrikelnummer, resolve if valid
			$matrnrCheck = $this->_ci->dvuhcheckinglib->checkMatrikelnummer(getData($personRes)[0]->matr_nr);

			if ($matrnrCheck)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false);
	}
}
