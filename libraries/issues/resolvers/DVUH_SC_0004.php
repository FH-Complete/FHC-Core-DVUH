<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Adresse invalid
 */
class DVUH_SC_0004 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['adresse_id']) || !is_numeric($params['adresse_id']))
			return error('Adresse Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('person/Adresse_model', 'AdresseModel');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

		// load the adresse
		$this->_ci->AdresseModel->addSelect('strasse, plz, gemeinde, nation, ort');
		$adresseDataRes = $this->_ci->AdresseModel->load($params['adresse_id']);

		if (isError($adresseDataRes))
			return $adresseDataRes;

		if (hasData($adresseDataRes))
		{
			$adresseData = getData($adresseDataRes)[0];

			// build array with infos for adresse check

			// ort - comes from Gemeinde Feld, from Ort if Gemeinde empty and address not austrian
			$ort = null;
			if (isset($adresseData->gemeinde))
				$ort = $adresseData->gemeinde;
			elseif ($adresseData->nation !== 'A')
				$ort = $adresseData->ort;

			$addr = array();
			$addr['ort'] = $ort;
			$addr['plz'] = $adresseData->plz;
			$addr['strasse'] = $adresseData->strasse;
			$addr['staat'] = $adresseData->nation;

			// call check method
			$addrCheck = $this->_ci->dvuhcheckinglib->checkAdresse($addr);

			// resolved if adresse valid, if it returns a success
			if (isSuccess($addrCheck))
				return success(true);
			else
				return success(false);
		}
		else
			return success(false); // if no adresse found, not resolved
	}
}
