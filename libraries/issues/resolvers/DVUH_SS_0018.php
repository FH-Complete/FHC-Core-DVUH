<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Students should be reported to DVUH in time
 */
class DVUH_SS_0018 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['prestudent_id']) || !is_numeric($params['prestudent_id']))
			return error('Prestudent Id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['studiensemester_kurzbz']) || isEmptyString($params['studiensemester_kurzbz']))
			return error('Studiensemester missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->library('extensions/FHC-Core-DVUH/issues/plausichecks/NichtGemeldeteStudierende');

		// check if issue persists
		$checkRes = $this->_ci->nichtgemeldetestudierende->getNichtGemeldeteStudierende(
			$params['studiensemester_kurzbz'],
			$params['prestudent_id'],
			null,
			$params['issue_id']
		);

		if (isError($checkRes)) return $checkRes;

		if (hasData($checkRes))
			return success(false); // not resolved if issue is still present
		else
			return success(true); // resolved otherwise
	}
}
