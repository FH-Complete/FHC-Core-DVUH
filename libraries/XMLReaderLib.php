<?php


class XMLReaderLib
{

	public function parseXml($xmlstr, $searchparams)
	{
		$result = null;

		$doc = new DOMDocument();
		$loadres = $doc->loadXML($xmlstr);

		if ($loadres)
		{
			$resultObj = new stdClass();

			foreach ($searchparams as $searchparam)
			{
				$reselements = array();
				$elements = $doc->getElementsByTagName($searchparam);

				foreach ($elements as $element)
				{
					$reselements[] = $element->nodeValue;
				}

				$resultObj->{$searchparam} = $reselements;
			}

			$result = success($resultObj);

		}
		else
		{
			$result = error('error when parsing feed string');
		}

		return $result;
	}

}