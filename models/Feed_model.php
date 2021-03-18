<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read Message Feed
 */
class Feed_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/0.5/feed.xml';
	}

	/**
	 * Performs the Webservie Call to Read the Feed
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $content 'true'|'false' include the content directly in the Feed or not
	 * @param $erstelltSeit Date of Feed start (Format: 1990-01-01)
	 * @param $markread 'true'|'false' defines if the entries are marked as read
	 */
	public function get($be,  $content, $erstelltSeit, $markread)
	{
		$callParametersArray = array(
			'be' => $be,
			'content' => $content,
			'uuid' => getUUID()
		);

		if (!is_null($erstelltSeit))
			$callParametersArray['erstelltSeit'] = $erstelltSeit;

		if (!is_null($markread))
			$callParametersArray['markread'] = $markread;

		$result = $this->_call('GET', $callParametersArray);

		return $result;
	}

	/**
	 * Gets feed content using alternate url provided by a feed.
	 * @param string $url excluding base url
	 * @param array $callParametersArray get params
	 * @return object
	 */
	public function getFeedContent($url, $callParametersArray)
	{
		$this->_url = $url;

		return $this->_call('GET', $callParametersArray);
	}
}
