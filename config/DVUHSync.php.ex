<?php

// if set to true, infos will be logged to webservicelog, otherwise only warnings and errors
$config['fhc_dvuh_log_infos'] = false;

// Default time spans for Studiensemester for which data is sent to DVUH.
// Only used when no Studiensemester parameters passed.
$config['fhc_dvuh_studiensemester_meldezeitraum'] = array(
	'SS2021' => array(
		'von' => '2021-01-01',
		'bis' => '2021-11-15'
	),
	'WS2021' => array(
		'von' => '2021-06-01',
		'bis' => '2022-04-15'
	)
);

// Only students with given status_kurzbz (defined for each job) are sent to DVUH
$config['fhc_dvuh_status_kurzbz'] = array(
	'DVUHRequestMatrikelnummer' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand'),
	'DVUHSendCharge' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	'DVUHSendPayment' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	'DVUHSendStudyData' => array('Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent'),
	'DVUHGetBpk' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand'),
	'DVUHRequestBpk' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand'),
	'DVUHRequestEkz' => array('Bewerber', 'Aufgenommener', 'Student', 'Incoming', 'Diplomand'),
	'DVUHSendPruefungsaktivitaeten' => array('Aufgenommener', 'Student', 'Incoming', 'Diplomand', 'Abbrecher', 'Unterbrecher', 'Absolvent')
);

// All status_kurzbz which an active student can have
$config['fhc_dvuh_active_student_status_kurzbz'] = array('Student', 'Incoming', 'Diplomand');

// All status_kurzbz which a student who terminated studies can have
$config['fhc_dvuh_terminated_student_status_kurzbz'] = array('Abgewiesener', 'Abbrecher');

// All status_kurzbz which a student who finished studies can have
$config['fhc_dvuh_finished_student_status_kurzbz'] = array('Absolvent', 'Abbrecher');

// All status_kurzbz which a student who started, but hasn't finished studies can have
$config['fhc_dvuh_unfinished_student_status_kurzbz'] = array('Student', 'Unterbrecher', 'Diplomand');

// buchungstypen to be considered when sending payments to DVUH
$config['fhc_dvuh_buchungstyp'] = array(
	'oehbeitrag' => array('OEH'),
	'studiengebuehr' => array('Studiengebuehr', 'StudiengebuehrAnzahlung', 'StudiengebuehrRestzahlung')
);

// if set, only students assigned to this oe or a child oe (determined by Studiengang) are sent to DVUH
$config['fhc_dvuh_oe_kurzbz'] = null;

// if false, no Bildungseinrichtung mails are sent to DVUH
$config['fhc_dvuh_send_university_mail'] = true;

$config['fhc_dvuh_sync_days_valutadatum'] = 30; // days after buchungsdatum for valutadatum for payment Frist
$config['fhc_dvuh_sync_days_valutadatumnachfrist'] = 90; // Nachfrist in days after valutadatum for payments
$config['fhc_dvuh_sync_euros_studiengebuehrnachfrist'] = 0; // amount to be added to studiengebuehr when Nachfrist is set

$config['fhc_dvuh_sync_nullify_buchungen_paid_other_univ'] = true; // if true, Buchungen are set to 0 if paid only on other university

// Ausserordentliche students are sent with this prefix to the studiengang_kz
$config['fhc_dvuh_sync_ausserordentlich_prefix'] = 9;

// StudStatusCode e.g. for gemeinsame Studien Statuscode
$config['fhc_dvuh_sync_student_statuscode'] = array(
	'Student' => 1,
	'Incoming' => 1,
	'Praktikant' => 1,
	'Outgoing' => 1,
	'Diplomand' => 1,
	'Unterbrecher' => 2,
	'Absolvent' => 3,
	'Abbrecher' => 4
);

// Meldestatus names and codes
$config['fhc_dvuh_sync_student_meldestatus'] = array(
	'zugelassen_inland' => 'I',
	'zugelassen_ausland' => 'A',
	'unterbrochen' => 'U',
	'storniert' => 'O'
);

// Noten-codes for sending angerechnete ECTS for Prüfungsaktivitäten
$config['fhc_dvuh_sync_note_angerechnet'] = array(6, 19);

// Vbpk types to save (mapping "DVUH acronym => fh database kennzeichentyp_kurzbz")
$config['fhc_dvuh_sync_vbpk_types'] = array(
	'AS' => 'vbpkAs',
	'BF' => 'vbpkBf',
	'ZP-TD' => 'vbpkTd',
);

// max number of "checkbpk" requests before a sleep (for preventing server errors)
$config['fhc_dvuh_sync_pruefe_bpk_max_requests'] = 50;
