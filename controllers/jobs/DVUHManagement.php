<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class DVUHManagement extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->library('extensions/FHC-Core-DVUH/DVUHManagementLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
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

			$person_arr = $this->_getPersonIdArray(getData($lastJobs));

			foreach ($person_arr as $person_id)
			{
				$requestMatrnrResult = $this->dvuhmanagementlib->requestMatrikelnummer($person_id);

				if (isError($requestMatrnrResult))
					$this->logError("An error occurred while requesting Matrikelnummer, person Id $person_id", getError($requestMatrnrResult));
				elseif (hasData($requestMatrnrResult))
				{
					$requestMatrnrObj = getData($requestMatrnrResult);

					if (isset($requestMatrnrObj->matr_nr))
					{
						if ($requestMatrnrObj->matr_aktiv == true)
							$this->logInfo('Existing Matrikelnr ' . $requestMatrnrObj->matr_nr . ' assigned to person Id ' . $person_id);
						elseif ($requestMatrnrObj->matr_aktiv == false)
							$this->logInfo('New Matrikelnr ' . $requestMatrnrObj->matr_nr . ' preliminary assigned to person Id ' . $person_id);

						$sendStammdatenResult = $this->dvuhmanagementlib->sendStammdaten($person_id);

						if (isError($sendStammdatenResult))
							$this->logError("An error occurred while sending Stammdaten, person Id $person_id", getError($sendStammdatenResult));
						elseif (hasData($sendStammdatenResult))
						{
							$this->logInfo("Stammdaten of student with id $person_id successfully sent");
						}
					}
					elseif (is_string($requestMatrnrObj))
					{
						$this->logInfo($requestMatrnrObj . " (personId $person_id)");
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

			$person_arr = $this->_getPersonIdArray(getData($lastJobs));

			foreach ($person_arr as $person_id)
			{
				$sendChargeResult = $this->dvuhmanagementlib->sendCharge($person_id);

				if (isError($sendChargeResult))
					$this->logError("An error occurred while sending charge, person Id $person_id", getError($sendChargeResult));
				elseif (hasData($sendChargeResult))
				{
					$sendChargeItems = getData($sendChargeResult);

					if (is_string($sendChargeItems))
						$this->logInfo($sendChargeItems . ", person_id $person_id");
					elseif (is_array($sendChargeItems))
					{
						foreach ($sendChargeItems as $chargeRes)
						{
							if (isError($chargeRes))
								$this->logError("An error occurred while sending charge, person Id $person_id", getError($chargeRes));
							elseif (hasData($chargeRes))
								$this->logInfo("Stammdaten with charge of student with person Id $person_id, studiensemester " . getData($chargeRes)['studiensemester_kurzbz'] . " successfully sent");
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

		$this->logInfo('DVUHSendCharge job stop');
	}

	private function _getPersonIdArray($jobs)
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
					$mergedUsersArray[] = $el->person_id;
				}
			}
		}
		return $mergedUsersArray;
	}
}
