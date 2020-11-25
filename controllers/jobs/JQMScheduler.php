<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class JQMScheduler extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads JQMSchedulerLib
		$this->load->library('extensions/FHC-Core-DVUH/JQMSchedulerLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 *
	 */
	public function requestMatrikelnummer()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->requestMatrikelnummer');

		// If an error occured then log it
		$jobInputResult = $this->jqmschedulerlib->requestMatrikelnummer();

		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// Add the new job to the jobs queue
			$addNewJobResult = $this->addNewJobsToQueue(
				JQMSchedulerLib::JOB_TYPE_REQUEST_MATRIKELNUMMER, // job type
				$this->generateJobs( // gnerate the structure of the new job
					JobsQueueLib::STATUS_NEW,
					getData($jobInputResult)
				)
			);

			// If error occurred return it
			if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
		}

		$this->logInfo('End job queue scheduler FHC-Core-DVUH->requestMatrikelnummer');
	}

	public function sendCharge()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->sendCharge');

		// If an error occured then log it
		$jobInputResult = $this->jqmschedulerlib->sendCharge();

		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// Add the new job to the jobs queue
			$addNewJobResult = $this->addNewJobsToQueue(
				JQMSchedulerLib::JOB_TYPE_SEND_CHARGE, // job type
				$this->generateJobs( // gnerate the structure of the new job
					JobsQueueLib::STATUS_NEW,
					getData($jobInputResult)
				)
			);

			// If error occurred return it
			if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
		}

		$this->logInfo('End job queue scheduler FHC-Core-DVUH->sendCharge');
	}
}
