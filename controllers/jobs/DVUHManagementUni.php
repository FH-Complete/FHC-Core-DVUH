<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'/controllers/extensions/FHC-Core-DVUH/jobs/DVUHManagement.php';

/**
 * Controller for initialising all DVUH jobs
 */
class DVUHManagementUni extends DVUHManagement
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagementuni/DVUHUniMatrikelnummerManagementLib');
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

					$requestMatrnrResult = $this->dvuhunimatrikelnummermanagementlib->requestMatrikelnummer($person_id, $studiensemester_kurzbz);

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
}
