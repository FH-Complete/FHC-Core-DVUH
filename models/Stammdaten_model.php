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
		if (isEmptyString($matrikelnummer))
			$result = error('Matrikelnummer not set');
		else
		{
			$callParametersArray = array(
				'be' => $be,
				'matrikelnummer' => $matrikelnummer,
				'uuid' => getUUID()
			);

			if (!is_null($semester))
				$callParametersArray['semester'] = $semester;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}

	public function post($be, $person_id, $semester, $oehbeitrag, $studiengebuehr, $valutadatum, $valutadatumnachfrist)
	{
		if (isEmptyString($person_id))
			$result = error('personID not set');
		elseif (isEmptyString($semester))
			$result = error('Semester not set');
		elseif (isEmptyString($oehbeitrag))
			$result = error('ÖH-Beitrag not set');
		elseif (isEmptyString($studiengebuehr))
			$result = error('Studiengebührt not set');
		elseif (isEmptyString($valutadatum))
			$result = error('Valudadatum not set');
		elseif (isEmptyString($valutadatumnachfrist))
			$result = error('Valudadatumnachfrist not set');
		else
		{
			$this->load->model('person/person_model', 'PersonModel');
			$this->load->model('person/benutzer_model', 'BenutzerModel');

			$stammdaten = $this->PersonModel->getPersonStammdaten($person_id);

			//var_dump($stammdaten);

			if (hasData($stammdaten))
			{
				$stammdaten = getData($stammdaten);

				$adressen = array();
				$emailliste = array();

				// adresses
				foreach ($stammdaten->adressen as $adresse)
				{
					$addr = array();
					$addr['ort'] = $adresse->ort;
					$addr['plz'] = $adresse->plz;
					$addr['strasse'] = $adresse->strasse;
					$addr['staat'] = $adresse->nation;
					$addr['typ'] = $adresse->heimatadresse === true ? 'H' : 'S'; // TODO if only Heimatadresse, also automatically Zustelladresse?
					$adressen[] = $addr;
				}

				// private mail
				foreach ($stammdaten->kontakte as $kontakt)
				{
					if ($kontakt->kontakttyp == 'email')
					{
						$knt = array();
						$knt['emailadresse'] = $kontakt->kontakt;
						$knt['emailtyp'] = 'PR';
						$emailliste[] = $knt;
					}
				}

				// business mail
				$this->BenutzerModel->addSelect('uid');
				$uids = $this->BenutzerModel->loadWhere(array('person_id' => $person_id));

				if (hasData($uids))
				{
					$uids = getData($uids);

					foreach ($uids as $uid)
					{
						$bsmail = array();
						$bsmail['emailadresse'] = $uid->uid . '@' . DOMAIN;
						$bsmail['emailtyp'] = 'BE';
						$emailliste[] = $bsmail;
					}
				}
			}

			$geschlecht = 'X';

			if ($stammdaten->geschlecht == 'm')
				$geschlecht = 'M';
			elseif ($stammdaten->geschlecht == 'w')
				$geschlecht = 'W';

			$params = array(
				"uuid" => getUUID(),
				"studierendenkey" => array(
					"matrikelnummer" => $stammdaten->matr_nr,
					"be" => $be,
					"semester" => $semester
				),
				'adressen' => $adressen,
				'akadgrad' => $stammdaten->titelpre,
				'akadgradnach' => $stammdaten->titelpost,
				'beitragsstatus' => 'X', // TODO: X gilt nur für FHs, Bei Uni anders
				//'bpk' => '1234',
				//'ekz' => 'ez1234',
				'emailliste' => $emailliste,
				'geburtsdatum' => $stammdaten->gebdatum,
				'geschlecht' => $geschlecht,
				'nachname' => $stammdaten->nachname,
				'staatsbuergerschaft' => $stammdaten->staatsbuergerschaft_code,
				//'svnr' => '12345',
				'vorname' => $stammdaten->vorname,

				'oehbeitrag' => $oehbeitrag, // IN CENT!!
				'sonderbeitrag' => '0',
				'studienbeitrag' => '0', // Bei FH immer 0, CENT !!
				'studienbeitragnachfrist' => '0', // Bei FH immer 0, CENT!!
				'studiengebuehr' => $studiengebuehr, // FH Studiengebuehr in CENT!!!
				'studiengebuehrnachfrist' => $studiengebuehr, //  in CENT!!!
				'valutadatum' => $valutadatum,
				'valutadatumnachfrist' => $valutadatumnachfrist
			);

			/*		$adressen = array(
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
							"matrikelnummer" => '51832997',
							"be" => 'FT',
							"semester" => '2020W'
						),
						'adressen' => $adressen,
						'akadgrad' => 'Ing.',
						'akadgradnach' => 'BSc',
						'beitragsstatus' => 'X', // TODO: X gilt nur für FHs, Bei Uni anders
						//'bpk' => '1234',
						//'ekz' => 'ez1234',
						'emailliste' => $emailliste,
						'geburtsdatum' => '1997-07-19',
						'geschlecht' => 'W',
						'nachname' => 'Bornberg',
						'staatsbuergerschaft' => 'A',
						//'svnr' => '12345',
						'vorname' => 'Christina',

						'oehbeitrag' => '0', // IN CENT!!
						'sonderbeitrag' => '0',
						'studienbeitrag' => '0', // Bei FH immer 0, CENT !!
						'studienbeitragnachfrist' => '0', // Bei FH immer 0, CENT!!
						'studiengebuehr' => '0', // FH Studiengebuehr in CENT!!!
						'studiengebuehrnachfrist' => '3600', //  in CENT!!!
						'valutadatum' => '2020-09-01',
						'valutadatumnachfrist' => '2020-11-30'
					);*/
			$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/stammdaten', $params, true);

			//var_dump($postData);

			$result = $this->_call('POST', null, $postData);
		}
		//echo print_r($result, true);
		return $result;

	}
}
