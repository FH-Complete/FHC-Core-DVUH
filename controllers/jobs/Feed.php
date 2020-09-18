<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Feed JOB
 */
class Feed extends JOB_Controller
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
	 * Get Feed Messages
	 */
	public function get()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');

		$this->logInfo('Feed GET job start');


		$be = $this->config->item('fhc_dvuh_be_code');
		$content = 'true';
		$erstelltSeit='2020-05-01';
		$markread='false';

		$queryResult = $this->FeedModel->get($be, $content, $erstelltSeit, $markread);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Feed GET job stop');
	}
}
