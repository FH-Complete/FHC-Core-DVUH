<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

use \CI3_Events as Events;
use \FHCAPI_Controller as FHCAPI_Controller;

function event_dvuh_konto_delete_allowed($buchungsnr)
{
	$CI =& get_instance();

	$CI->load->model('extensions/FHC-Core-DVUH/synctables/DVUHZahlungen_model', 'DVUHZahlungenModel');

	$result = $CI->DVUHZahlungenModel->loadWhere(['buchungsnr' => $buchungsnr]);
	
	if (isError($result))
		$CI->addError(getError($result), FHCAPI_Controller::ERROR_TYPE_DB);
	elseif (hasData($result))
		return false;

	return true;
}

Events::on('konto_delete_validation', function ($buchungsnr, $validationObj) {
	$CI =& get_instance();

	$CI->load->library('PhrasesLib', ['datenverbund'], 'p_dvuh');
	$validationObj->validations[] = [
		'app' => 'dvuh',
		'field' => 'buchungsnr',
		'fieldDisplay' => 'Buchungsnr',
		'result' => event_dvuh_konto_delete_allowed($buchungsnr),
		'error_message' => $CI->p_dvuh->t('datenverbund', 'error_buchungNotDeletable')
	];
});
