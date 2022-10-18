<?php

/**
 * Functionality for managing DVUH issues
 */
class DVUHIssueLib
{
	const APP = 'dvuh';

	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library(
			'IssuesLib',
			array(
				'app' => self::APP,
				'insertvon' => 'dvuhsync',
				'fallbackFehlercode' => 'DVUH_ERROR'
			)
		);

		// load models
		$this->_ci->load->model('crm/prestudent_model', 'PrestudentModel');
		$this->_ci->load->model('system/Fehler_model', 'FehlerModel');
	}

	/**
	 * Initializes adding of an issue.
	 * @param object $errorObj containing info for writing the issue.
	 * @param int $person_id person for which issue occured.
	 * @param int $prestudent_id prestudent for which issue occured, will be resolved to oe_kurzbz.
	 * @param bool $force_predefined_for_external if true, external issues won't be added if no error/warning is predefined
	 * @return object success or error
	 */
	public function addIssue($errorObj, $person_id = null, $prestudent_id = null, $force_predefined_for_external = false)
	{
		$oe_kurzbz = null;
		$errorData = getError($errorObj);

		// add as issue to be processed
		if (isset($errorData) && (isset($person_id) || isset($prestudent_id)))
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

			// if only a single issue, wrap into array
			if (isEmptyArray($errorData))
				$errorData = array($errorData);

			// optimistic assumption
			$issuesResObj = success('Successfully added issue(s)');
			$issuesErrorArr = array();

			foreach($errorData as $error)
			{
				if (isset($error->fehlernummer)) // has fehlernummer if external error
				{
					// get external fehlercode (unique for each app)
					$this->_ci->FehlerModel->addSelect('fehlercode');
					$fehlerRes = $this->_ci->FehlerModel->loadWhere(
						array(
							'fehlercode_extern' => $error->fehlernummer,
							'app' => self::APP
						)
					);

					if (isError($fehlerRes))
						return $fehlerRes;

					// check if there is a predefined custom error for the external issue
					if (!hasData($fehlerRes))
					{
						if ($force_predefined_for_external)
							return success("External issue not added because it is not defined in FHC"); // TODO phrases
					}

					// add the external issue
					$extIssueRes = $this->_ci->issueslib->addExternalIssue(
						$error->fehlernummer,
						$error->issue_fehlertext,
						$person_id,
						$oe_kurzbz
					);

					// if error when adding issue, add person Id, prestudent Id to error text
					if (isError($extIssueRes))
					{
						$errorText = getError($extIssueRes);
						$errorText .= ', fehler code extern: '.$error->fehlernummer;
						if (isset($person_id)) $errorText .= ', person Id: '.$person_id;
						if (isset($prestudent_id)) $errorText .= ', prestudent Id: '.$prestudent_id;
						$issuesErrorArr[] = $errorText;
					}
				}
				elseif (isset($error->issue_fehler_kurzbz)) // add custom fhc error if no fehlernummer, but issue_kurzbz
				{
					$addIssueRes = $this->_ci->issueslib->addFhcIssue(
						$error->issue_fehler_kurzbz,
						$person_id,
						$oe_kurzbz,
						$error->issue_fehlertext_params,
						$error->issue_resolution_params
					);

					// if error when adding issue, add person Id, prestudent Id to error text
					if (isError($addIssueRes))
					{
						$errorText = getError($addIssueRes);
						$errorText .= ', fehler kurzbz: '.$error->issue_fehler_kurzbz;
						if (isset($person_id)) $errorText .= ', person Id: '.$person_id;
						if (isset($prestudent_id)) $errorText .= ', prestudent Id: '.$prestudent_id;
						$issuesErrorArr[] = $errorText;
					}
				}
			}

			// return error object if errors occured when writing issues
			if (!isEmptyArray($issuesErrorArr))
				$issuesResObj = error('Error when adding issue(s)', $issuesErrorArr);

			return $issuesResObj;
		}

		return success('No issues to add');
	}

	/**
	 *
	 * @param
	 * @return object success or error
	 */
	public function getIssueString($issue)
	{
		$issueString = '';

		if (isError($issue))
		{
			$issueData = getError($issueData);

			if (is_string($issueData))
				return $issueData;


			if (is_array($issueData))
			{
				foreach ($issueData as $data)
				{
					if (isset($data->issue_fehlertext))
					{
						if (isset($data->issue_fehlertext_params) && is_array($data->issue_fehlertext_params)
							&& count($data->issue_fehlertext_params) == substr_count($data->issue_fehlertext, '%s'))
						{
							$issueString .= vsprintf($data->issue_fehlertext, $data->issue_fehlertext_params);
						}
					}
				}
			}
			elseif (isset($issueData->issue_fehlertext))
				return $issueData->issue_fehlertext;
		}

		return $issueString;
	}
}
