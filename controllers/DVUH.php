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
				'getPersonPrefillData'=>'admin:r',
				'getMatrikelnummerReservierungen'=>'admin:r',
				'getStammdaten'=>'admin:r',
				'getKontostaende'=>'admin:r',
				'getStudium'=>'admin:r',
				'getFullstudent'=>'admin:r',
				'getBpk' =>'admin:r',
				'getBpkByPersonId' =>'admin:r',
				'getPruefungsaktivitaeten' =>'admin:r',
				'getDvuhMenuData' =>'admin:r',
				'reserveMatrikelnummer'=>'admin:rw',
				'postMasterData'=>'admin:rw',
				'postCharge'=>'admin:rw',
				'postStudium'=>'admin:rw',
				'postPayment'=>'admin:rw',
				'postMatrikelkorrektur'=>'admin:rw',
				'postErnpmeldung'=>'admin:rw',
				'postPruefungsaktivitaeten'=>'admin:rw',
				'postEkzanfordern'=>'admin:rw',
				'postStudiumStorno'=>'admin:rw',
				'deletePruefungsaktivitaeten'=>'admin:rw'
			)
		);

		$this->load->library('extensions/FHC-Core-DVUH/DVUHManagementLib');

		$this->config->load('extensions/FHC-Core-DVUH/DVUHClient');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	public function index()
	{
		$this->load->library('WidgetLib');

		// display system path (e.g. rws or sandbox)
		$environment = $this->config->item(DVUHClientLib::URL_PATH);
		$apiVersion = $this->config->item(DVUHClientLib::API_VERSION);

		$this->load->view('extensions/FHC-Core-DVUH/dvuh', array('environment' => $environment, 'apiVersion' => $apiVersion));
	}

	//------------------------------------------------------------------------------------------------------------------
	// GET methods

	public function getMatrikelnummer()
	{
		$data = $this->input->get('data');

		$bpk = isset($data['bpk']) ? $data['bpk'] : null;
		$ekz = isset($data['ekz']) ? $data['ekz'] : null;
		$geburtsdatum = isset($data['geburtsdatum']) ? convertDateToIso($data['geburtsdatum']) : null;
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

	/**
	 * Gets person data for input field prefills.
	 */
	public function getPersonPrefillData()
	{
		$person_id = $this->input->get('person_id');

		$this->outputJson($this->bpkmanagementlib->getPersonDataForBpkCheck($person_id));
	}

	public function getMatrikelnummerReservierungen()
	{
		$data = $this->input->get('data');

		$studienjahr = isset($data['studienjahr']) ? $data['studienjahr'] : null;
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
		$seit = isset($data['seit']) ? convertDateToIso($data['seit']) : null;

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
		$geburtsdatum = isset($data['geburtsdatum']) ? convertDateToIso($data['geburtsdatum']) : null;
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

	public function getBpkByPersonId()
	{
		$json = null;

		$data = $this->input->get('data');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Pruefebpk_model', 'PruefebpkModel');

		$json = $this->PruefebpkModel->getByPersonId($person_id);

		$this->outputJson($json);
	}

	public function getPruefungsaktivitaeten()
	{
		$json = null;

		$data = $this->input->get('data');

		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = isset($data['semester']) ? $data['semester'] : null;
		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Pruefungsaktivitaeten_model', 'PruefungsaktivitaetenModel');

		$json = $this->PruefungsaktivitaetenModel->get(
			$be, $semester, $matrikelnummer
		);

		$this->outputJson($json);
	}

	public function getDvuhMenuData()
	{
		$menuData = array(
			'nations' => array()
		);

		$language = getUserLanguage();

		$nationTextFieldName = $language == 'German' ? 'langtext' : 'engltext';

		$this->load->model('codex/Nation_model', 'NationModel');

		$this->NationModel->addSelect("nation_code, $nationTextFieldName AS nation_text");
		$this->NationModel->addOrder("nation_text");
		$nationRes = $this->NationModel->load();

		if (isError($nationRes))
		{
			$this->outputJsonError(getError($nationRes));
			exit;
		}

		$this->load->model('organisation/Studiengang_model', 'StudiengangModel');

		$stgTextFieldName = $language == 'German' ? 'bezeichnung' : 'english';

		$this->StudiengangModel->addSelect("studiengang_kz, $stgTextFieldName AS studiengang_text");
		$this->StudiengangModel->addOrder('studiengang_kz');
		$stgRes = $this->StudiengangModel->loadWhere(
			array(
				'aktiv' => true,
				'melderelevant' => true
			)
		);

		if (isError($stgRes))
		{
			$this->outputJsonError(getError($stgRes));
			exit;
		}

		$menuData['nations'] = getData($nationRes);
		$menuData['stg'] = getData($stgRes);

		$this->outputJsonSuccess($menuData);
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

	public function postMasterData()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$json = $this->dvuhmanagementlib->sendMasterdata($person_id, $semester, null, $preview);

		$this->outputJson($json);
	}

	public function postPayment()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$json = $this->dvuhmanagementlib->sendPayment($person_id, $semester, $preview);

		$this->outputJson($json);
	}

	public function postStudium()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$prestudent_id = isset($data['prestudent_id']) ? $data['prestudent_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$json = $this->dvuhmanagementlib->sendStudyData($semester, $person_id, $prestudent_id,  $preview);

		$this->outputJson($json);
	}

	public function postErnpmeldung()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$writeonerror = isset($data['writeonerror']) ? $data['writeonerror'] : null;
		$ausgabedatum = isset($data['ausgabedatum']) ? convertDateToIso($data['ausgabedatum']) : null;
		$ausstellBehoerde = isset($data['ausstellBehoerde']) ? $data['ausstellBehoerde'] : null;
		$ausstellland = isset($data['ausstellland']) ? $data['ausstellland'] : null;
		$dokumentnr = isset($data['dokumentnr']) ? $data['dokumentnr'] : null;
		$dokumenttyp = isset($data['dokumenttyp']) ? $data['dokumenttyp'] : null;

		$json = $this->dvuhmanagementlib->sendMatrikelErnpMeldung($person_id, $writeonerror, $ausgabedatum,
			$ausstellBehoerde, $ausstellland, $dokumentnr, $dokumenttyp, $preview);

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

	public function postPruefungsaktivitaeten()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$json = $this->dvuhmanagementlib->sendPruefungsaktivitaeten($person_id, $semester, $preview);

		$this->outputJson($json);
	}

	public function postEkzanfordern()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$forcierungskey = isset($data['forcierungskey']) ? $data['forcierungskey'] : null;

		$json = $this->dvuhmanagementlib->requestEkz($person_id, $forcierungskey, $preview);

		$this->outputJson($json);
	}

	public function postStudiumStorno()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;
		$studiengang_kz = isset($data['studiengang_kz']) ? $data['studiengang_kz'] : null;

		$json = $this->dvuhmanagementlib->cancelStudyData($matrikelnummer, $semester, $studiengang_kz, $preview);

		$this->outputJson($json);
	}

	public function deletePruefungsaktivitaeten()
	{
		$json = null;

		$data = $this->input->post('data');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$prestudent_id = isset($data['prestudent_id']) ? $data['prestudent_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$json = $this->dvuhmanagementlib->deletePruefungsaktivitaeten($person_id, $semester, $prestudent_id);

		$this->outputJson($json);
	}
}
