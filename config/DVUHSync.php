<?php

// if set to true, infos will be logged to webservicelog, otherwise only warnings and errors
$config['fhc_dvuh_log_infos'] = false;

/*$config['fhc_dvuh_bisdatum_ws'] = array('month' => '11', 'day' => '15');
$config['fhc_dvuh_bisdatum_ss'] = array('month' => '03', 'day' => '15');*/

// Only students with given status_kurzbz (defined for each job) are sent to DVUH
$config['fhc_dvuh_status_kurzbz'] = array(
	'DVUHRequestMatrikelnummer' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand'),
	'DVUHSendCharge' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	'DVUHSendPayment' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	'DVUHSendStudyData' => array('Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	'DVUHRequestBpk' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand')
);

// buchungstypen to be considered when sending payments to DVUH
$config['fhc_dvuh_buchungstyp'] = array(
	'oehbeitrag' => array('OEH'),
	'studiengebuehr' => array('Studiengebuehr', 'StudiengebuehrAnzahlung')
);

// if set, only students assigned to this oe or a child oe (determined by Studiengang) are sent to DVUH
$config['fhc_dvuh_oe_kurzbz'] = null;

$config['fhc_dvuh_sync_days_valutadatum'] = 30; // days after buchungsdatum for valutadatum for payment Frist
$config['fhc_dvuh_sync_days_valutadatumnachfrist'] = 0; // Nachfrist in days after valutadatum for payments
$config['fhc_dvuh_sync_euros_studiengebuehrnachfrist'] = 0; // in euros and cents to be added to studiengebuehr when Nachfrist is set

// StudStatusCode
$config['fhc_dvuh_sync_student_statuscode'] = array(
	'Student' => 1,
	'Unterbrecher' => 2,
	'Absolvent' => 3,
	'Abbrecher' => 4
);

