<?php


class DVUHManagementLib
{
	const MATRNR_NAMESPACE = 'http://www.brz.gv.at/datenverbund-unis';

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');
	}

	public function requestMatrikelnummer($person)
	{
		$result = null;

		$queryResult = $this->_ci->MatrikelpruefungModel->get(
			$bpk = null,
			$ekz = $person->ersatzkennzeichen,
			$geburtsdatum = $person->gebdatum,
			$matrikelnummer = null,
			$nachname = $person->nachname,
			$svnr = !isEmptyString($person->svnr) ? $person->svnr : null,
			$vorname = $person->vorname
		);

		//var_dump($queryResult);

		if (hasData($queryResult))
		{
			echo print_r($queryResult, true);
			$parsedObj = $this->_ci->xmlreaderlib->parseXML(getData($queryResult), array('statuscode', 'statusmeldung', 'matrikelnummer'), self::MATRNR_NAMESPACE);

			if (hasData($parsedObj))
			{
				$parsedObj = getData($parsedObj);
				$statuscode = count($parsedObj->statuscode) > 0 ? $parsedObj->statuscode[0] : '';
				$statusmeldung = count($parsedObj->statusmeldung) > 0 ? $parsedObj->statusmeldung[0] : '';
				$matrikelnummer = count($parsedObj->matrikelnummer) > 0 ? $parsedObj->matrikelnummer[0] : '';
				var_dump($statuscode);
				var_dump($matrikelnummer);
				die();

				/**
				 *
				Code 1: Keine Studierendendaten gefunden, neue Matrikelnummer mit matrikelreservierung.xml aus eigenen Kontigent anfordern.

				Code 2: Matrikelnummer gesperrt keine Alternative, BRZ verständigen

				Code 3: Matrikelnummer im Status vergeben gefunden, Matrikelnummer übernehmen

				Code 4: Zur Matrikelnummer liegt eine aktive Meldung im aktuellen Semester vor, Matrikelnummer in Evidenz halten

				Code 5: Zur Matrikelnummer liegt ausschließlich eine Meldung in einen vergangenen Semester vor, es kam daher nie zur Zulassung.Eine neue Matrikelnummer aus dem eigenen Kontigent kann vergeben werden.

				Code 6: Mehr als eine Matrikelnummer wurde gefunden. Der Datenverbund kann keine eindeutige Matrikelnummer feststellen.
				 */

/*				$statuscode_responses = array(
					'2' => 'Matrikelnummer gesperrt keine Alternative, BRZ verständigen',
					'4'=> 'Zur Matrikelnummer liegt eine aktive Meldung im aktuellen Semester vor, Matrikelnummer in Evidenz halten',
					'6'=> 'Mehr als eine Matrikelnummer wurde gefunden. Der Datenverbund kann keine eindeutige Matrikelnummer feststellen.'
				);*/

				$this->_ci->load->model('person/Person_model', 'PersonModel');

				if ($statuscode == '1' || $statuscode == '5') // no existing Matrikelnr - new one must be assigned
				{
					$this->_ci->load->model('organisation/Studienjahr_model', 'StudienjahrModel');

					$studienjahr = $this->_ci->StudienjahrModel->getCurrStudienjahr();

					if (hasData($studienjahr))
					{
						var_dump("RESERVER");
						$sj = getData($studienjahr)[0];

						// reserve new Matrikelnummer
						$be = $this->config->item('fhc_dvuh_be_code');
						//$sj = '2019';
						$anzahl = 1;

						$queryResult = $this->_ci->MatrikelreservierungModel->post($be, $sj, $anzahl);

						if (hasData($queryResult))
						{

						}

						var_dump($queryResult);
					}
					else
					{
						$result = error("Studienjahr not found");
					}
				}
				elseif ($statuscode == '3') // Matrikelnr already existing -> save in FHC
				{
					//var_dump($matrikelnummer);
					if (is_numeric($matrikelnummer))
					{
						$result = $this->_ci->PersonModel->update($person->person_id, array('matr_nr' => $matrikelnummer, 'matr_aktiv' => true));
					}
				}
				else
				{
					if (!isEmptyString($statusmeldung))
						$result = success($statusmeldung); // TODO error or success?
					else
						$result = error("unknown statuscode");
				}
			}
		}
		else
		{
			$result = success('No elements were found for' . $person->person_id);
		}

		return $result;

	}

}