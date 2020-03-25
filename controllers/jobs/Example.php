<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Example extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->model('extensions/FHC-Core-DVUH/Fullstudent_model', 'FullstudentModel');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
	 */
	public function test()
	{
		$this->logInfo('Example job start');

		$matrikelnummer = '12345';
		$be = null;
		$semester = null;

		$queryResult = $this->FullstudentModel->get($matrikelnummer, $be, $semester);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Example job stop');
	}
}
