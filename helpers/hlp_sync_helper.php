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
 * @param string $error_text_for_logging
 * @param string $issue_fehler_kurzbz short unique text id of issue
 * @param string $issue_fehlertext_params parameters for replacement of erroro text
 * @return object the error
 */
function createError($error_text_for_logging, $issue_fehler_kurzbz, $issue_fehlertext_params = null)
{
	$error = new stdClass();
	$error->issue_fehler_kurzbz = $issue_fehler_kurzbz;
	$error->issue_fehlertext_params = $issue_fehlertext_params;

	return error($error_text_for_logging, $error);
}

/**
 * Helper function for creating a DVUH external error object.
 * @param string $error_text_for_logging
 * @param string $fehlernummer DVUH error number
 * @return object the error
 */
function createExternalError($error_text_for_logging, $fehlernummer)
{
	$error = new stdClass();
	$error->fehlernummer = $fehlernummer;
	$error->fehlertextKomplett = $error_text_for_logging;

	return error($error_text_for_logging, array($error));
}