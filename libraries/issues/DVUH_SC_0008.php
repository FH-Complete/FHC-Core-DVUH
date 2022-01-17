<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Email invalid
 */
class DVUH_SC_0008 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['kontakt_id']) || !is_numeric($params['kontakt_id']))
			return error('Kontakt Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('person/Kontakt_model', 'KontaktModel');
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load the mail kontakt
		$this->_ci->KontaktModel->addSelect('kontakt');
		$kontaktRes = $this->_ci->KontaktModel->load($params['kontakt_id']);

		if (isError($kontaktRes))
			return $kontaktRes;

		if (hasData($kontaktRes))
		{
			// check if valid string to send to DVUH
			$emailCheck = validateXmlTextValue(getData($kontaktRes)[0]->kontakt);

			// resolved if valid
			if ($emailCheck)
				return success(true);
			else
				return success(false);
		}
		else
			return success(false); // not resolved if kontakt not found
	}
}
