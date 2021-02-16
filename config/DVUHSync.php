<?php

// payments and students studying before this date will be ignored
$config['fhc_dvuh_sync_startdatum'] = '2020-09-01';

$config['fhc_dvuh_sync_days_valutadatumnachfrist'] = 0; // Nachfrist in days after valutadatum for payments
$config['fhc_dvuh_sync_euros_studiengebuehrnachfrist'] = 0; //in euros and cents to be added to studiengebuehr when Nachfrist is set

$config['fhc_dvuh_sync_not_foerderrelevant'] = array(); // prestudent_ids set to not foerderrelevant
$config['fhc_dvuh_sync_student_standort'] = array(); // $prestudent_id => $standortid
$config['fhc_dvuh_sync_standortcode_wien'] = '22';
$config['fhc_dvuh_sync_stg_standortcode'] = array(
	'265' => '14', '268' => '14', '761' => '14',
	'760' => '14', '266' => '14', '267' => '14',
	'764' => '14', '269' => '14', '400' => '14',
	'794' => '14', '795' => '14', '786' => '14', '859' => '14', // 14 - pinkafeld
	'639' => '3', '640' => '3', '263' => '3', '743' => '3',
	'364' => '3', '635' => '3', '402' => '3', '401' => '3',
	'725' => '3', '264' => '3', '271' => '3', '781' => '3'// 3- Eisenstadt
);
// StudStatusCode
$config['fhc_dvuh_sync_student_statuscode'] = array(
	'Student' => 1,
	'Unterbrecher' => 2,
	'Absolvent' => 3,
	'Abbrecher' => 4
);

