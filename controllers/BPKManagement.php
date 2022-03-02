<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Page for checking Persons where no bPK was found automatically
 */
class BPKManagement extends Auth_Controller
{
	private $_uid; // contains the UID of the logged user

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'admin:r',
				'showDetails' => 'admin:r',
				'checkBpkCombinations' => 'admin:r',
				'getAllNameCombinations' => 'admin:r',
				'saveBpk' => 'admin:rw',
				'outputAkteContent' => 'admin:r'
			)
		);

		// Loads models
		$this->load->model('crm/akte_model', 'AkteModel');
		$this->load->model('person/person_model', 'PersonModel');

		$this->load->library('extensions/FHC-Core-DVUH/BPKManagementLib');
		$this->load->library('WidgetLib');
		$this->loadPhrases(
			array(
				'global',
				'person',
				'lehre',
				'ui',
				'infocenter',
				'filter'
			)
		);

		$this->_setAuthUID(); // sets property uid
		$this->setControllerId(); // sets the controller id
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Main page of BPK management
	 */
	public function index()
	{
		$this->_setNavigationMenuIndex(); // define the navigation menu for this page

		$this->load->view('extensions/FHC-Core-DVUH/BPKManagement.php');
	}

	/**
	 * Show details page
	 */
	public function showDetails()
	{
		$this->_setNavigationMenuShowDetails();
		$person_id = $this->input->get('person_id');

		if (!is_numeric($person_id))
			show_error('person id is not numeric!');

		$personData = $this->bpkmanagementlib->loadPersonData($person_id);

		if (isError($personData))
			show_error('Fehler beim Holen der Persondaten');

		if (!hasData($personData))
			show_error('Person nicht gefunden');

		$this->load->view('extensions/FHC-Core-DVUH/BPKDetails.php', getData($personData));
	}

	public function checkBpkCombinations()
	{
		$bpkCombRes = null;
		$person_id = $this->input->get('person_id');

		if (!is_numeric($person_id))
			$bpkCombRes = error("Person Id ungültig!");
		else
			$bpkCombRes = $this->bpkmanagementlib->checkBpkCombinations($person_id);

		$this->outputJson($bpkCombRes);
	}

	public function getAllNameCombinations()
	{
		$person_id = $this->input->get('person_id');

		$combinations = array();

		$this->PersonModel->addSelect('vorname, vornamen, nachname');
		$personDataRes = $this->PersonModel->load($person_id);

		if (isError($personDataRes))
			$combinations = $personDataRes;
		elseif (hasData($personDataRes))
		{
			$personData = getData($personDataRes)[0];

			$nameArr = array(
				'vorname' => $personData->vorname,
				'vornamen' => $personData->vornamen,
				'nachname' => $personData->nachname
			);

			// get firstname/lastname combinations for bpk check
			$combinations = success($this->bpkmanagementlib->getNamesForBpkCheck($nameArr));
		}

		$this->outputJson($combinations);
	}

	/**
	 * Saves a bPK for a person
	 */
	public function saveBpk()
	{
		$person_id = $this->input->post('person_id');
		$bpk = $this->input->post('bpk');

		if (isEmptyString($person_id))
			$bpkSaveResult = error('PersonID fehlt');
		elseif (!$this->dvuhsynclib->checkBpk($bpk))
			$bpkSaveResult = error('bPK ungültig');
		else
		{
			$bpkSaveResult = $this->PersonModel->update(
				$person_id,
				array(
					'bpk' => $bpk,
					'updateamum' => date('Y-m-d H:i:s'),
					'updatevon' => $this->_uid
				)
			);
		}

		$this->outputJson($bpkSaveResult);
	}

	/**
	 * Outputs content of an Akte, sends appropriate headers (so the document can be downloaded)
	 * @param $akte_id
	 */
	public function outputAkteContent($akte_id)
	{
		$this->load->library('DmsLib');

		$akteRes = $this->AkteModel->load($akte_id);

		if (isError($akteRes))
		{
			show_error(getError($akteRes));
		}

		if (hasData($akteRes))
		{
			$akteContentRes = $this->dmslib->getAkteContent($akte_id);

			if (isError($akteContentRes))
			{
				show_error(getError($akteContentRes));
			}

			if (hasData($akteContentRes))
			{
				$akteData = getData($akteRes);
				$akteContentData = getData($akteContentRes);

				$this->output
					->set_status_header(200)
					->set_content_type($akteData[0]->mimetype, 'utf-8')
					->set_header('Content-Disposition: attachment; filename="'.$akteData[0]->titel.'"')
					->set_output($akteContentData)
					->_display();
			}
			else
				show_error("Akte Inhalt nicht gefunden");
		}
		else
			show_error("Akte nicht gefunden");
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Retrieve the UID of the logged user and checks if it is valid
	 */
	private function _setAuthUID()
	{
		$this->_uid = getAuthUID();

		if (!$this->_uid) show_error('User authentification failed');
	}

	/**
	 *  Define the navigation menu for the showDetails page
	 */
	private function _setNavigationMenuShowDetails()
	{
		$this->load->library('NavigationLib', array('navigation_page' => 'extensions/FHC-Core-DVUH/BPKManagement/showDetails'));

		$link = site_url('extensions/FHC-Core-DVUH/BPKManagement');

		$this->navigationlib->setSessionMenu(
			array(
				'back' => $this->navigationlib->oneLevel(
					'Zurück',	// description
					$link,			// link
					array(),		// children
					'angle-left',	// icon
					true,			// expand
					null, 			// subscriptDescription
					null, 			// subscriptLinkClass
					null, 			// subscriptLinkValue
					'', 			// target
					1 				// sort
				)
			)
		);
	}

	/**
	 *  Define the navigation menu for the BPK Management page
	 */
	private function _setNavigationMenuIndex()
	{
		$this->load->library('NavigationLib', array('navigation_page' => 'extensions/FHC-Core-DVUH/BPKManagement'));

		$link = site_url();

		$this->navigationlib->setSessionMenu(
			array(
				'back' => $this->navigationlib->oneLevel(
					'Zurück',	// description
					$link,			// link
					array(),		// children
					'angle-left',	// icon
					true,			// expand
					null, 			// subscriptDescription
					null, 			// subscriptLinkClass
					null, 			// subscriptLinkValue
					'', 			// target
					1 				// sort
				)
			)
		);
	}
}
