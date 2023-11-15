<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Different person with same EKZ already exists.
 */
class DVUH_RE_0001 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		var_dump("DRINN");
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['existing_person_id']) || !is_numeric($params['existing_person_id']))
			return error('Existing person Id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['ersatzkennzeichen']) || isEmptyString($params['ersatzkennzeichen']))
			return error('Ersatzkennzeichen missing, issue_id: '.$params['issue_id']);

		//$this->_ci =& get_instance(); // get code igniter instance

		//~ $this->_ci->load->model('person/Person_model', 'PersonModel');

		// Get the two persons which are supposed to have the same Ersatzkennzeichen
		//~ $this->_ci->
		//~ $this->_ci->PersonModel->addSelect('ersatzkennzeichenn');
		//~ $personRes = $this->_ci->PersonModel->loadWhere(
			//~ ''
			//~ array(
				//~ 'person_id' => array($params['issue_person_id'], $params['existing_person_id']),
				//~ 'ersatzkennzeichen <> ' => null
			//~ )
		//~ );


		$db = new DB_Model();

		$params = array($params['issue_person_id'], $params['existing_person_id'], $params['ersatzkennzeichen']);

		// issue persists when Ersatzkennzeichen not assigned to person, and another person with the ekz to be assigned still exists.
		$qry = "
				SELECT
					ersatzkennzeichen
				FROM
					public.tbl_person
				WHERE
					person_id = ?
					AND ersatzkennzeichen IS NULL

				UNION

				SELECT
					ersatzkennzeichen
				FROM
					public.tbl_person
				WHERE
					person_id = ?
					AND ersatzkennzeichen = ?";

		$personRes = $db->execReadOnlyQuery($qry, $params);

		var_dump($personRes);

		if (isError($personRes))
			return $personRes;

		// if two persons - not resolved
		// resolved if only one person found - likely, they have been merged, or another ekz has been assigned.
		return success(!hasData($personRes) || count(getData($personRes)) <= 1);
	}
}
