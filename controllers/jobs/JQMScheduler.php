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
	 * Creates jobs queue entries for requestMatrikelnummer job.
	 * @param string $studiensemester_kurzbz semester for which Matrikelnr should be requested and Stammdaten should be sent
	 */
	public function requestMatrikelnummer($studiensemester_kurzbz)
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->requestMatrikelnummer');

		// If an error occured then log it
		$jobInputResult = $this->jqmschedulerlib->requestMatrikelnummer($studiensemester_kurzbz);

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

	/**
	 * Creates jobs queue entries for sendCharge job.
	 * @param string $studiensemester_kurzbz semester for which stammdaten should be sent
	 */
	public function sendCharge($studiensemester_kurzbz)
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->sendCharge');

		$jobInputResult = $this->jqmschedulerlib->sendCharge($studiensemester_kurzbz);

		// If an error occured then log it
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

	/**
	 * Creates jobs queue entries for sendPayment job.
	 * @param string $studiensemester_kurzbz semester for which payment data should be sent
	 */
	public function sendPayment($studiensemester_kurzbz)
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->sendPayment');

		$jobInputResult = $this->jqmschedulerlib->sendPayment($studiensemester_kurzbz);

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// Add the new job to the jobs queue
			$addNewJobResult = $this->addNewJobsToQueue(
				JQMSchedulerLib::JOB_TYPE_SEND_PAYMENT, // job type
				$this->generateJobs( // gnerate the structure of the new job
					JobsQueueLib::STATUS_NEW,
					getData($jobInputResult)
				)
			);

			// If error occurred return it
			if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
		}

		$this->logInfo('End job queue scheduler FHC-Core-DVUH->sendPayment');
	}

	/**
	 * Creates jobs queue entries for sendStudy job.
	 * @param string $studiensemester_kurzbz semester for which studydata should be sent
	 */
	public function sendStudyData($studiensemester_kurzbz)
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->sendStudyData');

		$jobInputResult = $this->jqmschedulerlib->sendStudyData($studiensemester_kurzbz);

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// Add the new job to the jobs queue
			$addNewJobResult = $this->addNewJobsToQueue(
				JQMSchedulerLib::JOB_TYPE_SEND_STUDY_DATA, // job type
				$this->generateJobs( // gnerate the structure of the new job
					JobsQueueLib::STATUS_NEW,
					getData($jobInputResult)
				)
			);

			// If error occurred return it
			if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
		}

		$this->logInfo('End job queue scheduler FHC-Core-DVUH->sendStudyData');
	}

	/**
	 * Creates jobs queue entries for requestBpk job.
	 * @param string $studiensemester_kurzbz Bpk will be requested for students in this semester
	 */
	public function requestBpk($studiensemester_kurzbz)
	{
		$this->logInfo('Start job queue scheduler FHC-Core-DVUH->requestBpk');

		// If an error occured then log it
		$jobInputResult = $this->jqmschedulerlib->requestBpk($studiensemester_kurzbz);

		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// Add the new job to the jobs queue
			$addNewJobResult = $this->addNewJobsToQueue(
				JQMSchedulerLib::JOB_TYPE_REQUEST_BPK, // job type
				$this->generateJobs( // gnerate the structure of the new job
					JobsQueueLib::STATUS_NEW,
					getData($jobInputResult)
				)
			);

			// If error occurred return it
			if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
		}

		$this->logInfo('End job queue scheduler FHC-Core-DVUH->requestBpk');
	}
}
