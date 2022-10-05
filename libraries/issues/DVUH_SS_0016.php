<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GS data is missing
 */
class DVUH_SS_0016 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['gsprogramm_id']) || !is_numeric($params['gsprogramm_id']))
			return error('Gsprogramm missing, issue_id: '.$params['issue_id']);

		if (!isset($params['fehlendes_feld']) || !is_numeric($params['fehlendes_feld']))
			return error('Fehlendes Feld missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('codex/Mobilitaet_model', 'MobilitaetModel');

		// get all bisio
		$this->_ci->MobilitaetModel->addSelect($params['fehlendes_feld']);
		$this->_ci->MobilitaetModel->addJoin('bis.tbl_gsprogramm', 'gsprogramm_id', 'LEFT');
		$this->_ci->MobilitaetModel->addJoin('public.tbl_firma', 'firma_id', 'LEFT');
		$mobilitaetRes = $this->_ci->MobilitaetModel->loadWhere(array('gsprogramm_id' => $params['gsprogramm_id']));

		if (isError($mobilitaetRes))
			return $mobilitaetRes;

		$value = getData($mobilitaetRes)[0]->{$field};
		if (hasData($mobilitaetRes) && isset($value) && is_numeric($value))
			return success(true); // resolved if Herkunftsland set
		else
			return success(false); // not resolved if Herkunftsland not set
	}
}
