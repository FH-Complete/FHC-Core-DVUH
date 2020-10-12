<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example JOB
 */
class Matrikelmanagement extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();
		//$this->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Example method
	 */
	public function manageMatrikelnummer()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');
		$this->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');
		//$this->load->model('person/Person_model', 'PersonModel');

		$this->logInfo('Matrikelmanagement job start');

		$person_ids = array('65378'); // future students which already paid Kaution and RT absolviert

		$qry = "
				SELECT person_id, svnr, vorname, nachname, gebdatum, ersatzkennzeichen
				FROM public.tbl_person
				WHERE person_id IN ?
		";

		$dbModel = new DB_Model();

		$persons = $dbModel->execReadOnlyQuery($qry, array('person_id' => $person_ids));

		if (hasData($persons))
		{
			$persons = getData($persons);

			foreach ($persons as $person)
			{
			/*	$queryResult = $this->MatrikelpruefungModel->get(
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
					$parsedObj = $this->xmlreaderlib->parseXML(getData($queryResult), array('statuscode', 'matrikelnummer'));
					var_dump($parsedObj);
				}
				else
				{
					$this->logInfo('No elements were found for' . $person->person_id);
				}*/
			}
		}

		$this->logInfo('Matrikelmanagement job stop');
	}
}
