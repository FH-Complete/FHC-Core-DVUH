<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Adds jobs to queue.
 */
class UHSTATScheduler extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads UHSTATSchedulerLib
		$this->load->library('extensions/FHC-Core-DVUH/uhstat/UHSTATSchedulerLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Creates jobs queue entries for sendUHSTAT1 job.
	 */
	public function sendUHSTAT1()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->sendUHSTAT1');

		$jobInputResult = $this->uhstatschedulerlib->sendUHSTAT1();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Add the new job to the jobs queue
				$addNewJobResult = $this->addNewJobsToQueue(
					UHSTATSchedulerLib::JOB_TYPE_UHSTAT1, // job type
					$this->generateJobs( // gnerate the structure of the new job
						JobsQueueLib::STATUS_NEW,
						getData($jobInputResult)
					)
				);

				// If error occurred return it
				if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-DVUH->sendUHSTAT1');
	}

	/**
	 * Creates jobs queue entries for sendUHSTAT2 job.
	 */
	public function sendUHSTAT2($studiensemester_kurzbz = null)
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->sendUHSTAT2');

		$jobInputResult = $this->uhstatschedulerlib->sendUHSTAT2($studiensemester_kurzbz);

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Add the new job to the jobs queue
				$addNewJobResult = $this->addNewJobsToQueue(
					UHSTATSchedulerLib::JOB_TYPE_UHSTAT2, // job type
					$this->generateJobs( // gnerate the structure of the new job
						JobsQueueLib::STATUS_NEW,
						getData($jobInputResult)
					)
				);

				// If error occurred return it
				if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-DVUH->sendUHSTAT2');
	}
}

