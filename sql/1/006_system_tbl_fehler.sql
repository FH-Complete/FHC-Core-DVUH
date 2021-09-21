INSERT INTO system.tbl_fehler (fehlercode, fehler_kurzbz, fehlercode_extern, fehlertext, fehlertyp_kurzbz, app) VALUES
/* self-defined FHC Errors */
('DVUH_SC_0001', 'matrNrFehlt', NULL, 'Matrikelnummer nicht gesetzt', 'error', 'dvuh'),
('DVUH_SC_0002', 'keineZustelladresse', NULL, 'Keine Zustelladresse angegeben!', 'error', 'dvuh'),
('DVUH_SC_0003', 'keineHeimatadresse', NULL, 'Keine Heimatadresse angegeben!', 'error', 'dvuh'),
('DVUH_SC_0004', 'adresseUngueltig', NULL, 'Adresse ungültig: %s', 'error', 'dvuh'),
('DVUH_SC_0005', 'stammdatenFehlen', NULL, 'Stammdaten fehlen: %s', 'error', 'dvuh'),
('DVUH_SC_0006', 'ungueltigeSonderzeichen', NULL, '%s enthält ungültige Sonderzeichen', 'error', 'dvuh'),
('DVUH_SC_0007', 'emailEnthaeltSonderzeichen', NULL, 'Email enthält Sonderzeichen', 'error', 'dvuh'),
('DVUH_SC_0008', 'oehbeitragNichtSpezifiziert', NULL, 'Keine Höhe des Öhbeiträgs in Öhbeitragstabelle für Studiensemester %s spezifiziert, Buchung %s', 'error', 'dvuh'),
('DVUH_SP_0001', 'offeneBuchungen', NULL, 'Es gibt noch offene Buchungen', 'error', 'dvuh'),
('DVUH_SP_0002', 'zlgUngleichVorschreibung', NULL, 'Buchung: %s: Zahlungsbetrag abweichend von Vorschreibungsbetrag', 'error', 'dvuh'),
('DVUH_SS_0001', 'matrikelnrUngueltig', NULL, 'Matrikelnummer ungültig (%s)', 'error', 'dvuh'),
('DVUH_SS_0002', 'fehlerhafteZgvDaten', NULL, 'Fehlerhafte ZGV Daten: %s', 'error', 'dvuh'),
('DVUH_SS_0003', 'fehlerhafteZgvMasterDaten', NULL, 'Fehlerhafte ZGV Master Daten: %s', 'error', 'dvuh'),
('DVUH_SS_0004', 'personenkennzeichenUngueltig', NULL, 'Personenkennzeichen ungültig (%s)', 'error', 'dvuh'),
('DVUH_SS_0005', 'zuVieleZweckeIncoming', NULL, 'Es sind %s Aufenthaltszwecke eingetragen (max. 1 Zweck für Incomings)', 'error', 'dvuh'),
('DVUH_SS_0006', 'falscherIncomingZweck', NULL, 'Aufenthaltszweckcode ist %s (für Incomings ist nur Zweck 1, 2, 3 erlaubt)', 'error', 'dvuh'),
('DVUH_SS_0007', 'falscherIncomingZweckGemeinsam', NULL, 'Aufenthaltzweckcode 1, 2, 3 dürfen nicht gemeinsam gemeldet werden', 'error', 'dvuh'),
('DVUH_SS_0008', 'outgoingAufenthaltfoerderungfehlt', NULL, 'Keine Aufenthaltsfoerderung angegeben (bei Outgoings >= 29 Tage Monat im Ausland muss mind. 1 gemeldet werden)', 'error', 'dvuh'),
('DVUH_SS_0009', 'outgoingAngerechneteEctsFehlen', NULL, 'Angerechnete ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)', 'error', 'dvuh'),
('DVUH_SS_0010', 'outgoingErworbeneEctsFehlen', NULL, 'Erworbene ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)', 'error', 'dvuh'),
('DVUH_SS_0011', 'lehrgangdatenFehlen', NULL, 'Lehrgangdaten fehlen: %s', 'error', 'dvuh'),
('DVUH_SS_0012', 'studiumdatenFehlen', NULL, 'Studiumdaten fehlen: %s', 'error', 'dvuh'),
/* DVUH Errors */
('DVUH_ERROR', 'dvuhFehler', NULL, 'DVUH Fehler ist aufgetreten', 'error', 'dvuh'),
('DVUH_SC_ZD10075', NULL, 'ZD10075', 'DVUH Fehler ist aufgetreten', 'error', 'dvuh'),
('DVUH_SC_ZD10076', NULL, 'ZD10076', 'DVUH Fehler ist aufgetreten', 'error', 'dvuh'),
('DVUH_SC_ZD10077', NULL, 'ZD10077', 'DVUH Fehler ist aufgetreten', 'error', 'dvuh'),
('DVUH_SS_ZD00030', NULL, 'ZD00030', 'DVUH Fehler ist aufgetreten', 'error', 'dvuh')
ON CONFLICT (fehlercode, fehler_kurzbz) DO NOTHING;
/*
	'zlgKeineVorschreibungGesendet' => array('code' => 'DVUH_SP_0003', 'text' => 'Buchung %s: Zahlung nicht gesendet, vor der Zahlung wurde keine Vorschreibung an DVUH gesendet'),*/
