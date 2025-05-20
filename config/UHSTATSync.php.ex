<?php

// if set to true, infos will be logged to webservicelog, otherwise only warnings and errors
$config['fhc_uhstat_log_infos'] = true;

// Default time spans for Studiensemester for which data is sent to DVUH.
// Only used when no Studiensemester parameters passed.
$config['fhc_uhstat_studiensemester_meldezeitraum'] = array(
	'SS2025' => array(
		'von' => '2024-01-01', // SS from 01.01
		'bis' => '2025-31-05' // SS to 31.05
	),
	'WS2024' => array(
		'von' => '2023-06-01', // WS from 01.06
		'bis' => '2024-12-31' // WS to 31.12
	)
);

// Only students with given status_kurzbz (defined for each job) are sent to DVUH
$config['fhc_uhstat_status_kurzbz'] = array(
	'DVUHUHSTAT1' => array('Bewerber'),
	'DVUHUHSTAT2' => array('Absolvent', 'Abbrecher')
);

// if set, only students assigned to this oe or a child oe (determined by Studiengang) are sent to DVUH
$config['fhc_uhstat_oe_kurzbz'] = null;

// type of institution, for which data is sent
// 0 - Lehrverbund, 1 - öffentl. Uni, 2 - Privatuni, 3 - Pädagogische Hochschule, 4 - Fachhochschule
$config['fhc_uhstat_institutionen_typ'] = '0';

// code of institution
$config['fhc_uhstat_institutionen_code'] = 'XXXXX';
