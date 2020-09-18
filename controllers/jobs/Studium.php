<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Studium extends JOB_Controller
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
	 * Get Information about a Student
	 */
	public function get()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Studium_model', 'StudiumModel');

		$this->logInfo('Studium GET job start');

		$matrikelnummer = '52012345';


		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = '2019W';
		$studienkennung = null;

		$queryResult = $this->StudiumModel->get($be, $matrikelnummer, $semester, $studienkennung);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Studium GET job stop');
	}

	public function post()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Studium_model', 'StudiumModel');

		$this->logInfo('Studium POST job start');


		$queryResult = $this->StudiumModel->post();

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Studium POST job stop');
	}
}
