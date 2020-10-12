<?php


class DVUHManagementLib
{
	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
	}

	public function managePersonMatrikelnummer($person)
	{

		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');

		// TODO what to search for?
		$queryResult = $this->_ci->MatrikelpruefungModel->get(
			$bpk = null,
			$ekz = $person->ersatzkennzeichen,
			$geburtsdatum = $person->gebdatum,
			$matrikelnummer = null,
			$nachname = $person->nachname,
			$svnr = $person->svnr,
			$vorname = $person->vorname
		);

		//var_dump($queryResult);

		if (hasData($queryResult))
		{
			//echo print_r($queryResult, true);
			$parsedObj = $this->_ci->xmlreaderlib->parseXML(getData($queryResult), array('statuscode', 'matrikelnummer'));
			var_dump($parsedObj);

			if (hasData($parsedObj))
			{
				$statuscodes = getData($parsedObj)->statuscode;

				var_dump($statuscodes);
				die();

				/**
				 *
				 * Code 1: Keine Studierendendaten gefunden, neue Matrikelnummer mit matrikelreservierung.xml aus eigenen Kontigent anfordern.
				 *
				 * Code 2: Matrikelnummer gesperrt keine Alternative, BRZ verständigen
				 *
				 * Code 3: Matrikelnummer im Status vergeben gefunden, Matrikelnummer übernehmen
				 *
				 * Code 4: Zur Matrikelnummer liegt eine aktive Meldung im aktuellen Semester vor, Matrikelnummer in Evidenz halten
				 *
				 * Code 5: Zur Matrikelnummer liegt ausschließlich eine Meldung in einen vergangenen Semester vor, es kam daher nie zur Zulassung.Eine neue Matrikelnummer aus dem eigenen Kontigent kann vergeben werden.
				 *
				 * Code 6: Mehr als eine Matrikelnummer wurde gefunden. Der Datenverbund kann keine eindeutige Matrikelnummer feststellen.
				 */

				if ($statuscode == '1')
				{
					$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');

					$studienjahr = $this->_ci->StudienjahrModel->getCurrStudienjahr();

					if (hasData($studienjahr))
					{
						$sj = getData($studienjahr)[0];


						// reserve new Matrikelnummer
						$be = $this->config->item('fhc_dvuh_be_code');
						//$sj = '2019';
						$anzahl = 1;

						$queryResult = $this->MatrikelreservierungModel->post($be, $sj, $anzahl);
					}
					else
					{

					}
				}
				elseif ($statuscode == '3')
				{

				}
			}
		}
		else
		{
			$this->logInfo('No elements were found for' . $person->person_id);
		}

	}

}