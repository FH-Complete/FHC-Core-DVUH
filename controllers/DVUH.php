<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * DVUH Extension Landing Page
 */
class DVUH extends Auth_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index'=>'admin:r',
				'getMatrikelnummer'=>'admin:r',
				'getMatrikelnummerReservierungen'=>'admin:r',
				'getStammdaten'=>'admin:r',
				'getKontostaende'=>'admin:r',
				'getStudium'=>'admin:r',
				'getFullstudent'=>'admin:r',
				'getBpk' =>'admin:r',
				'reserveMatrikelnummer'=>'admin:r',
				'postStammdaten'=>'admin:r',
				'postStudium'=>'admin:r',
				'postZahlung'=>'admin:r',
				'postMatrikelkorrektur'=>'admin:r'
			)
		);

		$this->config->load('extensions/FHC-Core-DVUH/DVUHClient');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	public function index()
	{
		$this->load->library('WidgetLib');
		$this->load->view('extensions/FHC-Core-DVUH/dvuh'/*, array('action' => $action)*/);
	}

	//------------------------------------------------------------------------------------------------------------------
	// GET methods

	public function getMatrikelnummer()
	{
		$data = $this->input->get('data');

		$bpk = isset($data['bpk']) ? $data['bpk'] : null;
		$ekz = isset($data['ekz']) ? $data['ekz'] : null;
		$geburtsdatum = isset($data['geburtsdatum']) ? $data['geburtsdatum'] : null;
		$matrikelnummer = null;
		$nachname = isset($data['nachname']) ? $data['nachname'] : null;
		$vorname = isset($data['vorname']) ? $data['vorname'] : null;
		$svnr = isset($data['svnr']) ? $data['svnr'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');

		$queryResult = $this->MatrikelpruefungModel->get(
			$bpk, $ekz, $geburtsdatum, $matrikelnummer,
			$nachname, $svnr, $vorname
		);

		$this->outputJson($queryResult);
	}

	public function getMatrikelnummerReservierungen()
	{
		$data = $this->input->get('data');

		$studienjahr = isset($data['studienjahr']) ? $data['studienjahr'] : null; // TODO studienjahr abfangen?
		$be = $this->config->item('fhc_dvuh_be_code');

		$this->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');

		$queryResult = $this->MatrikelreservierungModel->get(
			$be, $studienjahr
		);

		$this->outputJson($queryResult);
	}

	public function getStammdaten()
	{
		$data = $this->input->get('data');

		$be = $this->config->item('fhc_dvuh_be_code');
		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');

		$queryResult = $this->StammdatenModel->get(
			$be, $matrikelnummer, $semester
		);

		$this->outputJson($queryResult);
	}

	public function getKontostaende()
	{
		$json = null;

		$data = $this->input->get('data');

		$be = $this->config->item('fhc_dvuh_be_code');
		$seit = isset($data['seit']) ? $data['seit'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Kontostaende_model', 'KontostaendeModel');

		$json = $this->KontostaendeModel->get(
			$be, $data['semester'], $data['matrikelnummer'], $seit
		);

		$this->outputJson($json);
	}

	public function getStudium()
	{
		$json = null;

		$data = $this->input->get('data');

		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = isset($data['semester']) ? $data['semester'] : null;
		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;
		$studienkennung = isset($data['studienkennung']) ? $data['studienkennung'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Studium_model', 'StudiumModel');

		$json = $this->StudiumModel->get(
			$be, $matrikelnummer, $semester, $studienkennung
		);

		$this->outputJson($json);
	}

	public function getFullstudent()
	{
		$json = null;

		$data = $this->input->get('data');

		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = isset($data['semester']) ? $data['semester'] : null;
		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Fullstudent_model', 'FullstudentModel');

		$json = $this->FullstudentModel->get(
			$matrikelnummer, $be, $semester
		);

		$this->outputJson($json);
	}

	public function getBpk()
	{
		$json = null;

		$data = $this->input->get('data');

		$vorname = isset($data['vorname']) ? $data['vorname'] : null;
		$nachname = isset($data['nachname']) ? $data['nachname'] : null;
		$geburtsdatum = isset($data['geburtsdatum']) ? $data['geburtsdatum'] : null;
		$geschlecht = isset($data['geschlecht']) ? $data['geschlecht'] : null;
		$strasse = isset($data['strasse']) ? $data['strasse'] : null;
		$plz = isset($data['plz']) ? $data['plz'] : null;
		$geburtsland = isset($data['geburtsland']) ? $data['geburtsland'] : null;
		$akadgrad = isset($data['akadgrad']) ? $data['akadgrad'] : null;
		$akadnach = isset($data['akadnach']) ? $data['akadnach'] : null;
		$alternativname = isset($data['alternativname']) ? $data['alternativname'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Pruefebpk_model', 'PruefebpkModel');

		$json = $this->PruefebpkModel->get(
			$vorname, $nachname, $geburtsdatum, $geschlecht,
			$strasse, $plz, $geburtsland, $akadgrad, $akadnach,
			$alternativname
		);

		$this->outputJson($json);
	}

	//------------------------------------------------------------------------------------------------------------------
	// POST methods

	public function reserveMatrikelnummer()
	{
		$data = $this->input->post('data');

		$studienjahr = isset($data['studienjahr']) ? $data['studienjahr'] : null;
		$be = $this->config->item('fhc_dvuh_be_code');

		$this->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');

		$queryResult = $this->MatrikelreservierungModel->post(
			$be, $studienjahr, 1
		);

		$this->outputJson($queryResult);
	}

	public function postStammdaten()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$be = $this->config->item('fhc_dvuh_be_code');
		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;
		$oehbeitrag = isset($data['oehbeitrag']) ? $data['oehbeitrag'] : null;
		$studiengebuehr = isset($data['studiengebuehr']) ? $data['studiengebuehr'] : null;
		$studiengebuehrnachfrist = isset($data['studiengebuehrnachfrist']) ? $data['studiengebuehrnachfrist'] : null;

		// valutadatum?? Buchungsdatum + Mahnspanne
		$valutadatum = isset($data['valutadatum']) ? $data['valutadatum'] : null;
		$valutadatumnachfrist = isset($data['valutadatumnachfrist']) ? $data['valutadatumnachfrist'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');

		$json = $this->StammdatenModel->post(
			$be, $person_id, $semester, $oehbeitrag, $studiengebuehr, $valutadatum, $valutadatumnachfrist, $studiengebuehrnachfrist, $preview
		);

		$this->outputJson($json);
	}

	public function postZahlung()
	{
		$json = null;

		$data = $this->input->post('data');

		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;
		$zahlungsart = isset($data['zahlungsart']) ? $data['zahlungsart'] : null;
		$centbetrag = isset($data['centbetrag']) ? $data['centbetrag'] : null;
		$buchungsdatum = isset($data['buchungsdatum']) ? $data['buchungsdatum'] : null;
		$referenznummer = isset($data['referenznummer']) ? $data['referenznummer'] : null;

		$be = $this->config->item('fhc_dvuh_be_code');

		$this->load->model('extensions/FHC-Core-DVUH/Zahlung_model', 'ZahlungModel');

		$json = $this->ZahlungModel->post(
			$be, $matrikelnummer, $semester, $zahlungsart, $centbetrag,
			$buchungsdatum, $referenznummer
		);

		$this->outputJson($json);
	}

	public function postStudium()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$be = $this->config->item('fhc_dvuh_be_code');
		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Studium_model', 'StudiumModel');

		$json = $this->StudiumModel->post(
			$be, $person_id, $semester, null, $preview
		);

		$this->outputJson($json);
	}

	public function postMatrikelkorrektur()
	{
		$json = null;

		$data = $this->input->post('data');

		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;
		$matrikelalt = isset($data['matrikelalt']) ? $data['matrikelalt'] : null;

		$be = $this->config->item('fhc_dvuh_be_code');

		$this->load->model('extensions/FHC-Core-DVUH/Matrikelkorrektur_model', 'MatrikelkorrekturModel');

		$json = $this->MatrikelkorrekturModel->post(
			$be, $matrikelnummer, $semester, $matrikelalt
		);

		$this->outputJson($json);
	}
}
