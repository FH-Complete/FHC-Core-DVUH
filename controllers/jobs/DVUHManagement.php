<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Controller for initialising all DVUH jobs
 */
class DVUHManagement extends JQW_Controller
{
	private $_logInfos;

	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->library('extensions/FHC-Core-DVUH/DVUHManagementLib');

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
					$this->logError("An error occurred while requesting Matrikelnummer, invalid parameters passed to queue");
				else
				{
					$person_id = $persobj->person_id;
					$studiensemester_kurzbz = $persobj->studiensemester_kurzbz;

					$requestMatrnrResult = $this->dvuhmanagementlib->requestMatrikelnummer($person_id, $studiensemester_kurzbz);

					if (isError($requestMatrnrResult))
						$this->logError("An error occurred while requesting Matrikelnummer, person Id $person_id", getError($requestMatrnrResult));
					elseif (hasData($requestMatrnrResult))
					{
						$requestMatrnrArr = getData($requestMatrnrResult);

						$this->_logInfosAndWarnings($requestMatrnrArr, array('person_id' => $person_id));
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
					$this->logError("An error occurred while sending charge, invalid parameters passed to queue");
				else
				{
					$person_id = $persobj->person_id;
					$studiensemester = $persobj->studiensemester_kurzbz;

					$sendChargeResult = $this->dvuhmanagementlib->sendMasterData($person_id, $studiensemester);

					if (isError($sendChargeResult))
						$this->logError("An error occurred while sending charge, person Id $person_id, studiensemester $studiensemester", getError($sendChargeResult));
					elseif (hasData($sendChargeResult))
					{
						$sendCharge = getData($sendChargeResult);

						$this->_logInfosAndWarnings($sendCharge, array('person_id' => $person_id));

						if (isset($sendCharge['result']))
							$this->_logInfoIfEnabled("Stammdaten with charge of student with person Id $person_id, studiensemester $studiensemester successfully sent");
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
					$this->logError("An error occurred while sending payment, invalid parameters passed to queue");
				else
				{
					$sendPaymentResult = $this->dvuhmanagementlib->sendPayment($person_id, $studiensemester);

					if (isError($sendPaymentResult))
						$this->logError("An error occurred while sending payment, person Id $person_id, studiensemester $studiensemester", getError($sendPaymentResult));
					elseif (hasData($sendPaymentResult))
					{
						$sendPaymentItems = getData($sendPaymentResult);


						$this->_logInfosAndWarnings($sendPaymentItems, array('person_id' => $person_id));

						if (isset($sendPaymentItems['result']) && is_array($sendPaymentItems['result']))
						{
							foreach ($sendPaymentItems['result'] as $paymentRes)
							{
								if (isError($paymentRes))
									$this->logError("An error occurred while sending payment, person Id $person_id, studiensemester $studiensemester", getError($paymentRes));
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
					$this->logError("An error occurred while sending study data, invalid parameters passed to queue");
				else
				{
					$sendStudyDataResult = $this->dvuhmanagementlib->sendStudyData($studiensemester, null, $prestudent_id);

					if (isError($sendStudyDataResult))
						$this->logError("An error occurred while sending study data, prestudent Id $prestudent_id, studiensemester $studiensemester", getError($sendStudyDataResult));
					elseif (hasData($sendStudyDataResult))
					{
						$sendStudyData = getData($sendStudyDataResult);

						$this->_logInfosAndWarnings($sendStudyData, array('prestudent_id' => $prestudent_id));

						if (isset($sendStudyData['result']))
						{
							$this->_logInfoIfEnabled("Study data for student with prestudent Id $prestudent_id, studiensemester $studiensemester successfully sent");
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
	private function _logInfosAndWarnings($resultarr, $idArr)
	{
		if ($this->_logInfos === true)
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

		if (isset($resultarr['warnings']) && is_array($resultarr['warnings']))
		{
			foreach ($resultarr['warnings'] as $warning)
			{
				$warningTxt = $warning;

				foreach ($idArr as $idname => $idvalue)
				{
					$warningTxt .= ", $idname: $idvalue";
				}

				$this->logWarning($warningTxt);
			}
		}
	}

	/**
	 * Logs info message if info logging is enabled in config.
	 * @param string $info
	 */
	private function _logInfoIfEnabled($info)
	{
		if ($this->_logInfos === true)
			$this->logInfo($info);
	}
}
