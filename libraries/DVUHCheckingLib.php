<?php

/**
 * Library for checking validity of data before reporting to DVUH.
 */
class DVUHCheckingLib
{
	private $_ci;

	const DVUH_STGKZ_LENGTH = 4;
	const DVUH_ERHALTER_LENGTH = 3;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Checks an adress for validity.
	 * @param object $addr
	 * @return error or success with true/false (valid or not)
	 */
	public function checkAdresse($addr)
	{
		$result = success(true);

		$errorText = '';

		if (!isset($addr['ort']) || isEmptyString($addr['ort']) || !validateXmlTextValue($addr['ort']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Ort (Feld Gemeinde) fehlt oder enthält Sonderzeichen';

		if (!isset($addr['plz']) || isEmptyString($addr['plz']) || !validateXmlTextValue($addr['plz']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Plz fehlt oder enthält Sonderzeichen';

		if (!isset($addr['strasse']) || isEmptyString($addr['strasse']) || !validateXmlTextValue($addr['strasse']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Strasse fehlt oder enthält Sonderzeichen';

		if (!isset($addr['staat']) || isEmptyString($addr['staat']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Nation fehlt';

		if (!isEmptyString($errorText))
			$result = error($errorText);

		return $result;
	}

	/**
	 * Checks Matrikelnummer for validity.
	 * @param string $svnr
	 * @return bool valid or not
	 */
	public function checkMatrikelnummer($svnr)
	{
		return preg_match("/^\d{8}$/", $svnr) === 1;
	}

	/**
	 * Checks Ersatzkennzeichen for validity.
	 * @param string $ekz
	 * @return bool valid or not
	 */
	public function checkEkz($ekz)
	{
		return preg_match('/^[A-Z]{4}[0-9]{6}$/', $ekz) === 1;
	}

	/**
	 * Checks Bpk for validity.
	 * @param string $bpk
	 * @return bool valid or not
	 */
	public function checkBpk($bpk)
	{
		return preg_match("/^([A-Za-z0-9+\/]{27})=$/", $bpk) === 1;
	}

	/**
	 * Checks Bpk for validity.
	 * @param string $bpk
	 * @return bool valid or not
	 */
	public function checkPersonenkennzeichen($perskz)
	{
		return preg_match("/^\d{10}$/", $perskz);
	}

	/**
	 * Checks academic title for validity.
	 * @param string $titel
	 * @return bool valid or not
	 */
	public function checkTitel($titel)
	{
		return preg_match("/^[A-Za-z.\-\/\,'´`\(\) À-ž&]{0,255}$/", $titel);
	}

	/**
	 * Checks Oehbeitrag in cents for validity.
	 * @param int $oehBeitrag
	 * @return bool valid or not
	 */
	public function checkOehBeitrag($oehBeitrag)
	{
		return is_numeric($oehBeitrag) && $oehBeitrag <= 9999 && $oehBeitrag >= 0;
	}

	/**
	 * Checks Studiengebühr in cents for validity.
	 * @param int $studiengebuehr
	 * @return bool valid or not
	 */
	public function checkStudiengebuehr($studiengebuehr)
	{
		return is_numeric($studiengebuehr) && $studiengebuehr <= 9999999 && $studiengebuehr >= 0;
	}

	/**
	 * Checks if a student is ausserordentlich.
	 * @param string $personenkennzeichen
	 * @return bool true if ausserordentlich, false otherwise
	 */
	public function checkIfAusserordentlich($personenkennzeichen)
	{
		$ausserordentlich_prefix = $this->_ci->config->item('fhc_dvuh_sync_ausserordentlich_prefix');
		return mb_substr($personenkennzeichen, 3, 1) == $ausserordentlich_prefix;
	}

	/**
	 * Checks if studiengang kz is valid to report.
	 * @param int $melde_studiengang_kz
	 * @return bool true if valid, false otherwise
	 */
	public function checkStudiengangkz($melde_studiengang_kz)
	{
		$stgkzValid = is_numeric($melde_studiengang_kz);

		// length must be erhalter kz length + studiengang kz length
		if (mb_strlen((string)$melde_studiengang_kz) !== self::DVUH_ERHALTER_LENGTH + self::DVUH_STGKZ_LENGTH)
		{
			$stgkzValid = false;
		}

		return $stgkzValid;
	}

	/**
	 * Checks studienkennunguni for validity.
	 * @param string $studienkennunguni
	 * @return bool valid or not
	 */
	public function checkStudienkennunguni($studienkennunguni)
	{
		return preg_match("/^[AFHLU][UPF][A-Z]([0-9]{3}){1,3}(0[1-6]|[UP][A-W]){0,1}$/", $studienkennunguni) === 1;
	}
}
