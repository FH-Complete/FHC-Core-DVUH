<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * DVUH Extension Landing Page
 */
class DVUH extends Auth_Controller
{
	const PERMISSION_TYPE_SEPARATOR = ':';

	private $_permissions = array(
		'index'=> array('admin:r', 'extension/dvuh_gui_ekz_anfordern:r'),
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
		'getDvuhMenuData' => array('admin:r', 'extension/dvuh_gui_ekz_anfordern:r'),
		'getPermittedActions' => array('admin:r', 'extension/dvuh_gui_ekz_anfordern:r'),
		'reserveMatrikelnummer'=>'admin:rw',
		'postMasterData'=>'admin:r',
		'postCharge'=>'admin:rw',
		'postStudium'=>'admin:rw',
		'postPayment'=>'admin:rw',
		'postMatrikelkorrektur'=>'admin:rw',
		'postErnpmeldung'=> 'admin:rw',
		'postPruefungsaktivitaeten'=>'admin:rw',
		'postEkzanfordern'=> array('admin:rw', 'extension/dvuh_gui_ekz_anfordern:rw'),
		'postStudiumStorno'=>'admin:rw',
		'deletePruefungsaktivitaeten'=>'admin:r'
	);

	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(
			$this->_permissions
		);

		$this->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->load->library('extensions/FHC-Core-DVUH/DVUHIssueLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHMatrikelnummerManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHMasterDataManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHPaymentManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHStudyDataManagementLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHPruefungsaktivitaetenManagementLib');

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
		$semester = isset($data['semester']) ? $this->dvuhconversionlib->convertSemestertoDVUH($data['semester']) : null;

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
		$semester = isset($data['semester']) ? $this->dvuhconversionlib->convertSemestertoDVUH($data['semester']) : null;

		$this->load->model('extensions/FHC-Core-DVUH/Kontostaende_model', 'KontostaendeModel');

		$json = $this->KontostaendeModel->get(
			$be, $semester, $data['matrikelnummer'], $seit
		);

		$this->outputJson($json);
	}

	public function getStudium()
	{
		$json = null;

		$data = $this->input->get('data');

		$be = $this->config->item('fhc_dvuh_be_code');
		$semester = isset($data['semester']) ? $this->dvuhconversionlib->convertSemestertoDVUH($data['semester']) : null;
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
		$semester = isset($data['semester']) ? $this->dvuhconversionlib->convertSemestertoDVUH($data['semester']) : null;
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
		$semester = isset($data['semester']) ? $this->dvuhconversionlib->convertSemestertoDVUH($data['semester']) : null;
		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;

		$this->load->model('extensions/FHC-Core-DVUH/Pruefungsaktivitaeten_model', 'PruefungsaktivitaetenModel');

		$json = $this->PruefungsaktivitaetenModel->get(
			$be, $semester, $matrikelnummer
		);

		$this->outputJson($json);
	}

	/**
	 * Gets data needed for rendering of the GUI menu.
	 */
	public function getDvuhMenuData()
	{
		$menuData = array(
			'permittedMethods' => array()
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
			return;
		}

		if (hasData($nationRes))
			$menuData['nations'] = getData($nationRes);

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

		$json = $this->dvuhmasterdatamanagementlib->sendMasterdata($person_id, $semester, null, $preview);

		$this->_outputJsonDVUH($json);
	}

	public function postPayment()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$json = $this->dvuhpaymentmanagementlib->sendPayment($person_id, $semester, $preview);

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

		$json = $this->dvuhstudydatamanagementlib->sendStudyData($semester, $person_id, $prestudent_id,  $preview);

		$this->_outputJsonDVUH($json);
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

		$json = $this->dvuhmasterdatamanagementlib->sendMatrikelErnpMeldung($person_id, $writeonerror, $ausgabedatum,
			$ausstellBehoerde, $ausstellland, $dokumentnr, $dokumenttyp, $preview);

		$this->outputJson($json);
	}

	public function postMatrikelkorrektur()
	{
		$json = null;

		$data = $this->input->post('data');

		$matrikelnummer = isset($data['matrikelnummer']) ? $data['matrikelnummer'] : null;
		$semester = isset($data['semester']) ? $this->dvuhconversionlib->convertSemestertoDvuh($data['semester']) : null;
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

		$json = $this->dvuhpruefungsaktivitaetenmanagementlib->sendPruefungsaktivitaeten($person_id, $semester, $preview);

		$this->outputJson($json);
	}

	public function postEkzanfordern()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$forcierungskey = isset($data['forcierungskey']) ? $data['forcierungskey'] : null;

		$json = $this->dvuhmasterdatamanagementlib->requestEkz($person_id, $forcierungskey, $preview);

		$this->outputJson($json);
	}

	public function postStudiumStorno()
	{
		$json = null;

		$data = $this->input->post('data');
		$preview = $this->input->post('preview');

		$semester = isset($data['semester']) ? $data['semester'] : null;
		$prestudent_id = isset($data['prestudent_id']) ? $data['prestudent_id'] : null;

		$json = $this->dvuhstudydatamanagementlib->cancelStudyData($prestudent_id, $semester, $preview);

		$this->outputJson($json);
	}

	public function deletePruefungsaktivitaeten()
	{
		$json = null;

		$data = $this->input->post('data');

		$person_id = isset($data['person_id']) ? $data['person_id'] : null;
		$prestudent_id = isset($data['prestudent_id']) ? $data['prestudent_id'] : null;
		$semester = isset($data['semester']) ? $data['semester'] : null;

		$json = $this->dvuhpruefungsaktivitaetenmanagementlib->deletePruefungsaktivitaeten($person_id, $semester, $prestudent_id);

		$this->outputJson($json);
	}

	/**
	 * Gets methods for which logged user has permission
	 */
	public function getPermittedActions()
	{
		$permittedMethods = array();

		$this->load->library('PermissionLib'); // Load permission library

		// for all methods with their permissions
		foreach ($this->_permissions as $method => $permissions)
		{
			// convert to array if only one permission
			if (!is_array($permissions))
				$permissions = array($permissions);

			// for all permissions of this method
			foreach ($permissions as $permission)
			{
				// separate permission name from access type
				$berechtigung = explode(self::PERMISSION_TYPE_SEPARATOR, $permission);

				// berechtigung must consist of name and access type
				if (count($berechtigung) != 2)
				{
					$this->outputJsonError("Invalid permission array");
					return;
				}

				$berechtigung_kurzbz = $berechtigung[0];
				// convert access type to legacy permission format
				$berechtigung_art = str_replace(
					array(PermissionLib::READ_RIGHT, PermissionLib::WRITE_RIGHT),
					array(PermissionLib::SELECT_RIGHT, PermissionLib::REPLACE_RIGHT),
					$berechtigung[1]
				);

				// return method name if user is authorized
				if (!in_array($method, $permittedMethods) && $this->permissionlib->isBerechtigt($berechtigung_kurzbz, $berechtigung_art))
				{
					$permittedMethods[] = $method;
				}
			}
		}

		$this->outputJsonSuccess($permittedMethods);
	}

	/**
	 * Outputs errors or result in JSON DVUH format
	 */
	private function _outputJsonDVUH($result)
	{
		if (isError($result))
			$this->outputJsonError($this->dvuhissuelib->getIssueTexts(getError($result)));
		else
			$this->outputJson($result);
	}
}
