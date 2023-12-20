<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Controller for initialising all DVUH jobs
 */
class DVUHManagement extends JQW_Controller
{
	const ERRORCODE_TOO_MANY_SZR_REQUESTS = 'ZD00001';

	private $_logInfos; // stores config param for info display

	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->load->library('extensions/FHC-Core-DVUH/DVUHIssueLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHMatrikelnummerManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHMasterDataManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHEkzManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHPaymentManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHStudyDataManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHPruefungsaktivitaetenManagementLib');

		// load configs and save "log infos" parameter
		$this->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$this->_logInfos = $this->config->item('fhc_dvuh_log_infos');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Initialises requestMatrikelnummer job, handles job queue, logs infos/errors
	 */
	public function requestMatrikelnummer()
	{
		$jobType = 'DVUHRequestMatrikelnummer';
		$this->logInfo('DVUHRequestMatrikelnummer job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			$person_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($person_arr as $persobj)
			{
				if (!isset($persobj->person_id) || !isset($persobj->studiensemester_kurzbz))
					$this->logError("Fehler bei Matrikelnummernabfrage, ungültige Parameter übergeben");
				else
				{
					$person_id = $persobj->person_id;
					$studiensemester_kurzbz = $persobj->studiensemester_kurzbz;

					$requestMatrnrResult = $this->dvuhmatrikelnummermanagementlib->requestMatrikelnummer($person_id, $studiensemester_kurzbz);

					if (isError($requestMatrnrResult))
					{
						$this->_logDVUHError(
							"Fehler bei Matrikelnummernvergabe, person Id $person_id, Studiensemester $studiensemester_kurzbz",
							$requestMatrnrResult,
							$person_id
						);
					}
					elseif (hasData($requestMatrnrResult))
					{
						$requestMatrnrArr = getData($requestMatrnrResult);

						$this->_logDVUHInfosAndWarnings($requestMatrnrArr, array('person_id' => $person_id));
					}
				}
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHRequestMatrikelnummer job stop');
	}

	/**
	 * Initialises sendCharge job, handles job queue, logs infos/errors
	 */
	public function sendCharge()
	{
		$jobType = 'DVUHSendCharge';
		$this->logInfo('DVUHSendCharge job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			$person_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($person_arr as $persobj)
			{
				if (!isset($persobj->person_id) || !isset($persobj->studiensemester_kurzbz))
					$this->logError("Fehler beim Senden der Vorschreibung, ungültige Parameter übergeben");
				else
				{
					$person_id = $persobj->person_id;
					$studiensemester = $persobj->studiensemester_kurzbz;

					$sendChargeResult = $this->dvuhmasterdatamanagementlib->sendMasterData($person_id, $studiensemester);

					if (isError($sendChargeResult))
					{
						$this->_logDVUHError(
							"Fehler beim Senden der Vorschreibung, Person Id $person_id, Studiensemester $studiensemester",
							$sendChargeResult,
							$person_id
						);
					}
					elseif (hasData($sendChargeResult))
					{
						$sendCharge = getData($sendChargeResult);

						$this->_logDVUHInfosAndWarnings($sendCharge, array('person_id' => $person_id));

						if (isset($sendCharge['result']))
							$this->_logDVUHInfoIfEnabled(
								"Stammdaten mit Vorschreibung Person Id $person_id, Studiensemester $studiensemester erfolgreich gesendet"
							);
					}
				}
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHSendCharge job stop');
	}

	/**
	 * Initialises sendPayment job, handles job queue, logs infos/errors
	 */
	public function sendPayment()
	{
		$jobType = 'DVUHSendPayment';
		$this->logInfo('DVUHSendPayment job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			$person_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($person_arr as $persobj)
			{
				$person_id = $persobj->person_id;
				$studiensemester = $persobj->studiensemester_kurzbz;

				if (!isset($persobj->person_id) || !isset($persobj->studiensemester_kurzbz))
					$this->logError("Fehler beim Senden der Zahlung, ungültige Parameter übergeben");
				else
				{
					$sendPaymentResult = $this->dvuhpaymentmanagementlib->sendPayment($person_id, $studiensemester);

					if (isError($sendPaymentResult))
					{
						$this->_logDVUHError(
							"Fehler beim Senden der Zahlung, Person Id $person_id, Studiensemester $studiensemester",
							$sendPaymentResult,
							$person_id
						);
					}
					elseif (hasData($sendPaymentResult))
					{
						$sendPaymentItems = getData($sendPaymentResult);

						$this->_logDVUHInfosAndWarnings($sendPaymentItems, array('person_id' => $person_id));

						if (isset($sendPaymentItems['result']) && is_array($sendPaymentItems['result']))
						{
							foreach ($sendPaymentItems['result'] as $paymentRes)
							{
								if (isError($paymentRes))
								{
									$this->_logDVUHError(
										"Fehler beim Senden der Zahlung, Person Id $person_id, Studiensemester $studiensemester",
										$paymentRes,
										$person_id
									);
								}
							}
						}
					}
				}
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHSendPayment job stop');
	}

	/**
	 * Initialises sendStudyData job, handles job queue, logs infos/errors
	 */
	public function sendStudyData()
	{
		$jobType = 'DVUHSendStudyData';
		$this->logInfo('DVUHSendStudyData job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			$prestudent_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($prestudent_arr as $prsobj)
			{
				$prestudent_id = $prsobj->prestudent_id;
				$studiensemester = $prsobj->studiensemester_kurzbz;

				if (!isset($prsobj->prestudent_id) || !isset($prsobj->studiensemester_kurzbz))
					$this->logError("Fehler beim Senden der Studiumdaten, ungültige Parameter übergeben");
				else
				{
					$sendStudyDataResult = $this->dvuhstudydatamanagementlib->sendStudyData($studiensemester, null, $prestudent_id);

					if (isError($sendStudyDataResult))
					{
						$this->_logDVUHError(
							"Fehler beim Senden der Studiumdaten, Prestudent Id $prestudent_id, studiensemester $studiensemester",
							$sendStudyDataResult,
							null,
							$prestudent_id
						);
					}
					elseif (hasData($sendStudyDataResult))
					{
						$sendStudyData = getData($sendStudyDataResult);

						$this->_logDVUHInfosAndWarnings($sendStudyData, array('prestudent_id' => $prestudent_id));

						if (isset($sendStudyData['result']))
						{
							$this->_logDVUHInfoIfEnabled(
								"Studiumdaten für prestudent Id $prestudent_id, studiensemester $studiensemester erfolgreich gesendet"
							);
						}
					}
				}
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHSendStudyData job stop');
	}

	/**
	 * Initialises requestBpk job, handles job queue, logs infos/errors
	 */
	public function requestBpk()
	{
		$jobType = 'DVUHRequestBpk';
		$this->logInfo('DVUHRequestBpk job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			$person_arr = $this->_getInputObjArray(getData($lastJobs));
			$maxResendBpkQueryCount = 5; // limit for sleeping times because of "too many szr requests" error
			$totalRequestAmount = 0; // threshold for total amount of API calls before sleep
			$sleepSecondsAmount = 30; // seconds to sleep for

			foreach ($person_arr as $persobj)
			{
				if (!isset($persobj->person_id))
					$this->logError("Fehler bei Bpkanfrage, ungültige Parameter übergeben");
				else
				{
					$person_id = $persobj->person_id;
					$continueLoop = true;
					$resendBpkQueryCount = 0; // number of times request has been made for this person

					while ($continueLoop)
					{
						$continueLoop = false;
						$requestBpkResult = $this->dvuhmasterdatamanagementlib->requestBpk($person_id);

						// sleep if number of requests exceeds threshold
						$totalRequestAmount++;
						if ($totalRequestAmount > $this->config->item('fhc_dvuh_sync_pruefe_bpk_max_requests'))
						{
							sleep($sleepSecondsAmount);
							// reset request amount
							$totalRequestAmount = 0;
						}

						if (isError($requestBpkResult))
						{
							$errCode = getError($requestBpkResult);

							if (isset($errCode) && is_array($errCode))
							{
								foreach ($errCode as $code)
								{
									// if "too many szr requests per minute" error, sleep and retry for the person.
									if (isset($code->fehlernummer) && $code->fehlernummer == self::ERRORCODE_TOO_MANY_SZR_REQUESTS)
									{
										$resendBpkQueryCount++;
										if ($resendBpkQueryCount <= $maxResendBpkQueryCount)
										{
											sleep($sleepSecondsAmount);
											$continueLoop = true;
										}
									}
								}
							}

							if (!$continueLoop || $resendBpkQueryCount > $maxResendBpkQueryCount)
							{
								$this->_logDVUHError(
									"Fehler bei Bpkanfrage, person Id $person_id",
									$requestBpkResult,
									$person_id
								);
							}
						}
						elseif (hasData($requestBpkResult))
						{
							$requesBpkArr = getData($requestBpkResult);

							$this->_logDVUHInfosAndWarnings($requesBpkArr, array('person_id' => $person_id));
						}
					}
				}
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHRequestBpk job stop');
	}

	/**
	 * Initialises requestEkz job, handles job queue, logs infos/errors
	 */
	public function requestEkz()
	{
		$jobType = 'DVUHRequestEkz';
		$this->logInfo('DVUHRequestEkz job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			$person_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($person_arr as $persobj)
			{
				if (!isset($persobj->person_id))
					$this->logError("Fehler bei Ekzabfrage, ungültige Parameter übergeben");
				else
				{
					$person_id = $persobj->person_id;

					$requestEkzResult = $this->dvuhekzmanagementlib->requestAndSaveEkz($person_id);

					if (isError($requestEkzResult))
					{
						$this->_logDVUHError(
							"Fehler bei Ekzvergabe, person Id $person_id",
							$requestEkzResult,
							$person_id
						);
					}
					elseif (hasData($requestEkzResult))
					{
						$requestEkzArr = getData($requestEkzResult);

						$this->_logDVUHInfosAndWarnings($requestEkzArr, array('person_id' => $person_id));
					}
				}
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHRequestEkz job stop');
	}

	/**
	 * Initialises sendPruefungsaktivitaeten job, handles job queue, logs infos/errors
	 */
	public function sendPruefungsaktivitaeten()
	{
		$jobType = 'DVUHSendPruefungsaktivitaeten';
		$this->logInfo('DVUHSendPruefungsaktivitaeten job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		else
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			$person_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($person_arr as $persobj)
			{
				if (!isset($persobj->person_id) || !isset($persobj->studiensemester_kurzbz))
					$this->logError("Fehler beim Senden von Prüfungsaktivitäten, ungültige Parameter übergeben");
				else
				{
					$person_id = $persobj->person_id;
					$studiensemester_kurzbz = $persobj->studiensemester_kurzbz;

					$sendPruefungsaktivitaetenResult = $this->dvuhpruefungsaktivitaetenmanagementlib->sendPruefungsaktivitaeten(
						$person_id,
						$studiensemester_kurzbz
					);

					if (isError($sendPruefungsaktivitaetenResult))
					{
						$this->_logDVUHError(
							"Fehler beim Senden von Prüfungsaktivitäten, person Id $person_id",
							$sendPruefungsaktivitaetenResult,
							$person_id
						);
					}
					elseif (hasData($sendPruefungsaktivitaetenResult))
					{
						$requestMatrnrArr = getData($sendPruefungsaktivitaetenResult);

						$this->_logDVUHInfosAndWarnings($requestMatrnrArr, array('person_id' => $person_id));
					}
				}
			}

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHSendPruefungsaktivitaeten job stop');
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Extracts input data from jobs.
	 * @param $jobs
	 * @return array with jobinput
	 */
	private function _getInputObjArray($jobs)
	{
		$mergedUsersArray = array();

		if (count($jobs) == 0) return $mergedUsersArray;

		foreach ($jobs as $job)
		{
			$decodedInput = json_decode($job->input);
			if ($decodedInput != null)
			{
				foreach ($decodedInput as $el)
				{
					$mergedUsersArray[] = $el;
				}
			}
		}
		return $mergedUsersArray;
	}

	/**
	 * Extracts infos and warnings from a result and logs them.
	 * @param array $resultarr
	 */
	private function _logDVUHInfosAndWarnings($resultarr, $idArr)
	{
		if ($this->_logInfos === true) // if info logging enabled
		{
			if (isset($resultarr['infos']) && is_array($resultarr['infos']))
			{
				foreach ($resultarr['infos'] as $info)
				{
					$infoTxt = $info;

					foreach ($idArr as $idname => $idvalue)
					{
						$infoTxt .= ", $idname: $idvalue";
					}

					$this->logInfo($infoTxt);
				}
			}
		}

		if (isset($resultarr['warnings']) && !isEmptyArray($resultarr['warnings']))
		{
			// get issue texts
			$warningTxt = implode('; ', $this->dvuhissuelib->getIssueTexts($resultarr['warnings']));

			// add warning texts for non-issue warnings
			foreach ($resultarr['warnings'] as $warning)
			{
				if (isError($warning))
				{
					$errText = getError($warning);
					if (is_string($errText)) $warningTxt .= (isEmptyString($warningTxt) ? '' : '; ').$errText;
				}
				elseif (is_string($warning))
					$warningTxt .= (isEmptyString($warningTxt) ? '' : '; ').$warning;
			}

			foreach ($idArr as $idname => $idvalue)
			{
				$warningTxt .= ", $idname: $idvalue";
			}

			$this->logWarning($warningTxt);

			// save DVUH issues in database
			foreach ($resultarr['warnings'] as $warning)
			{
				$person_id = isset($idArr['person_id']) ? $idArr['person_id'] : null;
				$prestudent_id = isset($idArr['prestudent_id']) ? $idArr['prestudent_id'] : null;
				$this->_addDVUHIssue($warning, $person_id, $prestudent_id, $force_predefined_for_external = true);
			}
		}
	}

	/**
	 * Logs info message if info logging is enabled in config.
	 * @param string $info
	 */
	private function _logDVUHInfoIfEnabled($info)
	{
		if ($this->_logInfos === true)
			$this->logInfo($info);
	}

	/**
	 * Logs DVUH error, writes webservice log and issue (if necessary).
	 * @param string $logging_prefix for log
	 * @param object $errorObj containing log info
	 * @param int $person_id for issue
	 * @param int $prestudent_id for issue oe_kurzbz
	 */
	private function _logDVUHError($logging_prefix, $errorObj, $person_id = null, $prestudent_id = null)
	{
		if (!isError($errorObj))
			return;

		// get all errors from object
		$errorArr = getError($errorObj);

		// if error is text, wrap it into array
		if (!is_array($errorArr))
			$errorArr = array($errorArr);

		// for each error
		foreach ($errorArr as $err)
		{
			$errorText = $logging_prefix.': '.implode('; ', $this->dvuhissuelib->getIssueTexts($err));
			// if error is an issue...
			if (isset($err->fehlernummer) || isset($err->issue_fehler_kurzbz))
			{
				// ...log as warning and add issue
				$this->logWarning($errorText);
				$this->_addDVUHIssue(getError($errorObj), $person_id, $prestudent_id);
			}
			else
			{
				// ...otherwise just log the error in webservice log
				$this->logError($errorText);
			}
		}
	}

	/**
	 * Adds DVUH issue. Logs error if issue adding failed.
	 * @param $issue
	 * @param int $person_id
	 * @param int $prestudent_id
	 * @param string $force_predefined_for_external
	 */
	private function _addDVUHIssue($issue, $person_id = null, $prestudent_id = null, $force_predefined_for_external = false)
	{
		$issueRes = $this->dvuhissuelib->addIssue($issue, $person_id, $prestudent_id, $force_predefined_for_external);

		if (isError($issueRes))
		{
			$postfix = '';
			$errors = getError($issueRes);

			if (!isEmptyArray($errors))
				$postfix = implode(', ', $errors);

			$this->logError('Error when adding issue(s)'.$postfix);
		}
	}
}
