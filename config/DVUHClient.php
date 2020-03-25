<?php

// Type FH | UNI
$config['fhc_dvuh_be_type'] = 'FH';

// BE Code
$config['fhc_dvuh_be_code'] = DVB_BILDUNGSEINRICHTUNG_CODE;

// Connection Details
$config['fhc_dvuh_active_connection'] = 'PRODUCTION'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['fhc_dvuh_connections'] = array(
	'PRODUCTION' => array(
		'portal' => DVB_PORTAL,
		'username' => DVB_USERNAME,
		'password' => DVB_PASSWORD
	)
);
