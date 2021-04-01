<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library to read Atom RSS feeds from DVUH Services
 */
class FeedReaderLib
{
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');
	}

	/**
	 * Gets feeds filtered by type.
	 * @param string $be
	 * @param string $erstelltSeit
	 * @param array $types
	 * @param string $contentFilters filters (name and value) applied on feed content
	 * @return object
	 */
	public function getFeedsByType($be, $erstelltSeit, $types, $contentFilters)
	{
		$feeds = $this->_ci->FeedModel->get($be, 'false', $erstelltSeit, 'false');

		if (isError($feeds))
			return $feeds;

		if (hasData($feeds))
		{
			$feedxml = getData($feeds);

			return $this->parseFeeds($feedxml, $types, $contentFilters);
		}
	}

	/**
	 * Parse an XML feed string, create feed objects.
	 * @param string $feedxml
	 * @param array $types for filtering by type in feed title
	 * @param array $contentFilters filters (name and value) applied on feed content, if no match, feed entry is excluded.
	 * @return object feed entry objects
	 */
	public function parseFeeds($feedxml, $types = null, $contentFilters = array())
	{
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$portal = $this->_ci->config->item('fhc_dvuh_connections')[$this->_ci->config->item('fhc_dvuh_active_connection')]['portal'];

		$result = null;

		$doc = new DOMDocument();
		$loadres = $doc->loadXML($feedxml);

		if ($loadres)
		{
			$feedentries = array();
			$tags = array('id', 'title', 'author', 'published', 'updated');

			$elements = $doc->getElementsByTagName('entry');

			foreach ($elements as $element)
			{
				$feedentry = new stdClass();
				$singleContentStr = '';

				$hasContent = false;
				$filtered = false;

				foreach ($element->childNodes AS $child)
				{
					if (isset($child->tagName))
					{
						if (!isEmptyArray($types) && $child->tagName == 'title')
						{
							if (!in_array($child->nodeValue, $types))
								continue 2; // skip to next entry if not of searched type
						}
						elseif ($child->tagName == 'content')
						{
							$hasContent = true;
						}
					}
				}

				foreach ($element->childNodes AS $child)
				{
					if (isset($child->tagName))
					{
						foreach ($tags as $tag)
						{
							if ($child->tagName == $tag)
							{
								$feedentry->{$tag} = trim($child->nodeValue);
							}
						}

						if ($child->tagName == 'content')
						{
							$feedentry->contentXml = $doc->saveXML($child);
							$this->_getFeedContentString($child, $singleContentStr);
							$feedentry->contentHtml = $singleContentStr;

						}
						elseif ($child->tagName == 'link' && !$hasContent)
						{
							$linkobj = null;
							$contentLink = null;
							foreach ($child->attributes as $attribute)
							{
								if ($attribute->nodeName == 'rel' && $attribute->nodeValue == 'alternate')
								{
									$linkobj = $child;
									break;
								}
							}

							if (isset($linkobj))
							{
								foreach ($linkobj->attributes as $attribute)
								{
									if ($attribute->name == 'href')
									{
										$contentLink = $attribute->nodeValue;
										break;
									}
								}
							}
							$contentPath = str_replace($portal, '', $contentLink);

							if (!isEmptyString($contentPath))
							{
								$callParametersArray = $this->_extractGetParams($contentPath);
								$feedcontentRes = $this->_ci->FeedModel->getFeedContent($contentPath, $callParametersArray);

								if (hasData($feedcontentRes))
								{
									$feedcontent = getData($feedcontentRes);

									$feedentry->contentXml = $feedcontent;
									$docContent = new DOMDocument();
									if ($docContent->loadXML($feedcontent))
									{
										$singleContentStr = '';
										$this->_getFeedContentString($docContent, $singleContentStr);
										$feedentry->contentHtml = $singleContentStr;
									}
								}
							}
						}

						foreach ($contentFilters as $field => $value)
						{
							$filter_res = $this->_getNode($child, $field);

							if (!isEmptyString($value) && isset($filter_res) && !strstr($filter_res, $value))
								$filtered = true; // do not include if not matching filter criteria
						}
					}
				}
				if (!$filtered)
					$feedentries[] = $feedentry;
			}
			$result = success($feedentries);
		}
		else
			$result = error('error when parsing feed string');

		return $result;
	}

	/**
	 * Create formatted feed content string out of DVUH-specific data.
	 * @param object $rootel root xml element containing data
	 * @param string $contentStr content string to create
	 * @param int $level recursion level
	 */
	private function _getFeedContentString($rootel, &$contentStr, $level = 0)
	{
		foreach ($rootel->childNodes as $childNode)
		{
			if (isset($childNode->childNodes))
			{
				if ($childNode->childNodes->length === 1 && $childNode->childNodes[0]->nodeType === 3)
				{
					$textNode = $childNode->childNodes[0];
					$contentStr .= str_repeat('&nbsp;', $level * 2 + 2);
					$contentStr .= $childNode->localName.': ';
					$contentStr .= $textNode->nodeValue.'<br />';
				}
				elseif ($childNode->childNodes->length > 0 && $childNode->nodeType === 1)
				{
					$contentStr .= str_repeat('&nbsp;', $level * 2);
					$contentStr .= '[' . $childNode->localName.']<br />';
					$level++;
					$this->_getFeedContentString($childNode, $contentStr, $level);
				}
			}
		}
	}

	/**
	 * Get node from xml root element.
	 * @param $rootel object root xml element containing data
	 * @param $nodename string name of node to search for
	 * @return string the first found matrikelnummer
	 */
	private function _getNode($rootel, $nodename)
	{
		foreach ($rootel->childNodes as $childNode)
		{
			if (isset($childNode->childNodes))
			{
				if ($childNode->childNodes->length === 1 && $childNode->childNodes[0]->nodeType === 3)
				{
					$textNode = $childNode->childNodes[0];
					if ($childNode->localName == $nodename)
					{
						return $textNode->nodeValue;
					}
				}
				elseif ($childNode->childNodes->length > 0 && $childNode->nodeType === 1)
				{
					return $this->_getNode($childNode, $nodename);
				}
			}
		}
	}

	/**
	 * Extracts get parameters and removes them from an url.
	 * @param string $url get parameters will be removed from this url
	 * @return array get parameters
	 */
	private function _extractGetParams(&$url)
	{
		$callParametersArray = array();

		$urlSplit = explode('?', $url, 2);

		if ($urlSplit && count($urlSplit) == 2)
		{
			$url = $urlSplit[0];
			$paramsStr = $urlSplit[1];

			$params = explode('&', $paramsStr);

			foreach ($params as $param)
			{
				$nameValuePair = explode('=', $param, 2);
				if ($nameValuePair && count($nameValuePair) == 2)
				{
					$callParametersArray[$nameValuePair[0]] = $nameValuePair[1];
				}
			}
		}

		return $callParametersArray;
	}
}
