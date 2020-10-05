<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library to read Atom RSS feeds from DVUH Services
 */
class FeedReaderLib
{

	public function getFeeds($feedxml)
	{
		$feeds = array();

		$tags = array('id', 'title');

		$doc = new DOMDocument();
		$doc->loadXML($feedxml);
		$elements = $doc->getElementsByTagName('entry');

		foreach ($elements as $element)
		{
			//var_dump($element);

			//if (isset($element->))

			$feedentry = new stdClass();

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
						foreach ($child->childNodes as $childNode)
						{
							//var_dump($childNode);
						}
					}
				}
			}
			$feedsentries[] = $feedentry;
		}

		var_dump($feedsentries);
	}

}
