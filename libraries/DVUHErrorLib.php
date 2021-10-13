<?php

/**
 * Functionality for parsing DVUH XML
 */
class DVUHErrorLib
{
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library(
			'IssuesLib',
			array(
				'app' => 'dvuh',
				'insertvon' => 'dvuhsync',
				'fallbackFehlercode' => 'DVUH_ERROR'
			)
		);

		// load models
		$this->_ci->load->model('crm/prestudent_model', 'PrestudentModel');
	}

	/**
	 * Initializes correct adding of an issue.
	 * @param object $errorObj containing info for writing the issue.
	 * @param int $person_id person for which issue occured.
	 * @param int $prestudent_id prestudent for which issue occured, will be resolved to oe_kurzbz.
	 * @param bool $force_predefined if true, external issues won't be added if no error/warning is predefined
	 * @return object success or error
	 */
	public function addIssue($errorObj, $person_id = null, $prestudent_id = null, $force_predefined = false)
	{
		$oe_kurzbz = null;
		$code = getCode($errorObj);

		// add as issue to be processed
		if (isset($code) && (isset($person_id) || isset($prestudent_id)))
		{
			// get person_id and oe_kurzbz from prestudent if necessary
			if (isset($prestudent_id))
			{
				$this->_ci->PrestudentModel->addSelect('person_id, oe_kurzbz');
				$this->_ci->PrestudentModel->addJoin('public.tbl_studiengang', 'studiengang_kz');
				$prestudentRes = $this->_ci->PrestudentModel->load($prestudent_id);

				if (isError($prestudentRes))
					return $prestudentRes;

				if (hasData($prestudentRes))
				{
					$prestudent = getData($prestudentRes)[0];
					if (!isset($person_id))
						$person_id = $prestudent->person_id;
					$oe_kurzbz = $prestudent->oe_kurzbz;
				}
				else
					return error("Kein Prestudent für Hinzufügen von Issue gefunden.");
			}

			if (isset($code->issue_fehler_kurzbz)) // custom, self-defined error
			{
				return $this->_ci->issueslib->addFhcIssue($code->issue_fehler_kurzbz, $person_id, $oe_kurzbz, $code->issue_fehlertext_params);
			}
			elseif (!isEmptyArray($code)) // external error from DVUH is an array
			{
				$issuesResObj = success('Successfully added issue(s)');
				$issuesErrorArr = array();

				foreach($code as $error)
				{
					if (isset($error->fehlernummer)) // has fehlernummer if external error
					{
						$extIssueRes = $this->_ci->issueslib->addExternalIssue(
							$error->fehlernummer,
							$error->fehlertextKomplett,
							$person_id,
							$oe_kurzbz,
							null,
							$force_predefined
						);

						if (isError($extIssueRes))
						{
							$issuesErrorArr[] = getError($extIssueRes);
						}
					}
				}

				if (!isEmptyArray($issuesErrorArr))
					$issuesResObj = error('Error when adding issue(s)', $issuesErrorArr);

				return $issuesResObj;
			}
		}

		return success('No issues to add');
	}
}
