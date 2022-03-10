<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Overview page for students which need to be cancelled in DVUH
 */
class StornoOverview extends Auth_Controller
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'admin:r'
			)
		);

		// load libraries
		$this->load->library('WidgetLib');
		$this->load->library('NavigationLib', array('navigation_page' => 'extensions/FHC-Core-DVUH/StornoOverview'));

		// load configs
		$this->config->load('extensions/FHC-Core-DVUH/DVUHSync');

		// load phrases
		$this->loadPhrases(
			array(
				'global',
				'person',
				'lehre',
				'ui',
				'filter'
			)
		);

		$this->setControllerId(); // sets the controller id
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Launch display of storno overview
	 */
	public function index()
	{
		$status_kurzbz = $this->config->item('fhc_dvuh_status_kurzbz');

		$this->load->view(
			'extensions/FHC-Core-DVUH/stornoOverview.php',
			array(
				'valid_status_kurzbz' => $status_kurzbz['DVUHSendStudyData']
			)
		);
	}
}
