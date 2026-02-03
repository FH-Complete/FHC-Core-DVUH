<?php

$config['fhc_dvuh_menu'] = array(
	'Matrikelnummermanagement' => array(
		'width' => 6,
		'left' => array(
			array(
				'id' => 'getMatrikelnummer',
				'description' => 'Matrikelnummer prüfen',
				'active' => true
			),
			array(
				'id' => 'postMatrikelnummer',
				'description' => 'Matrikelnummer melden',
				'active' => false
			),
			array(
				'id' => 'postMatrikelkorrektur',
				'description' => 'Matrikelnummer korrigieren',
				'active' => true
			),
		),
		'right' => array(
			array(
				'id' => 'getMatrikelnummerReservierungen',
				'description' => 'Reservierungen anzeigen',
				'active' => true
			),
			array(
				'id' => 'reserveMatrikelnummer',
				'description' => 'Matrikelnummer reservieren',
				'active' => true
			)
		)
	),
	'Stammdatenmanagement' => array(
		'width' => 3,
		'left' => array(
			array(
				'id' => 'getStammdaten',
				'description' => 'Stammdaten und Zahlungsvorschreibung abfragen',
				'active' => true
			),
			array(
				'id' => 'postMasterData',
				'description' => 'Stammdaten und Matrikelnummer melden',
				'active' => true
			),
			array(
				'id' => 'postEkzanfordern',
				'description' => 'EKZ anfordern',
				'active' => true
			),
		)
	),
	'bPK Management' => array(
		'width' => 3,
		'left' => array(
			array(
				'id' => 'getPruefeBpkByPersonId',
				'description' => 'bPK ermitteln',
				'active' => true
			),
			array(
				'id' => 'getPruefeBpk',
				'description' => 'bPK manuell ermitteln',
				'active' => true
			),
			array(
				'id' => 'postErnpmeldung',
				'description' => 'ERnP Meldung durchführen',
				'active' => true
			),
		)
	),
	'Studiumsdatenmanagement' => array(
		'width' => 6,
		'left' => array(
			array(
				'id' => 'getStudium',
				'description' => 'Studiumsdaten abfragen',
				'active' => true
			),
			array(
				'id' => 'getFullstudent',
				'description' => 'Detaillierte Studiendaten abfragen',
				'active' => true
			)
		),
		'right' => array(
			array(
				'id' => 'postStudium',
				'description' => 'Studiumsdaten melden',
				'active' => true
			),
			array(
				'id' => 'postStudiumStorno',
				'description' => 'Studiumsdaten stornieren',
				'active' => true
			)
		)
	),
	'Zahlungsmanagement' => array(
		'width' => 3,
		'left' => array(
			array(
				'id' => 'getKontostaende',
				'description' => 'Kontostand abfragen',
				'active' => true
			),
			array(
				'id' => 'postPayment',
				'description' => 'Zahlungseingang melden',
				'active' => true
			)
		)
	),
	'Prüfungsaktivitätenmanagement' => array(
		'width' => 3,
		'left' => array(
			array(
				'id' => 'getPruefungsaktivitaeten',
				'description' => 'Pr&uuml;fungsaktivit&auml;ten abfragen',
				'active' => true
			),
			array(
				'id' => 'postPruefungsaktivitaeten',
				'description' => 'Pr&uuml;fungsaktivit&auml;ten melden',
				'active' => true
			),
			array(
				'id' => 'deletePruefungsaktivitaeten',
				'description' => 'Pr&uuml;fungsaktivit&auml;ten l&ouml;schen',
				'active' => true
			)
		)
	),
);
