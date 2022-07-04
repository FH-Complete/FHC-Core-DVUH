<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Öhbeitragsliste page
 */
class RohdatenOehBeitrag extends Auth_Controller
{
	const FILE_NAME = 'Oehbeitragsliste.csv';

	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index'=> 'admin:r',
				'downloadRohdatenOehbeitrag'=> 'admin:r',
				'showRohdatenOehbeitrag'=> 'admin:r'
			)
		);

		$this->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		$this->config->load('extensions/FHC-Core-DVUH/DVUHClient');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	public function index()
	{
		$this->load->library('WidgetLib');
		$this->load->view('extensions/FHC-Core-DVUH/rohdatenOehBeitrag');
	}

	//------------------------------------------------------------------------------------------------------------------
	// GET methods

	/**
	 * Wrapper function, returns Oehbeitragsliste as json text
	 */
	public function showRohdatenOehbeitrag()
	{
		$this->outputJson($this->_getRohdatenOehbeitrag());
	}

	/**
	 * Wrapper function, returns Oehbeitragsliste as a downloadable csv file
	 */
	public function downloadRohdatenOehbeitrag()
	{
		$csv = '';

		$csvRes = $this->_getRohdatenOehbeitrag();

		if (isError($csvRes))
			show_error(getError($csvRes));

		if (hasData($csvRes))
			$csv = getData($csvRes);

		$this->output
			->set_status_header(200)
			->set_content_type('text/csv')
			->set_header('Content-Disposition: attachement; filename="'.self::FILE_NAME.'"')
			->set_output($csv);
	}

	//------------------------------------------------------------------------------------------------------------------
	// private methods

	/**
	 * Gets the Oehbeitragsliste between two input dates.
	 */
	private function _getRohdatenOehbeitrag()
	{
		$dateFrom = convertDateToIso($this->input->get('dateFrom'));
		$dateTo = convertDateToIso($this->input->get('dateTo'));

		$be = $this->config->item('fhc_dvuh_be_code');

		$this->load->model('extensions/FHC-Core-DVUH/RohdatenOehBeitrag_model', 'RohdatenOehBeitragModel');

		return $this->RohdatenOehBeitragModel->get(
			$be, $dateFrom, $dateTo
		);
	}
}
