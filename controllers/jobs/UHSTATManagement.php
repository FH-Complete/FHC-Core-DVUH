<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Controller for initialising all UHSTAT jobs
 */
class UHSTATManagement extends JQW_Controller
{
	private $_logInfos; // stores config param for info display

	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->load->library('extensions/FHC-Core-DVUH/DVUHIssueLib');
		$this->load->library('extensions/FHC-Core-DVUH/uhstat/UHSTATManagementLib');

		// load configs and save "log infos" parameter
		$this->config->load('extensions/FHC-Core-DVUH/UHSTATSync');
		$this->_logInfos = $this->config->item('fhc_uhstat_log_infos');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Initialises sendUHSTAT1 job, handles job queue, logs infos/errors
	 */
	public function sendUHSTAT1()
	{
		$jobType = 'DVUHUHSTAT1';
		$this->logInfo('DVUH UHSTAT1 job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		elseif (hasData($lastJobs))
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			// get students from queue
			$person_id_arr = $this->_mergeArrayByParameter(getData($lastJobs), 'person_id');

			// send UHSTAT1 data for the student
			$result = $this->uhstatmanagementlib->sendUHSTAT1($person_id_arr);

			// log errors if occured
			if ($this->uhstatmanagementlib->hasError())
			{
				$errors = $this->uhstatmanagementlib->readErrors();

				foreach ($errors as $error)
				{
					// write error log
					$this->logError(
						"Fehler beim Senden der UHSTAT1 Daten: ".getError($error->error)
					);
				}
			}

			// log warnings if occured
			if ($this->uhstatmanagementlib->hasWarning())
			{
				$warnings = $this->uhstatmanagementlib->readWarnings();

				foreach ($warnings as $warning)
				{
					// write warning log
					$this->logWarning(
						"Fehler beim Senden der UHSTAT1 Daten: ".getError($warning->error)
					);
				}
			}

			// write info log
			if ($this->uhstatmanagementlib->hasInfo())
			{
				$infos = $this->uhstatmanagementlib->readInfos();

				foreach ($infos as $info)
				{
					if (!isEmptyString($info)) $this->_logInfoIfEnabled($info);
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

		$this->logInfo('DVUHUHSTAT1 job stop');
	}

	/**
	 * Initialises sendUHSTAT2 job, handles job queue, logs infos/errors
	 */
	public function sendUHSTAT2()
	{
		$jobType = 'DVUHUHSTAT2';
		$this->logInfo('DVUH UHSTAT2 job start');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		elseif (hasData($lastJobs))
		{
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);

			// get students from queue
			$prestudent_id_arr = $this->_mergeArrayByParameter(getData($lastJobs), 'prestudent_id');

			// send UHSTAT1 data for the student
			$result = $this->uhstatmanagementlib->sendUHSTAT2($prestudent_id_arr);

			// log errors if occured
			if ($this->uhstatmanagementlib->hasError())
			{
				$errors = $this->uhstatmanagementlib->readErrors();

				foreach ($errors as $error)
				{
					// write error log
					$this->logError(
						"Fehler beim Senden der UHSTAT2 Daten: ".getError($error->error)
					);
				}
			}

			// log warnings if occured
			if ($this->uhstatmanagementlib->hasWarning())
			{
				$warnings = $this->uhstatmanagementlib->readWarnings();

				foreach ($warnings as $warning)
				{
					// write warning log
					$this->logWarning(
						"Fehler beim Senden der UHSTAT2 Daten: ".getError($warning->error)
					);
				}
			}

			// write info log
			if ($this->uhstatmanagementlib->hasInfo())
			{
				$infos = $this->uhstatmanagementlib->readInfos();

				foreach ($infos as $info)
				{
					if (!isEmptyString($info)) $this->_logInfoIfEnabled($info);
				}
			}
			die();

			// Update jobs properties values
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
		}

		$this->logInfo('DVUHUHSTAT2 job stop');
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Extract person Ids from jobs
	 * @param $jobs array with jobs
	 * @return array with person Ids
	 */
	private function _mergePersonIdArray($jobs, $jobsAmount = 99999)
	{
		$jobsCounter = 0;
		$mergedUsersArray = array();

		// If no jobs then return an empty array
		if (count($jobs) == 0) return $mergedUsersArray;

		// For each job
		foreach ($jobs as $job)
		{
			// Decode the json input
			$decodedInput = json_decode($job->input);

			// If decoding was fine
			if ($decodedInput != null)
			{
				// For each element in the array
				foreach ($decodedInput as $el)
				{
					// extract the Person Id
					if (isset($el->person_id)) $mergedUsersArray[] = $el->person_id;
				}
			}

			$jobsCounter++; // jobs counter

			if ($jobsCounter >= $jobsAmount) break; // if the required amount is reached then exit
		}

		return $mergedUsersArray;
	}

	/**
	 * Extract parameters from jobs.
	 * @param $jobs array with jobs
	 * @param $parameterName name of parameter to extract (e.g. prestudent Id)
	 * @return array with extracted elements
	 */
	private function _mergeArrayByParameter($jobs, $parameterName, $jobsAmount = 99999)
	{
		$jobsCounter = 0;
		$mergedUsersArray = array();

		// If no jobs then return an empty array
		if (count($jobs) == 0) return $mergedUsersArray;

		// For each job
		foreach ($jobs as $job)
		{
			// Decode the json input
			$decodedInput = json_decode($job->input);

			// If decoding was fine
			if ($decodedInput != null)
			{
				// For each element in the array
				foreach ($decodedInput as $el)
				{
					// extract the Person Id
					if (isset($el->{$parameterName})) $mergedUsersArray[] = $el->{$parameterName};
				}
			}

			$jobsCounter++; // jobs counter

			if ($jobsCounter >= $jobsAmount) break; // if the required amount is reached then exit
		}

		return $mergedUsersArray;
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
