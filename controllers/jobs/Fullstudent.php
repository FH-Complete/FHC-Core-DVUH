<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Fullstudent extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
	 */
	public function get()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Fullstudent_model', 'FullstudentModel');

		$this->logInfo('Fullstudent job start');

		$matrikelnummer = '52012345';
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

		$this->logInfo('Fullstudent job stop');
	}
}
