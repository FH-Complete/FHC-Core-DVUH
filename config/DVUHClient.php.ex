<?php

// Type FH | UNI
$config['fhc_dvuh_be_type'] = 'FH';

// BE Code
$config['fhc_dvuh_be_code'] = DVB_BILDUNGSEINRICHTUNG_CODE;

// Url path, rws or sandbox
$config['fhc_dvuh_path'] = 'rws';

// Api version, is part of url
$config['fhc_dvuh_api_version'] = '0.8';

// path for UHSTAT requests
$config['fhc_dvuh_uhstat_path'] = 'uhstat';

// UHSTAT Api version, is part of url
$config['fhc_dvuh_uhstat_api_version'] = '2.0';

// Connection Details
$config['fhc_dvuh_active_connection'] = 'TESTING'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['fhc_dvuh_connections'] = array(
	'PRODUCTION' => array(
		'portal' => DVB_PORTAL,
		'username' => DVB_USERNAME,
		'password' => DVB_PASSWORD
	),
	'TESTING' => array(
		'portal' => DVB_PORTAL,
		'username' => DVB_USERNAME,
		'password' => DVB_PASSWORD
	)
);
