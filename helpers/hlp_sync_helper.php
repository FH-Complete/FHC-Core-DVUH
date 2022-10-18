<?php

/** Checks a string for XML special chars.
 * @param string $textValue
 * @return bool true if textValue contains no special chars, false otherwise
 */
function validateXmlTextValue($textValue)
{
	return is_string($textValue) && !strpos($textValue, '<') && !strpos($textValue, '>') && !strpos($textValue, '&');
}

/**
 * Checks if date exists and is in valid format.
 * @param string $date
 * @param string $format
 * @return bool
 */
function validateDate($date, $format = 'Y-m-d')
{
	$d = DateTime::createFromFormat($format, $date);
	// The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
	return $d && $d->format($format) === $date;
}

/**
 * Converts date to ISO date yyyy-mm-dd
 * @param string $date
 */
function convertDateToIso($date)
{
	if (validateDate($date, 'd.m.Y'))
	{
		$dateObj = new DateTime($date);
		return $dateObj->format('Y-m-d');
	}

	return $date;
}

/**
 * Extracts street name from full adress.
 * @param $street
 * @return string
 */
function getStreetFromAddress($street)
{
	// remove any non-letter character (unicode /u for german letters), but keep space inbetween
	return trim(preg_replace('/([^\s\p{L}])/u', '', $street));
}

/**
 * Helper function for returning difference between two dates in days.
 * @param string $datum1
 * @param string $datum2
 * @return false|int
 */
function dateDiff($datum1, $datum2)
{
	$datetime1 = new DateTime($datum1);
	$datetime2 = new DateTime($datum2);
	$interval = $datetime1->diff($datetime2);
	return $interval->days;
}

/**
 * Helper function for creating a custom error object with issue data.
 * @param string $issue_fehler_kurzbz short unique text id of issue
 * @param array $issue_fehlertext_params parameters for replacement of issue error text
 * @param array $issue_resolution_params parameters used for check if issue is resolved, associative array
 * @return object the error
 */
function createError($issue_fehlertext, $issue_fehlertext_params = null, $issue_resolution_params = null)
{
	$error = new stdClass();
	$error->issue_fehlertext = $issue_fehlertext;
	$error->issue_fehler_kurzbz = $issue_fehler_kurzbz;
	$error->issue_fehlertext_params = $issue_fehlertext_params;
	$error->issue_resolution_params = $issue_resolution_params;

	return $error;
}

/**
 * Helper function for creating a external error object for errors produced by DVUH.
 * @param string $fehlertext
 * @param string $fehlernummer DVUH error number
 * @return object the error
 */
function createExternalError($fehlertext, $fehlernummer)
{
	$error = new stdClass();
	$error->fehlernummer = $fehlernummer;
	$error->issue_fehlertext = $fehlertext;

	return array($error);
}
