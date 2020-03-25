<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Example API
 */
class Example extends API_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct(array('Example' => 'basis/person:rw'));

		// Loads QueryAccountsModel
		$this->load->model('extensions/FHC-Core-DVUH/Fullstudent_model', 'FullstudentModel');
	}

	/**
	 * Example method
	 */
	public function getExample()
	{
		$this->response(
			$this->FullstudentModel->get('12345'),
			REST_Controller::HTTP_OK
		);
	}
}
