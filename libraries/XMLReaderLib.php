<?php

/**
 * Functionality for parsing DVUH XML
 */
class XMLReaderLib
{
	const DVUH_NAMESPACE_PREFIX = 'dvuh';
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
		return $this->_parseXmlDvuh($xmlstr, $searchparams);
	}

	/**
	 * Parses DVUH XML, includes attributes of XML elements, checks if XML contains DVUH-specific errors.
	 * If no errors found, finds given parameters in xml by provided names and returns the values.
	 * @param string $xmlstr
	 * @param array $searchparams one parameter as string or multiple parameters as array of strings
	 * @return object success with results or error
	 */
	public function parseXmlDvuhIncludeAttributes($xmlstr, $searchparams)
	{
		return $this->_parseXmlDvuh($xmlstr, $searchparams, $includeAttributes = true);
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
	 * @return object success with results or error
	 */
	public function parseXml($xmlstr, $searchparams)
	{
		return $this->_parseXml($xmlstr, $searchparams);
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Parses DVUH XML, checks if XML contains DVUH-specific errors.
	 * If no errors found, finds given parameters in xml by provided names and returns the values.
	 * @param string $xmlstr
	 * @param array $searchparams one parameter as string or multiple parameters as array of strings
	 * @return object success with results or error
	 */
	private function _parseXmlDvuh($xmlstr, $searchparams, $includeAttributes = false)
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
			$result = $this->_parseXml($xmlstr, $searchparams, $includeAttributes);

		return $result;
	}

	/**
	 * Parses xml, finds given parameters in xml by provided names and returns the values.
	 * @param string $xmlstr
	 * @param mixed $searchparams one parameter as string or multiple parameters as array of strings
	 * @param string $namespace
	 * @param bool $includeAttributes include attributes of XML elements
	 * @return object success with results or error
	 */
	private function _parseXml($xmlstr, $searchparams, $includeAttributes = false)
	{
		$result = null;

		// load xml
		$xmlElement = simplexml_load_string($xmlstr);

		// xml data should be loaded successfully
		if (!$xmlElement) return error('error when parsing xml string');

		// register DVUH namespace
		$xmlElement->registerXPathNamespace(self::DVUH_NAMESPACE_PREFIX, self::DVUH_NAMESPACE);

		$resultObj = new stdClass();

		// check search params
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

			// find all elements with searched tag name using xpath
			$elements = $xmlElement->xpath('//'.self::DVUH_NAMESPACE_PREFIX.':'.$searchparam);

			foreach ($elements as $element)
			{
				// if xml element has children
				if ($element->count() > 0)
				{
					// convert xmlobject with children to std class object, return whole object
					$reselements[] = json_decode(json_encode($element));
				}
				else // otherwise the value without chldren should be returned
				{
					// if attributes should be returned together with element
					if ($includeAttributes)
					{
						// create object and assign text value and attributes
						$phpObject = new stdClass();
						$phpObject->value = $element->__toString();
						$phpObject->attributes = array();
						foreach ($element->attributes() as $key => $value)
						{
							$phpObject->attributes[$key] = $value->__toString();
						}

						$reselements[] = $phpObject;
					}
					else
						$reselements[] = $element->__toString();
				}
			}

			// assign found elements to return object
			$resultObj->{$searchparam} = $reselements;
		}

		$result = success($resultObj);

		return $result;
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

		// load xml
		$xmlElement = simplexml_load_string($xmlstr);

		if (!$xmlElement) return error('error when parsing xml string');

		$xmlElement->registerXPathNamespace(self::DVUH_NAMESPACE_PREFIX, self::DVUH_NAMESPACE);

		$elements = $xmlElement->xpath('//'.self::DVUH_NAMESPACE_PREFIX.':'.self::ERRORLIST_TAG);

		if (isset($elements[0]) && $elements[0]->count() > 0)
		{
			$errObjects = $elements[0]->children();

			foreach ($errObjects as $errObject)
			{
				$errResultobj = new stdClass();
				$errArr = json_decode(json_encode($errObject), true);

				foreach ($errArr as $errorName => $errorValue)
				{
					$errResultobj->{$errorName} = $errorValue;
				}

				// feldinhalt can be array or string
				$feldinhalt = is_array($errResultobj->feldinhalt) && !isEmptyArray($errResultobj->feldinhalt)
					? implode('; ', $errResultobj->feldinhalt)
					: $errResultobj->feldinhalt;

				$errResultobj->issue_fehlertext =
					$errResultobj->fehlernummer . ': ' .
					(isset($feldinhalt) && !isEmptyString($feldinhalt) ? $feldinhalt . ' ' : '') .
					$errResultobj->fehlertext .
					(isset($errResultobj->datenfeld) ? ', '.$errResultobj->datenfeld : '') .
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
						$this->_convertDomElementToPhpObj(
							$child,
							$phpObject->{$child->nodeName}[count($phpObject->{$child->nodeName}) -1],
							$includeAttributes
						);
					}
					else // if element does not exist yet, create it and go down one level
					{
						$phpObject->{$child->nodeName} = new stdClass();
						$this->_convertDomElementToPhpObj($child, $phpObject->{$child->nodeName}, $includeAttributes);
					}
				}
				else // empty string if no children
					$phpObject->{$child->nodeName} = '';
			}
			else // no children anymore, set the value
			{
				if ($includeAttributes === true)
				{
					$phpObject = new stdClass();

					$phpObject->value = $child->nodeValue;
					if (isset($domElement->attributes))
					{
						$phpObject->attributes = array();
						foreach ($domElement->attributes as $attribute)
						{
							$phpObject->attributes[$attribute->nodeName] = $attribute->nodeValue;
						}
					}
				}
				else
					$phpObject = $child->nodeValue;
			}
		}
	}
}
