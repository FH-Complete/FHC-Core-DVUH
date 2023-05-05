<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Payment invalid
 */
class DVUH_SC_0013 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['studiensemester_kurzbz']) || isEmptyString($params['studiensemester_kurzbz']))
			return error('Studiensemester missing, issue_id: '.$params['issue_id']);

		if (!isset($params['buchungstypen']) || isEmptyArray($params['buchungstypen']))
			return error('Buchungstypen missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHStammdatenLib');

		$buchungstypen = $params['buchungstypen'];
		$studiensemester_kurzbz = $params['studiensemester_kurzbz'];

		// get Vorschreibung data from the found charges

		// get charges of a student
		$vorschreibung = $this->_ci->dvuhstammdatenlib->getVorschreibungData($params['issue_person_id'], $studiensemester_kurzbz, $buchungstypen);

		// if the issue does not persist, no error is returned
		if (isSuccess($vorschreibung))
			return success(true);

		return success(false); // not resolved
	}
}
