<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Search for existing Matrikelnr
 */
class Matrikelpruefung_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'matrikelpruefung.xml';

		$this->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');
	}

	/**
	 * Performs the Webservie Call to search if the person already has a matrikelnummer.
	 * @param string $bpk Bereichsspezifisches Personenkennzeichen
	 * @param string $ekz Ersatzkennzeichen
	 * @param string $geburtsdatum Date of Birth
	 * @param string $matrikelnummer Matrikelnummer
	 * @param string $nachname Lastname
	 * @param string $svnr Social Security Number
	 * @param string $vorname First Name
	 * @return object success or error
	 */
	public function get($bpk = null, $ekz = null, $geburtsdatum = null, $matrikelnummer = null,
						$nachname = null, $svnr = null, $vorname = null)
	{
		$callParametersArray = array(
			'uuid' => getUUID()
		);

		if (($vorname != '' || $nachname != '') && $geburtsdatum == '')
		{
			$result = createError(
				'Wenn der Name angegeben ist muss auch ein Geburtsdatum angegeben werden',
				'nameUndGebdatumAngeben'
			);
		}
		elseif (!isEmptyString($ekz) && !$this->dvuhcheckinglib->checkEkz($ekz))
		{
			$result = createError(
				'Ersatzkennzeichen ungültig, muss aus 4 Grossbuchstaben gefolgt von 6 Zahlen bestehen',
				'ersatzkennzeichenUngueltig'
			);
		}
		elseif (!isEmptyString($bpk) && !$this->dvuhcheckinglib->checkBpk($bpk))
		{
			$result = createError(
				'BPK ungültig, muss aus 27 Zeichen (alphanum. mit / +) gefolgt von = bestehen',
				'bpkUngueltig'
			);
		}
		else
		{
			if (!is_null($bpk))
				$callParametersArray['bpk'] = $bpk;
			if (!is_null($ekz))
				$callParametersArray['ekz'] = $ekz;
			if (!is_null($geburtsdatum))
				$callParametersArray['geburtsdatum'] = $geburtsdatum;
			if (!is_null($matrikelnummer))
				$callParametersArray['matrikelnummer'] = $matrikelnummer;
			if (!is_null($nachname))
				$callParametersArray['nachname'] = $nachname;
			if (!is_null($svnr))
				$callParametersArray['svnr'] = $svnr;
			if (!is_null($vorname))
				$callParametersArray['vorname'] = $vorname;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}
}
