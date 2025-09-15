<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * JOB for writing issues from DVUH errors.
 */
class Fehler extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$this->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$this->config->load('extensions/FHC-Core-DVUH/DVUHFehlerJob');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Get errors persisting in DVUH.
	 */
	public function get($studiensemester_kurzbz = null)
	{
		// loading libs and models
		$this->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
		$this->load->library('extensions/FHC-Core-DVUH/DVUHIssueLib');
		$this->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->load->model('person/person_model', 'PersonModel');
		$this->load->model('extensions/FHC-Core-DVUH/AufgetreteneFehler_model', 'AufgetreteneFehlerModel');
		$this->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		$this->logInfo('Fehler GET job start');

		// get configs
		$be = $this->config->item('fhc_dvuh_be_code');
		$logInfos = $this->config->item('fhc_dvuh_log_infos');
		$writeableErrorCategories = $this->config->item('fhc_dvuh_fehler_job_categories') ?? [];
		$excludedErrors = $this->config->item('fhc_dvuh_fehler_job_exclusions') ?? [];

		$semester = getStudiensemesterForSync($this->config->item('fhc_dvuh_studiensemester_meldezeitraum'), $studiensemester_kurzbz);

		foreach ($semester as $idx => $sem)
		{
			// convert studiensemester to dvuh format
			$dvuhSemester = $this->dvuhconversionlib->convertSemesterToDVUH($sem);

			$queryResult = $this->AufgetreteneFehlerModel->get($be, $dvuhSemester);

			if (hasData($queryResult))
			{
				// parse error
				$fehlerData = $this->xmlreaderlib->parseXml(getData($queryResult), 'fehler');

				if (hasData($fehlerData))
				{
					$fehler = getData($fehlerData)->fehler;

					foreach ($fehler as $fehlerObj)
					{
						// abort if error not well-formed
						if (
							!isset($fehlerObj->fehlerquelle->studierendenkey->matrikelnummer)
							|| !isset($fehlerObj->fehlernummer)
							|| !isset($fehlerObj->fehlertext)
						) continue;

						$dvuhMatrNr = $fehlerObj->fehlerquelle->studierendenkey->matrikelnummer;

						$personRes = $this->PersonModel->loadWhere(array('matr_nr' => $dvuhMatrNr));

						// abort if error is should not be written
						if (
							!hasData($personRes)
							|| in_array($fehlerObj->fehlernummer, $excludedErrors)
							|| !in_array(mb_substr($fehlerObj->fehlernummer, 0, 1), $writeableErrorCategories)
						) continue;

						$person_id = getData($personRes)[0]->person_id;

						// write issue from error info
						$result = $this->dvuhissuelib->addIssue(createExternalIssueObj($fehlerObj->fehlertext, $fehlerObj->fehlernummer), $person_id);

						if (isError($result)) $this->logError(getError($result));

						if ($logInfos === true)
						{
							$this->logInfo(
								"Issue ".$fehlerObj->fehlernummer." for Matrikelnr $dvuhMatrNr successfully managed"
							);
						}
					}
				}
			}
			else
			{
				if ($logInfos === true) $this->logInfo("No elements were found for $sem");
			}
		}

		$this->logInfo('Fehler GET job stop');
	}
}
