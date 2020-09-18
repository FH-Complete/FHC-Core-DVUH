<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stammdaten JOB
 */
class Stammdaten extends JOB_Controller
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
		$this->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');

		$this->logInfo('Stammdaten GET job start');

		$matrikelnummer = '52012345';

		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = '2020S';

		$queryResult = $this->StammdatenModel->get($be, $matrikelnummer, $semester);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Stammdaten GET job stop');
	}

	public function post()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');

		$this->logInfo('Stammdaten POST job start');

		$queryResult = $this->StammdatenModel->post();

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Stammdaten POST job stop');
	}
}
