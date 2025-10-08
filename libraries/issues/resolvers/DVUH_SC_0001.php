<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Matrikelnummer missing
 */
class DVUH_SC_0001 implements IIssueResolvedChecker
{
	public function checkIfIssueIsResolved($params)
	{
		if (!isset($params['issue_person_id']) || !is_numeric($params['issue_person_id']))
			return error('Person Id missing, issue_id: '.$params['issue_id']);

		$this->_ci =& get_instance(); // get code igniter instance
		$this->_ci->load->model('person/Person_model', 'PersonModel');

		// get student without matrikelnummer, who is not rejected
		$query = "
			SELECT 1 FROM
			(
				SELECT
					DISTINCT ON (prestudent_id) prestudent_id, status_kurzbz
				FROM
				(
					SELECT
						ps.prestudent_id, pss.status_kurzbz, pss.datum, pss.insertamum
					FROM
						public.tbl_person p
						JOIN public.tbl_prestudent ps USING (person_id)
						JOIN public.tbl_prestudentstatus pss USING (prestudent_id)
					WHERE
						(matr_nr IS NULL OR matr_nr = '')
						AND p.person_id = ?
				) status
				ORDER BY
					prestudent_id, datum DESC, insertamum DESC
			) letzte_status
			WHERE
				status_kurzbz <> 'Abgewiesener'";

		$personRes = $this->_ci->PersonModel->execReadOnlyQuery($query, [$params['issue_person_id']]);

		if (isError($personRes))
			return $personRes;

		// check if there is an entry without matrikelnummer, issue is not resolved
		if (hasData($personRes))
			return success(false);
		else
			return success(true); // issue resolved
	}
}
