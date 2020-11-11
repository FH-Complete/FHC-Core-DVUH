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
		$this->_url = '/rws/0.5/matrikelpruefung.xml';
	}

	/**
	 * Performs the Webservie Call to search if the person already has a matrikelnumber
	 *
	 * @param $bpk Bereichsspezifisches Personenkennzeichen
	 * @param $ekz Ersatzkennzeichen
	 * @param $geburtsdatum Date of Birth
	 * @param $matrikelnummer Matrikelnummer
	 * @param $nachname Lastname
	 * @param $svnr Social Security Number
	 * @param $vorname First Name
	 */
	public function get($bpk = null, $ekz = null, $geburtsdatum = null, $matrikelnummer = null,
						$nachname = null, $svnr = null, $vorname = null)
	{
		$callParametersArray = array(
			'uuid' => getUUID()
		);

		if (($vorname != '' || $nachname != '') && $geburtsdatum == '')
		{
			$result = error('Wenn der Name angegeben ist muss auch ein Geburtsdatum angegeben werden');
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
			//echo print_r($result,true);
		}

		return $result;
	}
}
