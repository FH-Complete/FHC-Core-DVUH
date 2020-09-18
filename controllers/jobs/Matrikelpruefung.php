<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Matrikelpruefung extends JOB_Controller
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
		$this->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');

		$this->logInfo('Matrikelpruefung job start');

		$queryResult = $this->MatrikelpruefungModel->get(
			$bpk = null,
			$ekz = null,
			$geburtsdatum = '1984-04-26',
			$matrikelnummer = null,
			$nachname = 'Test',
			$svnr = null,
			$vorname = 'Karl'
		);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Matrikelpruefung job stop');
	}
}
