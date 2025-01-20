<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Different person with same EKZ already exists.
 */
class DVUH_RE_0001 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['existing_person_id']) || !is_numeric($params['existing_person_id']))
			return error('Existing person Id missing, issue_id: '.$params['issue_id']);

		if (!isset($params['ersatzkennzeichen']) || isEmptyString($params['ersatzkennzeichen']))
			return error('Ersatzkennzeichen missing, issue_id: '.$params['issue_id']);

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

		if (isError($personRes))
			return $personRes;

		// if two persons - not resolved
		// resolved if only one person found - likely, they have been merged, or another ekz has been assigned.
		return success(!hasData($personRes) || numberOfElements(getData($personRes)) <= 1);
	}
}
