<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Get feeds for overview
 */
class FeedOverview extends Auth_Controller
{
	const DEFAULT_FEED_PAST_DAYS = 30;

	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'admin:rw',
				'getFeedEntries' => 'admin:rw'
			)
		);

		$this->config->load('extensions/FHC-Core-DVUH/DVUHClient');

		$this->load->library('extensions/FHC-Core-DVUH/FeedReaderLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Loading feed overview.
	 */
	public function index()
	{
		$this->load->library('WidgetLib');
		$this->load->view(
			'extensions/FHC-Core-DVUH/feeds'
		);
	}

	/**
	 * Get Feed Messages
	 */
	public function getFeedEntries()
	{
		$result = null;

		$this->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');

		$be = $this->config->item('fhc_dvuh_be_code');
		$content = 'true';
		$markread = 'false';
		$erstelltSeit = $this->input->get('erstelltSeit');
		$matrikelnummer = $this->input->get('matrikelnummer');

		if (isEmptyString($erstelltSeit))
			$erstelltSeit = date("Y-m-d", strtotime("-" . self::DEFAULT_FEED_PAST_DAYS . " days"));

		$queryResult = $this->FeedModel->get($be, $content, $erstelltSeit, $markread);

		if (isError($queryResult))
			$result = $queryResult;
		elseif (hasData($queryResult))
		{
			$feeds = $this->feedreaderlib->parseFeeds(getData($queryResult), null, array('matrikelnummer' => $matrikelnummer));

			if (isError($feeds))
				$result = $feeds;
			elseif (hasData($feeds))
				$result = $feeds;
			else
				$result = success(array());
		}
		else
		{
			$result = success(array());
		}

		$this->outputJson($result);
	}
}
