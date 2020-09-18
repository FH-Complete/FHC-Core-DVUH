<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Zahlung extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->model('extensions/FHC-Core-DVUH/Zahlung_model', 'ZahlungModel');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
	 */
	public function post()
	{
		$this->logInfo('Zahlung job start');

		$matrikelnummer='52012345';
		$be = 'FT';
		$semester = '2020S';
		$zahlungsart = '1';
		$centbetrag = '36336';
		$buchungsdatum = '2020-05-13';
		$referenznummer = '52012345-1234';

		$queryResult = $this->ZahlungModel->post(
			$matrikelnummer,
			$be,
			$semester,
			$zahlungsart,
			$centbetrag,
			$buchungsdatum,
			$referenznummer
		);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Zahlung job stop');
	}
}
