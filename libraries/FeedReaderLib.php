<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library to read Atom RSS feeds from DVUH Services
 */
class FeedReaderLib
{
	const MATRIKELNR_NAME = 'matrikelnummer';

	/**
	 * Parse an XML feed string, create feed objects.
	 * @param string $feedxml
	 * @param string $matrikelnummer optionally filter by matrikelnr
	 * @return object feed entry objects
	 */
	public function parseFeeds($feedxml, $matrikelnummer = null)
	{
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
				$contentStr = '';

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

						if ($child->tagName === 'content')
						{
							$this->_getFeedContentString($child, $contentStr);
							$feedentry->content = $contentStr;
							$matrikelnr_filter = $this->_getMatrikelnr($child);

							if (!isEmptyString($matrikelnummer) && isset($matrikelnr_filter) && !strstr($matrikelnr_filter, $matrikelnummer))
								continue 2; // continue elements loop if not correct matrikelnr

							$feedentry->matrikelnr_filter = $matrikelnr_filter;
						}
					}
				}
				$feedentries[] = $feedentry;
			}
			$result = success($feedentries);
		}
		else
			$result = error('error when parsing feed string');

		return $result;
	}

	/**
	 * Create feed content string out of DV-specific data.
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
	 * Get Matrikelnummer from DV-specific data.
	 * @param $rootel root xml element containing data
	 * @return string the first found matrikelnummer
	 */
	private function _getMatrikelnr($rootel)
	{
		foreach ($rootel->childNodes as $childNode)
		{
			if (isset($childNode->childNodes))
			{
				if ($childNode->childNodes->length === 1 && $childNode->childNodes[0]->nodeType === 3)
				{
					$textNode = $childNode->childNodes[0];
					if ($childNode->localName == self::MATRIKELNR_NAME)
					{
						return $textNode->nodeValue;
					}
				}
				elseif ($childNode->childNodes->length > 0 && $childNode->nodeType === 1)
				{
					return $this->_getMatrikelnr($childNode);
				}
			}
		}
	}
}
