<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Titel pre invalid
 */
class DVUH_SC_0011 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

		// load Titel of given person
		$this->_ci->PersonModel->addSelect('titelpre');
		$personRes = $this->_ci->PersonModel->load($params['issue_person_id']);

		if (isError($personRes))
			return $personRes;

		if (hasData($personRes))
		{
			// call method for checking Titel
			$titelCheck = $this->_ci->dvuhcheckinglib->checkTitel(getData($personRes)[0]->titelpre);

			// resolved if Titel valid
			if ($titelCheck)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false); // not resolved if no Titel found
	}
}
