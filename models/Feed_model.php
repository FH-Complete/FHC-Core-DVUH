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
		$this->_url = 'feed.xml';
	}

	/**
	 * Performs the Webservie Call to Read the Feed
	 * @param $string be Code of the Bildungseinrichtung
	 * @param string $content 'true'|'false' include the content directly in the Feed or not
	 * @param string $erstelltSeit Date of Feed start (Format: 1990-01-01)
	 * @param string $markread 'true'|'false' defines if the entries are marked as read
	 * @return object success or error
	 */
	public function get($be,  $content = null, $erstelltSeit = null, $markread = null)
	{
		$callParametersArray = array(
			'be' => $be,
			'uuid' => getUUID()
		);

		if (!is_null($content))
			$callParametersArray['content'] = $content;

		if (!is_null($erstelltSeit))
			$callParametersArray['erstelltSeit'] = $erstelltSeit;

		if (!is_null($markread))
			$callParametersArray['markread'] = $markread;

		$result = $this->_call(ClientLib::HTTP_GET_METHOD, $callParametersArray);

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

		return $this->_call(ClientLib::HTTP_GET_METHOD, $callParametersArray);
	}
}
