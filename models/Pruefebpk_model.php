<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Request BPK BF of a Student
 */
class Pruefebpk_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/rws/0.5/pruefebpk.xml';
	}

	public function get($vorname, $nachname, $geburtsdatum, $geschlecht = null,
						$strasse = null, $plz = null, $geburtsland = null, $akadgrad = null, $akadnach = null,
						$alternativname = null)
	{
		if (isEmptyString($vorname))
			$result = error('Vorname nicht gesetzt');
		elseif (isEmptyString($nachname))
			$result = error('Nachname nicht gesetzt');
		elseif (isEmptyString($geburtsdatum))
			$result = error('Geburtsdatum nicht gesetzt');
		else
		{
			$callParametersArray = array(
				'uuid' => getUUID(),
				'vorname' => $vorname,
				'nachname' => $nachname,
				'geburtsdatum' => $geburtsdatum
			);

			if (!is_null($geschlecht))
				$callParametersArray['geschlecht'] = $geschlecht;
			if (!is_null($strasse))
				$callParametersArray['strasse'] = $strasse;
			if (!is_null($plz))
				$callParametersArray['plz'] = $plz;
			if (!is_null($geburtsland))
				$callParametersArray['geburtsland'] = $geburtsland;
			if (!is_null($akadgrad))
				$callParametersArray['akadgrad'] = $akadgrad;
			if (!is_null($akadnach))
				$callParametersArray['akadnach'] = $akadnach;
			if (!is_null($alternativname))
				$callParametersArray['$alternativname'] = $alternativname;

			$result = $this->_call('GET', $callParametersArray);
			//echo print_r($result,true);
		}

		return $result;
	}
}
