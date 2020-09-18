<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Read and save Student Data
 */
class Stammdaten_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = '/rws/0.5/stammdaten.xml';
	}

	/**
	 * Performs the Webservie Call
	 *
	 * @param $be Code of the Bildungseinrichtung
	 * @param $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param $semester Studysemester in format 2019W (optional)
	 */
	public function get($be, $matrikelnummer, $semester = null)
	{
		$callParametersArray = array(
			'be' => $be,
			'matrikelnummer' => $matrikelnummer,
			'uuid' => getUUID()
		);

		if (!is_null($semester))
			$callParametersArray['semester'] = $semester;

		$result = $this->_call('GET', $callParametersArray);
		echo print_r($result,true);
		// TODO Parse Result, Handle Errors
	}

	public function post()
	{
		/*
		$adressen = array(
			array(
				'coname' => $coname, // optional
				'ort' => $ort,
				'plz' => $plz,
				'strasse' => $strasse,
				'typ' => $typ // H = Heimatadresse, S = Studienadresse/Zustelladresse
			)
		);

		$emailliste = array(
			array(
				'emailadresse' => $mail,
				'emailtyp' => $mailtyp // BE | PR
			)
		);
		$params = array(
			"uuid" => getUUID(),
			"studierendenkey" => array(
				"matrikelnummer" => $matrikelnummer,
				"be" => $be,
				"semester" => $semester
			),
			'adressen' => $adressen,
			'akadgrad' => $titelpre,
			'akadgradnach' => $titelpost,
			'beitragsstatus' => 'X', // TODO: X gilt nur für FHs, Bei Uni anders
			'bpk' => $bpk,
			'ekz' => $ersatzkennzeichen,
			'emailliste' => $emailliste,
			'geburtsdatum' => $gebdatum,
			'geschlecht' => $geschlecht, // M, W, X
			'nachname' => $nachname,
			'staatsbuergerschaft' => $staatsbuergerschaft,
			'svnr' => $svnr,
			'vorname' => $vorname,

			'oehbeitrag' => $oehbeitrag, // IN CENT!!
			'sonderbeitrag' => $sonderbeitrag,
			'studienbeitrag' => $studienbeitrag, // Bei FH immer 0, CENT !!
			'studienbeitragnachfrist' => $studienbeitragnachfrist, // Bei FH immer 0, CENT!!
			'studiengebuehr' => $studiengebuehr, // FH Studiengebuehr in CENT!!!
			'studiengebuehrnachfrist' => $studiengebuehrnachfirst, //  in CENT!!!
			'valutadatum' => $valutadatum,
			'valutadatumnachfrist' => $valutadatumnachfrist
		);
		*/


		$adressen = array(
			array(
			//	'coname' => 'Karl Lagerfeld', // optional
				'ort' => 'Wien',
				'plz' => '1100',
				'strasse' => 'Rathausplatz 1',
				'staat' => 'A',
				'typ' => 'H' // H = Heimatadresse, S = Studienadresse/Zustelladresse
			),
			array(
			//	'coname' => 'Karl Lagerfeld', // optional
				'ort' => 'Wien',
				'plz' => '1100',
				'strasse' => 'Rathausplatz 1',
				'staat' => 'A',
				'typ' => 'S' // H = Heimatadresse, S = Studienadresse/Zustelladresse
			)
		);

		$emailliste = array(
			array(
				'emailadresse' => 'invalid@technikum-wien.at',
				'emailtyp' => 'BE' // BE | PR
			)
		);
		$params = array(
			"uuid" => getUUID(),
			"studierendenkey" => array(
				"matrikelnummer" => '520012345',
				"be" => 'FT',
				"semester" => '2020S'
			),
			'adressen' => $adressen,
			'akadgrad' => 'Ing.',
			'akadgradnach' => 'BSc',
			'beitragsstatus' => 'X', // TODO: X gilt nur für FHs, Bei Uni anders
			//'bpk' => '1234',
			//'ekz' => 'ez1234',
			'emailliste' => $emailliste,
			'geburtsdatum' => '1984-04-26',
			'geschlecht' => 'M',
			'nachname' => 'TEST',
			'staatsbuergerschaft' => 'A',
			//'svnr' => '12345',
			'vorname' => 'Karl',

			'oehbeitrag' => '1920', // IN CENT!!
			'sonderbeitrag' => '0',
			'studienbeitrag' => '0', // Bei FH immer 0, CENT !!
			'studienbeitragnachfrist' => '0', // Bei FH immer 0, CENT!!
			'studiengebuehr' => '36336', // FH Studiengebuehr in CENT!!!
			'studiengebuehrnachfrist' => '36336', //  in CENT!!!
			'valutadatum' => '2020-09-01',
			'valutadatumnachfrist' => '2020-11-30'
		);
		$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/stammdaten', $params, true);
		echo $postData;

		$result = $this->_call('POST', null, $postData);
		echo print_r($result, true);
		return $result;

	}
}
