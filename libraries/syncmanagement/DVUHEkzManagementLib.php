<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncmanagement/DVUHManagementLib.php';

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying MasterDat data in DVUH, and updating data in FHC accordingly.
 */
class DVUHEkzManagementLib extends DVUHManagementLib
{
	// Statuscodes returned when checking EKZ, resulting actions are array keys
	private $_ekz_returncodes = array(
		'existing' => '0', // ekz already exists
		'new' => '1', // new ekz assigned
		'multipleForcable' => '2', // multiple ekz returned, forceable (non-exact match)
		'multipleNonForcable' => '4', // multiple ekz returned, non-forceable (exact match)
		'error' => array('10', '99')
	);

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHEkzLib');

		// load models
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Ekzanfordern_model', 'EkzanfordernModel');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Requests EKZ from DVUH, returns info/error messages from result.
	 * Useful for GUIs.
	 * @param $person_id
	 * @param string $forcierungskey
	 * @param bool $preview
	 * @return object error or success
	 */
	public function requestEkz($person_id, $forcierungskey = null, $preview = false)
	{
		$infos = array();

		// get ekz data
		$ekzDataRes = $this->_ci->dvuhekzlib->getEkzData($person_id);

		if (isError($ekzDataRes))
			return $ekzDataRes;

		// get and reset warnings produced by ekzlib
		//~ $warnings = $this->_ci->dvuhekzlib->readWarnings();

		if (!hasData($ekzDataRes))
			return error('Keine Ekz Daten gefunden');

		$ekzData = getData($ekzDataRes);

		if ($preview)
		{
			$postData = $this->_ci->EkzanfordernModel->retrievePostData($ekzData, $forcierungskey);

			if (isError($postData))
				return $postData;

			return $this->getResponseArr(getData($postData), $infos);
		}

		$ekzanfordernResult = $this->_requestEkzObject($ekzData, $forcierungskey);

		if (isError($ekzanfordernResult))
			return $ekzanfordernResult;

		if (!hasData($ekzanfordernResult))
			return error("Fehler bei EKZ-Anfrage");

		$parsedObj = getData($ekzanfordernResult);

		$infomsg = "EKZanfrage ausgeführt";

		if (isset($parsedObj->returntext[0]->text))
		{
			$returnText = $parsedObj->returntext[0]->text;
			if (is_string($returnText))
				$infomsg .= "; " . $returnText;
			elseif (is_array($returnText))
				$infomsg .= "; " . implode(', ', $returnText);
		}

		if (isset($parsedObj->returncode[0]))
		{
			$returnCode = $parsedObj->returncode[0];

			// if error code, return error
			if (in_array($returnCode, $this->_ekz_returncodes['error']))
			{
				return error("Fehler beim Holen von Ekz aufgetreten: $infomsg; Code: $returnCode");
			}
		}

		if (isset($parsedObj->ekz[0]))
			$infomsg .= "; EKZ: " . implode(', ', $parsedObj->ekz);

		if (isset($parsedObj->forcierungskey[0]))
			$infomsg .= "; Forcierungskey(s) für Ekz Auswahl mit weiterer Anfrage: " . implode(', ', $parsedObj->forcierungskey);

		$requestXml = $parsedObj->requestXml;

		$infos[] = $infomsg;

		return $this->getResponseArr(
			$requestXml,
			$infos,
			null,
			true
		);
	}

	/**
	 * Requests EKZ from DVUH, saves Ekz in database if one exact match is returned, returns info/error messages.
	 * @param $person_id
	 * @param string $forcierungskey
	 * @return object error or success
	 */
	public function requestAndSaveEkz($person_id, $forcierungskey = null)
	{
		$infos = array();
		$warnings = array();

		// get ekz data
		$ekzDataRes = $this->_ci->dvuhekzlib->getEkzData($person_id);

		if (isError($ekzDataRes))
			return $ekzDataRes;

		if (!hasData($ekzDataRes))
			return error('Keine Ekz Daten gefunden');

		$ekzData = getData($ekzDataRes);

		$ekzanfordernResult = $this->_requestEkzObject($ekzData, $forcierungskey);

		if (isError($ekzanfordernResult))
			return $ekzanfordernResult;

		if (!hasData($ekzanfordernResult))
			return error("Fehler bei EKZ-Anfrage");

		$parsedObj = getData($ekzanfordernResult);

		if (isset($parsedObj->returncode[0]))
		{
			$returnCode = $parsedObj->returncode[0];

			$returnText = '';
			if (isset($parsedObj->returntext[0]->text))
			{
				$txt = $parsedObj->returntext[0]->text;
				if (is_string($txt))
					$returnText .= $txt;
				elseif (is_array($txt))
					$returnText .= implode(', ', $txt);
			}

			// if exactly one exactly matching ekz can be retrieved, get and save it
			if (in_array($returnCode, array($this->_ekz_returncodes['new'], $this->_ekz_returncodes['existing'])))
			{
				if (isset($parsedObj->ekz[0]))
				{
					$ekz = $parsedObj->ekz[0];

					$ekzSaveResult = $this->_ci->dvuhekzlib->saveEkz($person_id, $ekz);

					if (isError($ekzSaveResult))
						return $ekzSaveResult;

					$infos[] = "Ekz erfolgreich in FHC gespeichert!";
				}
			}
			// if multiple ekz, write warning
			elseif ($returnCode == $this->_ekz_returncodes['multipleForcable'])
			{
				// . '; mehrere Ekz Personenkanditaten, erneute Anfrage mit korrektem Forcierungskey ('.$parsedObj->forcierungskey.') notwendig'
				return createExternalIssueError($returnText, 'EKZ_STATUS_'.$returnCode);
				//$warnings[] = error($returnText . '; mehrere Ekz Personenkanditaten, erneute Anfrage mit korrektem Forcierungskey ('.$parsedObj->forcierungskey.') notwendig');
			}
			elseif ($returnCode == $this->_ekz_returncodes['multipleNonForcable'])
			{
				// . '; mehrere Ekz Personenkanditaten, Stammdaten prüfen, Datenverbund kontaktieren'
				return createExternalIssueError($returnText, 'EKZ_STATUS_'.$returnCode);
				//$warnings[] = error($returnText . '; mehrere Ekz Personenkanditaten, Stammdaten prüfen, Datenverbund kontaktieren');
			}
			// if error code, return error
			elseif (in_array($returnCode, $this->_ekz_returncodes['error']))
			{
				// "Fehler beim Holen von Ekz aufgetreten: $returnText; Code: $returnCode"
				return createExternalIssueError($returnText, 'EKZ_STATUS_'.$returnCode);
				//return error("Fehler beim Holen von Ekz aufgetreten: $returnText; Code: $returnCode");
			}
			// unknown return code
			else
			{
				$unknownCodeStr = "Unbekannter Fehlercode $returnCode";
				if (!isEmptyString($returnText))
					$unknownCodeStrStr .= $returnText;
				return error($unknownCodeStr);
			}
		}

		// return response with request xml string and infos
		$requestXml = $parsedObj->requestXml;

		return $this->getResponseArr(
			$requestXml,
			$infos,
			$warnings,
			true
		);
	}

	/**
	 * Requests EKZ from DVUH, parses returned XML object and returns object with parsed data.
	 * @param array $ekzData
	 * @param string $forcierungskey
	 * @return object error or success
	 */
	private function _requestEkzObject($ekzData, $forcierungskey = null)
	{
		$ekzanfordernResult = $this->_ci->EkzanfordernModel->post($ekzData, $forcierungskey);

		if (isError($ekzanfordernResult))
			return $ekzanfordernResult;

		if (hasData($ekzanfordernResult))
		{
			$xmlstr = getData($ekzanfordernResult);

			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh(
				$xmlstr,
				array('uuid', 'responsecode', 'returncode', 'returntext', 'ekz', 'forcierungskey')
			);

			if (isError($parsedObj))
				return $parsedObj;

			$parsedObj = getData($parsedObj);

			if (!isset($parsedObj->responsecode[0]) || $parsedObj->responsecode[0] != '200')
			{
				$errortext = 'Fehlerantwort bei EKZ-Anfrage.';
				if (isset($parsedObj->responsetext[0]))
					$errortext .= ' ' . $parsedObj->responsetext[0];

				return error($errortext);
			}

			$parsedObj->requestXml = $xmlstr;

			return success($parsedObj);
		}
		else
			return error("Fehler bei EKZ-Anfrage");
	}
}
