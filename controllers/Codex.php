<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Export Codex Tables from DVUH
 */
class Codex extends Auth_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				'index' => 'admin:r',
				'exportbecodes'=>'admin:r',
				'exportlaendercodes'=>'admin:r',
				'fehlerliste'=>'admin:r'
			)
		);

		$this->loadPhrases(
			array(
				'codex'
			)
		);
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	public function index()
	{
		$this->load->library('WidgetLib');
		$this->load->view('extensions/FHC-Core-DVUH/codex');
	}

	/**
	 * Creates a CSV List of BE Codes
	 */
	public function exportbecodes()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Exportbecodes_model', 'ExportbecodesModel');
		$data = $this->ExportbecodesModel->get();

		if(hasData($data))
		{
			header('Content-Type: text/csv');
			echo getData($data);
		}
	}

	/**
	 * Creates a CSV List of Country Codes
	 */
	public function exportlaendercodes()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Exportlaendercodes_model', 'ExportlaendercodesModel');
		$data = $this->ExportlaendercodesModel->get();

		if(hasData($data))
		{
			header('Content-Type: text/csv');
			echo getData($data);
		}
	}

	/**
	 * Creates a CSV List of Error Codes
	 */
	public function fehlerliste()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Fehlerliste_model', 'FehlerlisteModel');
		$data = $this->FehlerlisteModel->get();

		if(hasData($data))
		{
			header('Content-Type: text/csv');
			echo getData($data);
		}
	}
}
