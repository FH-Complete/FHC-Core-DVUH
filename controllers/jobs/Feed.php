<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Feed JOB
 */
class Feed extends JOB_Controller
{
	private $_feedtypes = array('at.gv.brz.rg.stubei.rws.schema.kontostandantwort');

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
		$erstelltSeit = '2021-03-30';

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

	/**
	 * Gets feeds, parses them and saved them in FHC database.
	 * @param string $erstelltSeit date after which feeds were generated
	 */
	public function getAndSave($erstelltSeit = null)
	{
		$this->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');
		$this->load->model('extensions/FHC-Core-DVUH/synctables/DVUHFeedeintrag_model', 'DVUHFeedeintragModel');

		$this->logInfo('Feed getAndSave job start');

		$be = $this->config->item('fhc_dvuh_be_code');
		$content = 'true';
		$markread = 'false';


		$queryResult = $this->FeedModel->get($be, $content, $erstelltSeit, $markread);

		if (hasData($queryResult))
		{
			$feeds = $this->feedreaderlib->parseFeeds(getData($queryResult), $this->_feedtypes);

			if (isError($feeds))
				$this->logError(getError($feeds));
			elseif (hasData($feeds))
			{
				$feeddata = getData($feeds);

				foreach ($feeddata as $feed)
				{
					$saveRes = $this->DVUHFeedeintragModel->saveFeedeintrag($feed);

					if (isError($saveRes))
						$this->logError(getError($saveRes));
				}
			}
			else
				$this->logInfo('No new feeds available');
		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Feed getAndSave stop');
	}
}
