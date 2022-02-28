<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Heimatadresse missing
 */
class DVUH_SC_0003 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('person/Adresse_model', 'AdresseModel');

		// Check if there is a Heimatadresse
		$this->_ci->AdresseModel->addSelect('1');
		$adresseRes = $this->_ci->AdresseModel->loadWhere(
			array(
				'person_id' => $params['issue_person_id'],
				'heimatadresse'=> true
			)
		);

		if (isError($adresseRes))
			return $adresseRes;

		// resolved if Heimatadresse found
		if (hasData($adresseRes))
			return success(true);
		else
			return success(false);
	}
}
