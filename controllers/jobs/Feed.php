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
		$this->load->library('extensions/FHC-Core-DVUH/FeedReaderLib');
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
		$markread = 'false';
		$erstelltSeit='2020-05-01';

		$queryResult = $this->FeedModel->get($be, $content, $erstelltSeit, $markread);

		if (hasData($queryResult))
		{
			$feeds = $this->feedreaderlib->parseFeeds(getData($queryResult));

			if (isError($feeds))
				$this->logError(getError($feeds));
			elseif (hasData($feeds))
			{
				$feeddata = getData($feeds);
				print_r($feeddata);
			}
			else
				$this->logInfo('No new feeds available');
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Feed GET job stop');
	}
}
