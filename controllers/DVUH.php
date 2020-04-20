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
			)
		);
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Index Page
	 */
	public function index()
	{
		$this->load->library('WidgetLib');
		$this->load->view('extensions/FHC-Core-DVUH/dvuh');
	}
}
