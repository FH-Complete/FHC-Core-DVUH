<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stammdaten JOB
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
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Get Errormessages
	 */
	public function get()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Fehler_model', 'FehlerModel');

		$this->logInfo('Fehler GET job start');


		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = '2019W';
		$matrikelnummer = null;

		$queryResult = $this->FehlerModel->get($be, $semester, $matrikelnummer);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Fehler GET job stop');
	}
}
