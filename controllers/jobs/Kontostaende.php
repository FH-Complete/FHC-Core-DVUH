<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stammdaten JOB
 */
class Kontostaende extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->config->load('extensions/FHC-Core-DVUH/DVUHClient');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Get Errormessages
	 */
	public function get()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Kontostaende_model', 'KontostaendeModel');

		$this->logInfo('Kontostaende GET job start');

		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = '2020W';
		$matrikelnummer = '01206888';
		$seit = null;

		$queryResult = $this->KontostaendeModel->get($be, $semester, $matrikelnummer, $seit);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Kontostaende GET job stop');
	}
}
