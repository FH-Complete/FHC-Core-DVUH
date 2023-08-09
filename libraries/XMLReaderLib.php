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


	// --------------------------------------------------------------------------------------------
	// Public methods

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
			$errorsArr = getData($errors);

			foreach ($errorsArr as $error)
			{
				if (!isEmptyString($errortext))
					$errortext .= ', ';
				$errortext .= $error->issue_fehlertext;
			}

			$result = error($errorsArr);
		}
		else
			$result = $this->parseXml($xmlstr, $searchparams, self::DVUH_NAMESPACE);

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
					// if element node with children, save as php object
					if ($element->nodeType == XML_ELEMENT_NODE && ($element->childNodes) && numberOfElements($element->childNodes) > 0)
					{
						$obj = new stdClass();
						$this->_convertDomElementToPhpObj($element, $obj);
						$reselements[] = $obj;
					}
					else // otherwise text node -> save value
						$reselements[] = $element->nodeValue;
				}

				$resultObj->{$searchparam} = $reselements;
			}

			$result = success($resultObj);
		}
		else
		{
			$result = error('error when parsing xml string');
		}

		return $result;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

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

		if (!$loadres)
			return error('error when parsing xml string');

		$elements = $doc->getElementsByTagNameNs(self::DVUH_NAMESPACE, self::ERRORLIST_TAG);

		if (isset($elements[0]->childNodes))
		{
			$errObjects = $elements[0]->childNodes;

			foreach ($errObjects as $errObject)
			{
				$errResultobj = new stdClass();

				foreach ($errObject->childNodes as $errAttr)
				{
					$errResultobj->{$errAttr->nodeName} = $errAttr->nodeValue;
				}

				$errResultobj->issue_fehlertext =
					$errResultobj->fehlernummer . ': ' .
					(isset($errResultobj->feldinhalt) && !isEmptyString($errResultobj->feldinhalt) ? $errResultobj->feldinhalt . ' ' : '') .
					$errResultobj->fehlertext .
					(isset($errResultobj->massnahme) && !isEmptyString($errResultobj->massnahme) ? ', ' . $errResultobj->massnahme : '');

				if (in_array($errResultobj->kategorie, $error_categories))
					$resultarr[] = $errResultobj;
			}
		}

		return success($resultarr);
	}

	/**
	 * Converts dom element to php stdClass object.
	 * @param object $domElement
	 * @param object $phpObject
	 */
	private function _convertDomElementToPhpObj($domElement, &$phpObject)
	{
		// for all child nodes of element
		foreach ($domElement->childNodes as $child)
		{
			// continue recursion if it is an element node and there are child nodes
			if ($child->nodeType == XML_ELEMENT_NODE && isset($child->childNodes))
			{
				if ($child->childNodes->length > 0)
				{
					// if there is already element with same name on this level...
					if (isset($phpObject->{$child->nodeName}))
					{
						// ...create array if multiple elements with same name
						if (!is_array($phpObject->{$child->nodeName}))
							$phpObject->{$child->nodeName} = array($phpObject->{$child->nodeName});

						// add new element to array
						$phpObject->{$child->nodeName}[] = new stdClass();

						// recursive call for new array element to fill child data
						$this->_convertDomElementToPhpObj($child, $phpObject->{$child->nodeName}[numberOfElements($phpObject->{$child->nodeName}) -1]);
					}
					else // if element does not exist yet, create it and go down one level
					{
						$phpObject->{$child->nodeName} = new stdClass();
						$this->_convertDomElementToPhpObj($child, $phpObject->{$child->nodeName});
					}
				}
				else // empty string if no children
					$phpObject->{$child->nodeName} = '';
			}
			else // no children anymore, set the value
			{
				$phpObject = $child->nodeValue;
			}
		}
	}
}
