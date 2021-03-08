<?php

/**
 * Functionality for parsing DVUH XML
 */
class XMLReaderLib
{
	const MATRNR_NAMESPACE = 'http://www.brz.gv.at/datenverbund-unis';
	const ERRORLIST_TAG = 'fehlerliste';

	private $_error_categories = array('Z', 'P', 'Y');

	/**
	 * parses xml, finds given parameters in xml by provided names and returns the values.
	 * @param string $xmlstr
	 * @param mixed $searchparams one parameter as string or multiple parameters as array of strings
	 * @param string $namespace
	 * @return object success with results or error
	 */
	public function parseXml($xmlstr, $searchparams, $namespace = null)
	{
		$result = null;

		$doc = new DOMDocument();
		$loadres = $doc->loadXML($xmlstr);

		if ($loadres)
		{
			$resultObj = new stdClass();
			if (!is_array($searchparams))
			{
				if (is_string($searchparams))
					$searchparams = array($searchparams);
				else
					return error('invalid searchparameters!');
			}

			foreach ($searchparams as $searchparam)
			{
				$reselements = array();
				if (!isEmptyString($namespace))
					$elements = $doc->getElementsByTagNameNS($namespace, $searchparam);
				else
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

	/**
	 * Parses XML for blocking errors (as defined by DVUH).
	 * @param string $xmlstr
	 * @return object array with errors on success, error otherwise
	 */
	public function parseXmlDvuhError($xmlstr)
	{
		$result = null;
		$resultarr = array();

		$doc = new DOMDocument();
		$loadres = $doc->loadXML($xmlstr);

		if ($loadres)
		{
			$elements = $doc->getElementsByTagNameNs(self::MATRNR_NAMESPACE, self::ERRORLIST_TAG);

			$errObjects = $elements[0]->childNodes;

			foreach ($errObjects as $errObject)
			{
				$errResultobj = new stdClass();

				foreach ($errObject->childNodes as $errAttr)
				{
					$errResultobj->{$errAttr->tagName} = $errAttr->nodeValue;
				}

				if (in_array($errResultobj->kategorie, $this->_error_categories))
					$resultarr[] = $errResultobj->fehlernummer . ': ' . $errResultobj->fehlertext . ' ' . $errResultobj->massnahme;

			}

			$result = success($resultarr);
		}
		else
		{
			$result = error('error when parsing feed string');
		}

		return $result;
	}

	/**
	 * Parses DVUH XML, checks if XML contains DVUH-specific errors.
	 * If no errors found, finds given parameters in xml by provided names and returns the values.
	 * @param string $xmlstr
	 * @param array $searchparams one parameter as string or multiple parameters as array of strings
	 * @return object success with results or error
	 */
	public function parseXmlDvuh($xmlstr, $searchparams)
	{
		$result = null;
		$errors = $this->parseXmlDvuhError($xmlstr);

		if (isError($errors))
			$result = $errors;
		elseif (hasData($errors))
			$result = error('Error(s) occured: ' . implode(',', getData($errors)));
		else
			$result = $this->parseXml($xmlstr, $searchparams, self::MATRNR_NAMESPACE);

		return $result;
	}
}
