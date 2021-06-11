<?php

// if set to true, infos will be logged to webservicelog, otherwise only warnings and errors
$config['fhc_dvuh_log_infos'] = false;

/*$config['fhc_dvuh_bisdatum_ws'] = array('month' => '11', 'day' => '15');
$config['fhc_dvuh_bisdatum_ss'] = array('month' => '03', 'day' => '15');*/

// if set, only students assigned (determined by Studiengang) to this oe or a child oe are sent to DVUH
$config['fhc_dvuh_status_kurzbz'] = array(
	JQMSchedulerLib::JOB_TYPE_REQUEST_MATRIKELNUMMER => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand'),
	JQMSchedulerLib::JOB_TYPE_SEND_CHARGE => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	JQMSchedulerLib::JOB_TYPE_SEND_PAYMENT => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	JQMSchedulerLib::JOB_TYPE_SEND_STUDY_DATA => array('Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	JQMSchedulerLib::JOB_TYPE_REQUEST_BPK => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
);

// buchungstypen to be considered when sending payments to DVUH
$config['fhc_dvuh_buchungstyp'] = array(
	'oehbeitrag' => array('OEH'),
	'studiengebuehr' => array('Studiengebuehr', 'StudiengebuehrAnzahlung')
);

// if set, only students assigned to this oe or a child oe (determined by Studiengang) are sent to DVUH
$config['fhc_dvuh_oe_kurzbz'] = null;

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

