<?php

/**
 * Functionality for parsing DVUH XML
 */
class XMLReaderLib
{
	const DVUH_NAMESPACE = 'http://www.brz.gv.at/datenverbund-unis';
	const ERRORLIST_TAG = 'fehlerliste';

	private $_error_categories = array('P', 'Y', 'Z');
	private $_warning_categories = array('A', 'B', 'E');

	/**
	 * Parses xml, finds given parameters in xml by provided names and returns the values.
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
	public function parseXmlDvuhBlockingErrors($xmlstr)
	{
		return $this->_parseXmlDvuhError($xmlstr, $this->_error_categories);
	}

	/**
	 * Parses XML for non-blocking warnings (as defined by DVUH).
	 * @param string $xmlstr
	 * @return object array with warnings on success, error otherwise
	 */
	public function parseXmlDvuhWarnings($xmlstr)
	{
		return $this->_parseXmlDvuhError($xmlstr, $this->_warning_categories);
	}

	/**
	 * Parses XML for errors (as defined by DVUH).
	 * @param string $xmlstr
	 * @param array $error_categories types of categories to include in result as errors
	 * @return object array with errors on success, error otherwise
	 */
	private function _parseXmlDvuhError($xmlstr, $error_categories)
	{
		$result = null;
		$resultarr = array();

		$doc = new DOMDocument();
		$loadres = $doc->loadXML($xmlstr);

		if ($loadres)
		{
			$elements = $doc->getElementsByTagNameNs(self::DVUH_NAMESPACE, self::ERRORLIST_TAG);

			if (isset($elements[0]->childNodes))
			{
				$errObjects = $elements[0]->childNodes;

				foreach ($errObjects as $errObject)
				{
					$errResultobj = new stdClass();

					foreach ($errObject->childNodes as $errAttr)
					{
						$errResultobj->{$errAttr->tagName} = $errAttr->nodeValue;
					}
					$errResultobj->full_error_text = $errResultobj->fehlernummer . ': ' . $errResultobj->fehlertext . ' ' . $errResultobj->massnahme;

					if (in_array($errResultobj->kategorie, $error_categories))
						$resultarr[] = $errResultobj;
				}
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
		$errors = $this->parseXmlDvuhBlockingErrors($xmlstr);

		if (isError($errors))
			$result = $errors;
		elseif (hasData($errors))
		{
			$errortext = '';

			foreach (getData($errors) as $error)
			{
				if (!isEmptyString($errortext))
					$errortext .= ', ';
				$errortext .= $error->full_error_text;
			}

			$result = error('Error(s) occured: ' . $errortext);
		}
		else
			$result = $this->parseXml($xmlstr, $searchparams, self::DVUH_NAMESPACE);

		return $result;
	}
}
