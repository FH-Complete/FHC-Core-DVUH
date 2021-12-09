<?php

/**
 * Library for BPK Management.
 */
class BPKManagementLib
{
	private $_ci;
	private $_dbModel;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
		$this->_dbModel = new DB_Model();

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('person/Adresse_model', 'AdresseModel');
		$this->_ci->load->model('crm/Akte_model', 'AkteModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Pruefebpk_model', 'PruefebpkModel');

		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHSyncLib');

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHBpkCheck');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Loads all necessary Person data: Stammdaten (name, svnr, contact, ...), Dokumente, Logs and Notizen
	 * @param $person_id
	 * @return array
	 */
	public function loadPersonData($person_id)
	{
		// get Stammdaten
		$stammdatenRes = $this->_ci->PersonModel->getPersonStammdaten($person_id);

		if (isError($stammdatenRes))
		{
			return $stammdatenRes;
		}

		if (!hasData($stammdatenRes))
			return error("Person nicht gefunden");

		$dokument_kurzbz = $this->_ci->config->item('fhc_dvuh_bpkcheck_relevant_documenttypes');

		// get documents
		$qry = "
			SELECT akte_id, dokument_kurzbz, titel, akte.bezeichnung as akte_bezeichnung, akte.erstelltam,
			       dok.bezeichnung as dokument_bezeichnung, dok.bezeichnung_mehrsprachig as dokument_bezeichnung_mehrsprachig,
			       akte.anmerkung as akte_anmerkung, nat.nation_code, nat.langtext as nation, nat.engltext as nation_englisch
			FROM public.tbl_akte akte
			JOIN public.tbl_dokument dok USING (dokument_kurzbz)
			LEFT JOIN bis.tbl_nation nat ON ausstellungsnation = nation_code
			WHERE akte.person_id = ?
			AND dokument_kurzbz IN ?;
		";

		$documentsRes = $this->_dbModel->execReadOnlyQuery($qry, array($person_id, $dokument_kurzbz));

		if (isError($documentsRes))
		{
			return $documentsRes;
		}

		return success(
			array(
				'stammdaten' => getData($stammdatenRes),
				'dokumente' => hasData($documentsRes) ? getData($documentsRes) : array()
			)
		);
	}

	public function checkBpkCombinations($person_id)
	{
		// get all person data needed for bpk check
		$personDataRes = $this->getPersonDataForBpkCheck($person_id);

		if (isError($personDataRes))
			return $personDataRes;

		if (hasData($personDataRes))
		{
			$personData = getData($personDataRes);

			$nameArr = array(
				'vorname' => $personData['vorname'],
				'vornamen' => $personData['vornamen'],
				'nachname' => $personData['nachname']
			);

			// get firstname/lastname combinations for bpk check
			$combinations = $this->getNamesForBpkCheck($nameArr);

			$allBpkResults = array();

			foreach ($combinations as $combination)
			{
				$bpkRequestData = array();
				$bpkRequestData['vorname'] = $combination['vorname'];
				$bpkRequestData['nachname'] = $combination['nachname'];
				$bpkRequestData['geburtsdatum'] = $personData['gebdatum'];

				// execute bpk call
				$bpkRes = $this->executeBpkRequest($bpkRequestData);

				if (isError($bpkRes))
					return $bpkRes;

				if (hasData($bpkRes))
				{
					$bpkResponseData = getData($bpkRes);

					$this->_addBpkResponseToResults($bpkRequestData, $bpkResponseData, $allBpkResults);

					if ($bpkResponseData['numberPersonsFound'] > 1)
					{
						// if multiple person results, check with more parameters
						$additionalParamms = array(
							'geschlecht' => $personData['geschlecht'],
							'plz' => $personData['plz'],
							'strasse' => $personData['strasse']
						);

						foreach ($additionalParamms as $name => $param)
						{
							$bpkExtendedRequestData = $bpkRequestData;
							$bpkExtendedRequestData[$name] = $param;

							$bpkRes = $this->executeBpkRequest($bpkExtendedRequestData);

							if (isError($bpkRes))
								return $bpkRes;

							if (hasData($bpkRes))
							{
								$bpkNewResponseData = getData($bpkRes);

								$this->_addBpkResponseToResults($bpkExtendedRequestData, $bpkNewResponseData, $allBpkResults);
							}
						}
					}
				}
			}

			return success($allBpkResults);
		}
		else
			return error("Person nicht gefunden");
	}

	public function getPersonDataForBpkCheck($person_id)
	{
		$stammdatenRes = $this->_ci->PersonModel->getPersonStammdaten($person_id);

		if (isError($stammdatenRes))
			return $stammdatenRes;

		if (hasData($stammdatenRes))
		{
			$stammdaten = getData($stammdatenRes);

			$personData = array(
				'vorname' => $stammdaten->vorname,
				'nachname' => $stammdaten->nachname,
				'vornamen' => $stammdaten->vornamen,
				'gebdatum' => $stammdaten->gebdatum,
				'bpk' => $stammdaten->bpk,
				'svnr' => $stammdaten->svnr,
				'ersatzkennzeichen' => $stammdaten->ersatzkennzeichen,
				'geschlecht' => $this->_ci->dvuhsynclib->convertGeschlechtToDVUH($stammdaten->geschlecht),
				'geburtsland' => $stammdaten->geburtsnation_code,
				'akadgrad' => $stammdaten->titelpre,
				'akadnach' => $stammdaten->titelpost
			);

			$latestInsertamum = '';
			$latestAdresse = null;

			// get latest Heimatadresse
			foreach ($stammdaten->adressen as $adresse)
			{
				if ($adresse->heimatadresse === true && (isEmptyString($latestInsertamum) || $adresse->insertamum > $latestInsertamum))
				{
					$latestAdresse = $adresse;
					$latestInsertamum = $adresse->insertamum;
				}
			}

			if (isset($latestAdresse->strasse))
				$personData['strasse'] = getStreetFromAddress($latestAdresse->strasse);

			if (isset($latestAdresse->plz))
				$personData['plz'] = $latestAdresse->plz;

			return success($personData);
		}
		else
		{
			return error("Person nicht gefunden");
		}
	}

	public function executeBpkRequest($personData)
	{
		$bpkRes =  array('bpk' => null, 'personData' => array(), 'numberPersonsFound' => 0);

		$pruefeBpkResult = $this->_ci->PruefebpkModel->get(
			$personData['vorname'],
			$personData['nachname'],
			$personData['geburtsdatum'],
			isset($personData['geschlecht']) ? $personData['geschlecht'] : null,
			isset($personData['strasse']) ? $personData['strasse'] : null,
			isset($personData['plz']) ? $personData['plz'] : null
		);

		if (isError($pruefeBpkResult))
		{
			return $pruefeBpkResult;
		}

		if (hasData($pruefeBpkResult))
		{
			$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh(getData($pruefeBpkResult), array('bpk', 'personInfo'));

			if (isError($parsedObj))
				return $parsedObj;

			if (hasData($parsedObj))
			{
				$parsedObj = getData($parsedObj);

				$personData = array();

				foreach ($parsedObj->personInfo as $personInfo)
				{
					$person = new stdClass();
					foreach ($personInfo as $pInfoPropName => $pInfoPropValue) {
						foreach ($pInfoPropValue as $propName => $propValue) {
							$person->{$propName} = $propValue;
						}
					}
					$personData[] = $person;
				}

				// no bpk found
				if (isEmptyArray($parsedObj->bpk))
				{
					$bpkRes = array('bpk' => null, 'personData' => $personData, 'numberPersonsFound' => count($parsedObj->personInfo));
				}
				else // bpk found
				{
					$bpkRes = array('bpk' => $parsedObj->bpk[0], 'personData' => $personData, 'numberPersonsFound' => count($parsedObj->bpk));
				}
			}
		}

		return success($bpkRes);
	}

	private function _addBpkResponseToResults($bpkRequestData, $bpkResponseData, &$allBpkResults)
	{
		if (isEmptyArray($bpkResponseData['personData']))
			return;

		$bpkResAlreadyExists = false;
		for ($i = 0; $i < count($allBpkResults); $i++)
		{
			// if bpk result already returned by other combination, add sent combination to data of existing request
			if ($this->_checkBpkResponsesForEquality($bpkResponseData, $allBpkResults[$i]['responseData']))
			{
				$allBpkResults[$i]['requestData'][] = $bpkRequestData;
				$bpkResAlreadyExists = true;
				break;
			}
		}

		// if it is a new bpk result, add new response and request data
		if (!$bpkResAlreadyExists)
		{
			$bpkResponse = array();
			$bpkResponse['responseData'] = $bpkResponseData;
			$bpkResponse['requestData'][] = $bpkRequestData;
			$allBpkResults[] = $bpkResponse;
		}
	}

	private function _getVariationsFromName($name, $charsReplace)
	{
		$enc = 'UTF-8';
		$name = htmlentities($name, ENT_NOQUOTES | ENT_HTML5, $enc);

		$pattern = '/('.implode('|', $charsReplace).')/';

		// split whole word into parts by replacing symbols
		$parts = preg_split($pattern, $name, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		$replaceParts = array();
		$placeholder = '';

		// create string with placeholders (%s) for sprinf and array of replacing symbols
		foreach ($parts as $wordPart)
		{
			$found = false;
			foreach ($charsReplace as $replacement => $ptrn)
			{
				if (preg_match('/'.$ptrn.'/', $wordPart) === 1)
				{
					$replaceParts[] = array('word' => $wordPart, 'replacement' => $replacement);
					$placeholder .= '%s';
					$found = true;
				}
			}

			if (!$found)
			{
				$placeholder .= html_entity_decode($wordPart, ENT_NOQUOTES | ENT_HTML5, $enc);
			}
		}

		$paramsCnt = count($replaceParts);
		$combinations = array();
		$combinationsCnt = pow(2, $paramsCnt);

		// iterate all combinations (with help of binary codes)
		for ($i = 0; $i < $combinationsCnt; $i++)
		{
			$mask = sprintf('%0'.$paramsCnt.'b', $i);
			$sprintfParams = array($placeholder);
			foreach ($replaceParts as $index => $replacePart)
			{
				$sprintfParams[] = $mask[$index] == 1 ? $replacePart['replacement'] : html_entity_decode($replacePart['word'], ENT_NOQUOTES | ENT_HTML5, $enc);
			}
			// fill current combination into placeholder and collect it in array
			$combinations[] = call_user_func_array('sprintf', $sprintfParams);
		}

		return $combinations;
	}

	private function _getCombinationsFromNames($names)
	{
		$combinations = array();
		$firstNameEqFirstNameCombinations = array();

		for ($i = 0; $i < count($names); $i++) // come from front
		{
			for ($j = count($names) - 1; $j >= 0; $j--) // come from behind
			{
				$vorname = $names[$i];

				foreach ($vorname['variations'] as $vnVariation) // try all vorname variations with all nachname variations
				{
					$nachname = $names[$j];
					foreach ($nachname['variations'] as $nnVariation)
					{
						// wildcard to match anything after name, but min 3 chars
						$vnVariationWildcard = mb_strlen($vnVariation) < 3 ? $vnVariation : $vnVariation.'*';
						$nnVariationWildcard = mb_strlen($nnVariation) < 3 ? $nnVariation : $nnVariation.'*';
						$combination = array('vorname' => $vnVariationWildcard, 'nachname' => $nnVariationWildcard);

						// avoid dublicates (can still occur if Vorname and Nachname contain same name)
						$exists = false;
						foreach ($combinations as $existingComb)
						{
							if ($existingComb['vorname'] == $combination['vorname']
								&& $existingComb['nachname'] == $combination['nachname'])
							{
								$exists = true;
								break;
							}
						}

						if ($exists)
							continue;

						if ($j == $i)
						{
							// add combination with vorname = nachname after other combinations
							if ($vnVariation == $nnVariation)
							{
								$firstNameEqFirstNameCombinations[] = $combination;
							}
						}
						else
							$combinations[] = $combination;
					}
				}
			}
		}

		return array_merge($combinations, $firstNameEqFirstNameCombinations);
	}

	public function getNamesForBpkCheck($nameArr)
	{
		$replacements = array(
			'A' => '&Agrave;|&Aacute;|&Acirc;|&Atilde;|&Aring;|&Abreve;|Ǎ',
			'Ae' => '&Auml;',
			'a' => '&agrave;|&aacute;|&acirc;|&atilde;|&aring;|&abreve;|ǎ|&ordm;',
			'ae' => '&auml;',
			'C' => '&Ccedil;|&Ccaron;',
			'c' => '&ccedil;|&ccaron;',
			'E' => '&Egrave;|&Eacute;|&Ecirc;|&Euml;',
			'e' => '&egrave;|&eacute;|&ecirc;|&euml;',
			'I' => '&Igrave;|&Iacute;|&Icirc;|&Iuml;|Ǐ',
			'i' => '&igrave;|&iacute;|&icirc;|&iuml;|ǐ',
			'N' => '&Ntilde;|&Ncaron;|&ncaron;',
			'n' => '&ntilde;',
			'O' => '&Ograve;|&Oacute;|&Ocirc;|&Otilde;|Ǒ',
			'Oe' => '&Ouml;',
			'o' => '&ograve;|&oacute;|&ocirc;|&otilde;|ǒ|&ordf;',
			'oe' => '&ouml;',
			'R' => '&Rcaron;',
			'r' => '&rcaron;',
			'S' => '&Scaron;',
			's' => '&scaron;',
			'T' => '&Tcaron;',
			't' => '&tcaron;',
			'U' => '&Ugrave;|&Uacute;|&Ucirc;|&Ubreve;|Ǔ',
			'Ue' => '&Uuml;',
			'u' => '&ugrave;|&uacute;|&ucirc;|&ubreve;|ǔ',
			'ue' => '&uuml;',
			'Y' => '&Yacute;',
			'y' => '&yacute;|&yuml;',
			'Z' => '&Zcaron;',
			'z' => '&zcaron;',
			'ss' => '&szlig;'/*,*/
/*			'ä' => 'ae',
			'ö' => 'oe', //Umlauts should be handled by DVUH...
			'ü' => 'ue'*/
		);

		$nameVariationsArr = array();

		$separators = array('\s', '-'); // names can consist of multiple names, separated by a character
		$separatorRegex = '/'.implode('|', $separators).'/';

		foreach ($nameArr as $nameIdx => $nameItem)
		{
			// split to check each name part separately
			$partNames = preg_split($separatorRegex, $nameItem, null, PREG_SPLIT_NO_EMPTY);

			foreach ($partNames as $partNameIdx => $partName)
			{
				// name too short
				if (mb_strlen($partName) < 3)
				{
					// append next name part if it exists
					if (isset($partNames[$partNameIdx + 1]))
					{
						foreach ($separators as $separator)
						{
							if ($separator == '\s')
								$separator = ' ';
							$pn = $partName . $separator . $partNames[$partNameIdx + 1];
							$variations = $this->_getVariationsFromName($pn, $replacements);
							$nameVariationsArr[] = array('name' => $pn, 'variations' => $variations);
						}
					}
				}

				// get variations (e.g. with chars in different languages) from a name
				$variations = $this->_getVariationsFromName($partName, $replacements);
				$nameVariationsArr[] = array('name' => $partName, 'variations' => $variations);
			}
		}

		// get all firstname-lastname combinations to test for bpk
		return $this->_getCombinationsFromNames($nameVariationsArr);
	}

	private function _checkBpkResponsesForEquality($responseA, $responseB)
	{
		if (isset($responseA['bpk']) && isset($responseB['bpk']) && $responseA['bpk'] == $responseB['bpk'])
			return true;

		if (isset($responseA['personData']) && isset($responseB['personData']))
		{
			$responseSize = count($responseA['personData']);

			if ($responseSize !== count($responseB['personData']))
				return false;

			for ($i = 0; $i < $responseSize; $i++)
			{
				if ((array) $responseA['personData'][$i] !== (array) $responseB['personData'][$i])
					return false;
			}

			return true;
		}

		return false;
	}
}
