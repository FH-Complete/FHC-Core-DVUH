<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library to read Atom RSS feeds from DVUH Services
 */
class FeedReaderLib
{

	public function getFeeds($feedxml)
	{
		$result = null;

		$doc = new DOMDocument();
		$loadres = $doc->loadXML($feedxml);

		if ($loadres)
		{
			$feedentries = array();
			$tags = array('id', 'title');

			$elements = $doc->getElementsByTagName('entry');

			foreach ($elements as $element)
			{
				$feedentry = new stdClass();
				$contentStr = '';

				foreach ($element->childNodes AS $child)
				{
					//var_dump($child);
					if (isset($child->tagName))
					{

						foreach ($tags as $tag)
						{
							if ($child->tagName == $tag)
							{
								$feedentry->{$tag} = $child->nodeValue;
							}
						}

						if ($child->tagName === 'content')
						{
							$this->_getFeedContentString($child, $contentStr);
							$feedentry->content = $contentStr;
						}
					}
				}
				$feedsentries[] = $feedentry;

				$result = success($feedentries);
				//die();
			}
		}
		else
			$result = error('error when parsing feed string');

		return $result;
	}

	private function _getFeedContentString($rootel, &$contentStr)
	{
		foreach ($rootel->childNodes as $childNode)
		{
			if (isset($childNode->childNodes))
			{
				if ($childNode->childNodes->length === 1 && $childNode->childNodes[0]->nodeType === 3)
				{
					$textNode = $childNode->childNodes[0];
					$contentStr .= $childNode->tagName.': ';
					$contentStr .= $textNode->nodeValue.'<br />';
				}
				elseif ($childNode->childNodes->length > 0 && $childNode->nodeType === 1)
				{
					$contentStr .= $childNode->tagName.'<br />';
					$this->_getFeedContentString($childNode, $contentStr);
				}
			}
		}
	}
}
