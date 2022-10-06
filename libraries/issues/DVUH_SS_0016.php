<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GS data is missing
 */
class DVUH_SS_0016 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['mobilitaet_id']) || !is_numeric($params['mobilitaet_id']))
			return error('Mobilitaet_id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['fehlendes_feld']) || isEmptyString($params['fehlendes_feld']))
			return error('Fehlendes Feld missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('codex/Mobilitaet_model', 'MobilitaetModel');

		// get gsprogramme
		$this->_ci->MobilitaetModel->addSelect($params['fehlendes_feld']);
		$this->_ci->MobilitaetModel->addJoin('bis.tbl_gsprogramm', 'gsprogramm_id', 'LEFT');
		$this->_ci->MobilitaetModel->addJoin('public.tbl_firma', 'firma_id', 'LEFT');
		$mobilitaetRes = $this->_ci->MobilitaetModel->loadWhere(array('mobilitaet_id' => $params['mobilitaet_id']));

		if (isError($mobilitaetRes))
			return $mobilitaetRes;

		$value = getData($mobilitaetRes)[0]->{$params['fehlendes_feld']};

		if (hasData($mobilitaetRes) && isset($value))
			return success(true); // resolved if field set
		else
			return success(false); // not resolved if field not set
	}
}
