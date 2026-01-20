<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * SM - send Matrikelnummer
 * SC - send charge
 * SP - send payment
 * SS - send study data
 * RE - request Ersatzkennzeichen
 *
 */
$config['fehler'] = array(
	/* self-defined FHC Errors */
	array(
		'fehlercode' => 'DVUH_SM_0001',
		'fehler_kurzbz' => 'nameUndGebdatumAngeben',
		'fehlercode_extern' => null,
		'fehlertext' => 'Wenn der Name angegeben ist muss auch ein Geburtsdatum angegeben werden',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SM_0001',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0001',
		'fehler_kurzbz' => 'matrNrFehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'Matrikelnummer nicht gesetzt',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0001',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0002',
		'fehler_kurzbz' => 'keineZustelladresse',
		'fehlercode_extern' => null,
		'fehlertext' => 'Keine Zustelladresse angegeben',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0002',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0003',
		'fehler_kurzbz' => 'keineHeimatadresse',
		'fehlercode_extern' => null,
		'fehlertext' => 'Keine Heimatadresse angegeben',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0003',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0004',
		'fehler_kurzbz' => 'adresseUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Adresse ungültig: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0004',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0005',
		'fehler_kurzbz' => 'ersatzkennzeichenUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Ersatzkennzeichen ungültig, muss aus 4 Grossbuchstaben gefolgt von 6 Zahlen bestehen',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0005',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0006',
		'fehler_kurzbz' => 'stammdatenFehlen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Stammdaten fehlen: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0006',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0007',
		'fehler_kurzbz' => 'ungueltigeSonderzeichen',
		'fehlercode_extern' => null,
		'fehlertext' => '%s enthält ungültige Sonderzeichen',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0007',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0008',
		'fehler_kurzbz' => 'emailEnthaeltSonderzeichen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Email enthält Sonderzeichen',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0008',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0009',
		'fehler_kurzbz' => 'oehbeitragNichtSpezifiziert',
		'fehlercode_extern' => null,
		'fehlertext' => 'Keine Höhe des Öhbeiträgs in Öhbeitragstabelle für Studiensemester %s spezifiziert, Buchung %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0009',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0010',
		'fehler_kurzbz' => 'bpkUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'BPK ungültig, muss aus 27 Zeichen (alphanum. mit / +) gefolgt von = bestehen',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0010',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0011',
		'fehler_kurzbz' => 'titelpreUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Titel pre hat ungültiges Format',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0011',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0012',
		'fehler_kurzbz' => 'titelpostUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Titel post hat ungültiges Format',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0012',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_0013',
		'fehler_kurzbz' => 'vorschreibungUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Vorschreibung ungültig, Zahlungstypen: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_0013',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SP_0001',
		'fehler_kurzbz' => 'zlgUngleichVorschreibung',
		'fehlercode_extern' => null,
		'fehlertext' => 'Buchung: %s: Zahlungsbetrag abweichend von Vorschreibungsbetrag',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SP_0001',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0001',
		'fehler_kurzbz' => 'matrikelnrUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Matrikelnummer ungültig (%s)',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0001',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0002',
		'fehler_kurzbz' => 'fehlerhafteZgvDaten',
		'fehlercode_extern' => null,
		'fehlertext' => 'Fehlerhafte ZGV Daten: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0002',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0003',
		'fehler_kurzbz' => 'fehlerhafteZgvMasterDaten',
		'fehlercode_extern' => null,
		'fehlertext' => 'Fehlerhafte ZGV Master Daten: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0003',
		'producerIsResolver' => true
	),
	array(
		'fehlercode' => 'DVUH_SS_0004',
		'fehler_kurzbz' => 'personenkennzeichenUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Personenkennzeichen ungültig (%s)',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0004',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0005',
		'fehler_kurzbz' => 'zuVieleZweckeIncoming',
		'fehlercode_extern' => null,
		'fehlertext' => 'Es sind %s Aufenthaltszwecke eingetragen (max. 1 Zweck für Incomings)',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0005',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0006',
		'fehler_kurzbz' => 'falscherIncomingZweck',
		'fehlercode_extern' => null,
		'fehlertext' => 'Aufenthaltszweckcode ist %s (für Incomings ist nur Zweck 1, 2, 3 erlaubt)',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0006',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0007',
		'fehler_kurzbz' => 'falscherIncomingZweckGemeinsam',
		'fehlercode_extern' => null,
		'fehlertext' => 'Aufenthaltzweckcode 1, 2, 3 dürfen nicht gemeinsam gemeldet werden',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0007',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0008',
		'fehler_kurzbz' => 'outgoingAufenthaltfoerderungfehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'Keine Aufenthaltsfoerderung angegeben (bei Outgoings >= 29 Tage Monat im Ausland muss mind. 1 gemeldet werden)Keine Aufenthaltsfoerderung angegeben (bei Outgoings >= 29 Tage Monat im Ausland muss mind. 1 gemeldet werden)',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0008',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0009',
		'fehler_kurzbz' => 'outgoingAngerechneteEctsFehlen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Angerechnete ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0009',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0010',
		'fehler_kurzbz' => 'outgoingErworbeneEctsFehlen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Erworbene ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0010',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0011',
		'fehler_kurzbz' => 'lehrgangdatenFehlen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Lehrgangdaten fehlen: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0011',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0012',
		'fehler_kurzbz' => 'studiumdatenFehlen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Studiumdaten fehlen: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0012',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0013',
		'fehler_kurzbz' => 'ungueltigeMeldeStudiengangskennzahl',
		'fehlercode_extern' => null,
		'fehlertext' => 'Ungültige Meldestudiengangskennzahl für Studiengang %s, gültiges Format: (3 Stellen für Erhalter wenn Lehrgang) [4 Stellen Studiengang]',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0013',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0014',
		'fehler_kurzbz' => 'studienkennunguniUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Ungültige Studienkennung Uni für GS mit Programmcode %s, muss z.B. UUT190593347UA sein',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0014',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0015',
		'fehler_kurzbz' => 'herkunftslandFehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'Herkunftsland fehlt',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0015',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0016',
		'fehler_kurzbz' => 'gsdatenFehlen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Daten für gemeinsames Studium fehlen: %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0016',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0017',
		'fehler_kurzbz' => 'orgformUngueltig',
		'fehlercode_extern' => null,
		'fehlertext' => 'Orgform ungültig',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_0017',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_0018',
		'fehler_kurzbz' => 'nichtGemeldeteStudierende',
		'fehlercode_extern' => null,
		'fehlertext' => 'Zu meldende/r Studierende/r kurz vor Ende der Bismeldung nicht gemeldet, Zahlungsvorschreibung überprüfen, prestudent Id %s, Studiensemester %s',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => 'NichtGemeldeteStudierende',
		'resolverLibName' => 'DVUH_SS_0018',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_RE_0001',
		'fehler_kurzbz' => 'personMitEkzExistiert',
		'fehlercode_extern' => null,
		'fehlertext' => 'Person (person Id %s) mit EKZ %s existiert bereits',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_RE_0001',
		'producerIsResolver' => false
	),
	/* DVUH Errors */
	array(
		'fehlercode' => 'DVUH_ERROR',
		'fehler_kurzbz' => 'dvuhFehler',
		'fehlercode_extern' => null,
		'fehlertext' => 'DVUH Fehler ist aufgetreten',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SM_MATRNR_STATUS_2',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'MATRNR_STATUS_2',
		'fehlertext' => 'Matrikelnummer gesperrt, Matrikelnummer prüfen, Datenverbund kontaktieren.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SM_MATRNR_STATUS_4',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'MATRNR_STATUS_4',
		'fehlertext' => 'Aktive, noch nicht scharfgeschaltene Matrikelnummer an einer Bildungseinrichtung. In Evidenz halten, bis Student an einer Bildungseinrichtung scharf geschalten ist.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SM_MATRNR_STATUS_6',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'MATRNR_STATUS_6',
		'fehlertext' => 'Zwei Datensätze existieren zur Person mit der Matrikelnummer, Datenverbund kontaktieren.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_ZD00030',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'ZD00030',
		'fehlertext' => 'Keine Stammdaten vor den Studiendaten gesendet, evtl. Folgefehler wegen Stammdaten Sendefehler.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_ZD10073',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'ZD10073',
		'fehlertext' => 'Matrikelnummer aus ungültigem Kontingent, Matrikelnummer mit Studiendaten abgleichen.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_ZD10074',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'ZD10074',
		'fehlertext' => 'Studienjahr der Matrikelnummer (zweite und dritte Stelle) passt nicht mit Semester des Gemeldeten überein, Matrikelnummer prüfen und evtl. neue vergeben.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_ZD10075',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'ZD10075',
		'fehlertext' => 'Personendaten stimmen nicht mit Datenverbund Daten überein, u.a. Vorname, Nachname, Geburtsdatum... überprüfen.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_ZD10076',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'ZD10076',
		'fehlertext' => 'Es gibt eine andere, bereits scharf geschaltete Matrikelnummer. Matrikelnummer mit DVUH abgleichen.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_ZD10077',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'ZD10077',
		'fehlertext' => 'Matrikelnummer aus Kontingent einer anderen Bildungseinrichtung, Matrikelnummer prüfen.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_ZD10078',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'ZD10078',
		'fehlertext' => 'Matrikelnummer gesperrt, Matrikelnummer prüfen, Datenverbund kontaktieren.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_YD21245',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'YD21245',
		'fehlertext' => 'Doppelmeldung, gleiche Daten für einen Studiengang doppelt gesendet, Korrektur der Studiendaten',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SPA_YD52608',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'YD52608',
		'fehlertext' => 'Keine Studiumsdatenmeldung vor Prüfungsaktivitätenmeldung, Studiumsmeldung durchführen.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_RE_EKZ_STATUS_2',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'EKZ_STATUS_2',
		'fehlertext' => 'mehrere Ersatzkennzeichen Personenkanditaten, erneute Anfrage mit korrektem Forcierungskey notwendig.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_RE_EKZ_STATUS_4',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'EKZ_STATUS_4',
		'fehlertext' => 'mehrere Ersatzkennzeichen Personenkanditaten, Stammdaten prüfen, Datenverbund kontaktieren.',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_RE_EKZ_STATUS_10',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'EKZ_STATUS_10',
		'fehlertext' => 'Fehler beim Holen vom Ersatzkennzeichen aufgetreten',
		'fehlertyp_kurzbz' => 'error',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	/* self-defined FHC Warnings */
	array(
		'fehlercode' => 'DVUH_SC_W_0001',
		'fehler_kurzbz' => 'andereBeBezahltSapGesendet',
		'fehlercode_extern' => null,
		'fehlertext' => 'Buchung %s ist in SAP gespeichert, obwohl ÖH-Beitrag bereits an anderer Bildungseinrichtung bezahlt wurde',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SC_W_0001',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SP_W_0001',
		'fehler_kurzbz' => 'vorgeschrBetragUngleichFestgesetzt',
		'fehlercode_extern' => null,
		'fehlertext' => 'Vorgeschriebener Beitrag %s nach Abzug der Versicherung stimmt nicht mit festgesetztem Betrag für Semester, %s, überein',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SP_W_0001',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SP_W_0002',
		'fehler_kurzbz' => 'zlgKeineVorschreibungGesendet',
		'fehlercode_extern' => null,
		'fehlertext' => 'Buchung %s: Zahlung nicht gesendet, vor der Zahlung wurde keine Vorschreibung an DVUH gesendet',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SP_W_0002',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SP_W_0003',
		'fehler_kurzbz' => 'offeneBuchungen',
		'fehlercode_extern' => null,
		'fehlertext' => 'Es gibt noch offene Buchungen',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SP_W_0003',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_W_0001',
		'fehler_kurzbz' => 'zgvFehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'ZGV fehlt',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_W_0001',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_W_0002',
		'fehler_kurzbz' => 'zgvDatumFehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'ZGV Datum fehlt',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_W_0002',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_W_0003',
		'fehler_kurzbz' => 'zgvMasterFehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'ZGV Master fehlt',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_W_0003',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_W_0004',
		'fehler_kurzbz' => 'zgvMasterDatumFehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'ZGV Masterdatum fehlt',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_W_0004',
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SS_W_0005',
		'fehler_kurzbz' => 'berufstaetigkeitcodeFehlt',
		'fehlercode_extern' => null,
		'fehlertext' => 'Berufstätigkeitcode fehlt',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => 'DVUH_SS_W_0005',
		'producerIsResolver' => false
	),
	/* DVUH Warnings */
	array(
		'fehlercode' => 'DVUH_SC_W_AD10065',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'AD10065',
		'fehlertext' => 'Bpk fehlt oder im Datenverbund anders, Bpk prüfen',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	),
	array(
		'fehlercode' => 'DVUH_SC_W_AD10208',
		'fehler_kurzbz' => null,
		'fehlercode_extern' => 'AD10208',
		'fehlertext' => 'SVNR oder Ersatzkennzeichen fehlt, prüfen und ergänzen',
		'fehlertyp_kurzbz' => 'warning',
		'app' => array('dvuh'),
		'producerLibName' => null,
		'resolverLibName' => null,
		'producerIsResolver' => false
	)
);