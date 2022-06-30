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
			->set_content_type('text/plain')
			->set_header('Content-Disposition: attachement; filename="'.self::FILE_NAME.'"')
			->set_output($csv)
			->_display();
	}

	public function showRohdatenOehbeitrag()
	{
		$this->outputJson($this->_getRohdatenOehbeitrag());
	}


	//------------------------------------------------------------------------------------------------------------------
	// private methods

	public function _getRohdatenOehbeitrag()
	{
		$dateFrom = $this->input->get('dateFrom');
		$dateTo = $this->input->get('dateTo');
		// $dateFrom = '2021-01-01';
		// $dateTo = '2021-12-31';

		$be = $this->config->item('fhc_dvuh_be_code');

		$this->load->model('extensions/FHC-Core-DVUH/RohdatenOehBeitrag_model', 'RohdatenOehBeitragModel');

		return $this->RohdatenOehBeitragModel->get(
			$be, $dateFrom, $dateTo
		);

		if (isError($csvRes))
			show_error(getError($csvRes));

		if (hasData($csvRes))
			$csv = getData($csvRes);

		$this->output
			->set_status_header(200)
			->set_content_type('text/plain')
			->set_header('Content-Disposition: attachement; filename="'.self::FILE_NAME.'"')
			->set_output($csv)
			->_display();
	}
}
