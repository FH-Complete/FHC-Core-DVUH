<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Herkunfstland is missing
 */
class DVUH_SS_0015 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['bisio_id']) || !is_numeric($params['bisio_id']))
			return error('Bisio Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('codex/Bisio_model', 'BisioModel');

		// get all bisio
		$this->_ci->BisioModel->addSelect('herkunftsland_code');
		$bisioRes = $this->_ci->BisioModel->loadWhere(array('bisio_id' => $params['bisio_id']));

		var_dump($bisioRes);

		if (isError($bisioRes))
			return $bisioRes;

		if (hasData($bisioRes) && !isEmptyString(getData($bisioRes)[0]->herkunftsland_code))
			return success(true); // resolved if Herkunftsland set
		else
			return success(false); // not resolved if Herkunftsland not set
	}
}
