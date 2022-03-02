<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * There are still unpaid Buchungen
 */
class DVUH_SP_W_0003 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['studiensemester_kurzbz']))
			return error('Studiensemester missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		// load config and get buchungstypen which need to be checked
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$buchungstypen = $this->_ci->config->item('fhc_dvuh_buchungstyp');
		$all_buchungstypen = array_merge($buchungstypen['oehbeitrag'], $buchungstypen['studiengebuehr']);

		// check if there are unpaid Buchungen
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');

		$unpaidBuchungen = $this->_ci->fhcmanagementlib->getUnpaidBuchungen(
			$params['issue_person_id'],
			$params['studiensemester_kurzbz'],
			$all_buchungstypen
		);

		if (isError($unpaidBuchungen))
			return $unpaidBuchungen;

		// resolved if there are unpaid Buchungen
		if (hasData($unpaidBuchungen))
			return success(false);
		else
			return success(true);
	}
}
