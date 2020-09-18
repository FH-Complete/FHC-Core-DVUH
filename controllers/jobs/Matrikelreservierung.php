<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stammdaten JOB
 */
class Matrikelreservierung extends JOB_Controller
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
	 * Get Reservations
	 * @param $sj Studienjahr
	 */
	public function get($sj)
	{
		if(!isset($sj))
		{
			show_error('Parameter SJ is missing');
		}

		$this->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');

		$this->logInfo('MatrikelreservierungModel GET job start');

		$be = $this->config->item('fhc_dvuh_be_code');

		$queryResult = $this->MatrikelreservierungModel->get($be, $sj);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('MatrikelreservierungModel GET job stop');
	}

	public function post()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');

		$this->logInfo('Matrikelreservierung POST job start');
		$be = $this->config->item('fhc_dvuh_be_code');
		$sj = '2019';
		$anzahl = 1;

		$queryResult = $this->MatrikelreservierungModel->post($be, $sj, $anzahl);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Matrikelreservierung POST job stop');
	}
}
