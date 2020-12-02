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

			$person_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($person_arr as $persobj)
			{
				$person_id = $persobj->person_id;
				$studiensemester_kurzbz = $persobj->studiensemester_kurzbz;

				$requestMatrnrResult = $this->dvuhmanagementlib->requestMatrikelnummer($person_id, $studiensemester_kurzbz);

				if (isError($requestMatrnrResult))
					$this->logError("An error occurred while requesting Matrikelnummer, person Id $person_id", getError($requestMatrnrResult));
				elseif (hasData($requestMatrnrResult))
				{
					$requestMatrnrArr = getData($requestMatrnrResult);

					if (isset($requestMatrnrArr['matr_nr']))
					{
						$this->logInfo('Stammdaten with Matrikelnr ' . $requestMatrnrArr['matr_nr'] . ' successfully sent for person Id ' . $person_id);

						if ($requestMatrnrArr['matr_aktiv'] == true)
							$this->logInfo('Existing Matrikelnr ' . $requestMatrnrArr['matr_nr'] . ' assigned to person Id ' . $person_id);
						elseif ($requestMatrnrArr['matr_aktiv'] == false)
							$this->logInfo('New Matrikelnr ' . $requestMatrnrArr['matr_nr'] . ' preliminary assigned to person Id ' . $person_id);

/*						$sendMasterdataResult = $this->dvuhmanagementlib->sendMasterdata($person_id, $studiensemester_kurzbz);

						if (isError($sendMasterdataResult))
							$this->logError("An error occurred while sending Stammdaten, person Id $person_id", getError($sendMasterdataResult));
						elseif (hasData($sendMasterdataResult))
						{
							$this->logInfo("Stammdaten of student with id $person_id successfully sent");
						}*/
					}
					elseif (isset($requestMatrnrArr['info']))
					{
						$this->logInfo($requestMatrnrArr['info'] . " (personId $person_id)");
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

			$person_arr = $this->_getInputObjArray(getData($lastJobs));

			foreach ($person_arr as $persobj)
			{
				$person_id = $persobj->person_id;
				$studiensemester = $persobj->studiensemester_kurzbz;

				$sendChargeResult = $this->dvuhmanagementlib->sendCharge($person_id, $studiensemester);

				if (isError($sendChargeResult))
					$this->logError("An error occurred while sending charge, person Id $person_id, studiensemester $studiensemester", getError($sendChargeResult));
				elseif (hasData($sendChargeResult))
				{
					$sendCharge = getData($sendChargeResult);

					if (isset($sendCharge['info']))
						$this->logInfo($sendCharge['info'] . ", person_id $person_id, studiensemester $studiensemester");
					else
						$this->logInfo("Stammdaten with charge of student with person Id $person_id, studiensemester $studiensemester successfully sent");
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

				$sendPaymentResult = $this->dvuhmanagementlib->sendPayment($person_id, $studiensemester);

				if (isError($sendPaymentResult))
					$this->logError("An error occurred while sending charge, person Id $person_id, studiensemester $studiensemester", getError($sendPaymentResult));
				elseif (hasData($sendPaymentResult))
				{
					$sendPaymentItems = getData($sendPaymentResult);

					if (isset($sendPaymentItems['info']))
						$this->logInfo($sendPaymentItems['info'] . ", person Id $person_id, studiensemester $studiensemester");
					elseif (is_array($sendPaymentItems))
					{
						foreach ($sendPaymentItems as $paymentRes)
						{
							if (isError($paymentRes))
								$this->logError("An error occurred while sending payment, person Id $person_id, studiensemester $studiensemester", getError($paymentRes));
							elseif (hasData($paymentRes))
								$this->logInfo("Payment of student with person Id $person_id, studiensemester $studiensemester successfully sent");
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

				$sendStudyDataResult = $this->dvuhmanagementlib->sendStudyData($prestudent_id, $studiensemester);

				if (isError($sendStudyDataResult))
					$this->logError("An error occurred while sending study data, prestudent Id $prestudent_id, studiensemester $studiensemester", getError($sendStudyDataResult));
				elseif (hasData($sendStudyDataResult))
				{
					$sendStudyData = getData($sendStudyDataResult);

					if (isset($sendStudyData['info']))
						$this->logInfo($sendStudyData['info'] . ", prestudent_id $prestudent_id, studiensemester $studiensemester");
					else
					{
						$this->logInfo("Study data for student with prestudent Id $prestudent_id, studiensemester $studiensemester successfully sent");
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
}
