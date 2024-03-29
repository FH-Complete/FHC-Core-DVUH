INSERT INTO system.tbl_fehler (fehlercode, fehler_kurzbz, fehlercode_extern, fehlertext, fehlertyp_kurzbz, app) VALUES
/* self-defined FHC Errors */
('DVUH_SM_0001', 'nameUndGebdatumAngeben', NULL, 'Wenn der Name angegeben ist muss auch ein Geburtsdatum angegeben werden', 'error', 'dvuh'),
('DVUH_SC_0001', 'matrNrFehlt', NULL, 'Matrikelnummer nicht gesetzt', 'error', 'dvuh'),
('DVUH_SC_0002', 'keineZustelladresse', NULL, 'Keine Zustelladresse angegeben', 'error', 'dvuh'),
('DVUH_SC_0003', 'keineHeimatadresse', NULL, 'Keine Heimatadresse angegeben', 'error', 'dvuh'),
('DVUH_SC_0004', 'adresseUngueltig', NULL, 'Adresse ungültig: %s', 'error', 'dvuh'),
('DVUH_SC_0005', 'ersatzkennzeichenUngueltig', NULL, 'Ersatzkennzeichen ungültig, muss aus 4 Grossbuchstaben gefolgt von 6 Zahlen bestehen', 'error', 'dvuh'),
('DVUH_SC_0006', 'stammdatenFehlen', NULL, 'Stammdaten fehlen: %s', 'error', 'dvuh'),
('DVUH_SC_0007', 'ungueltigeSonderzeichen', NULL, '%s enthält ungültige Sonderzeichen', 'error', 'dvuh'),
('DVUH_SC_0008', 'emailEnthaeltSonderzeichen', NULL, 'Email enthält Sonderzeichen', 'error', 'dvuh'),
('DVUH_SC_0009', 'oehbeitragNichtSpezifiziert', NULL, 'Keine Höhe des Öhbeiträgs in Öhbeitragstabelle für Studiensemester %s spezifiziert, Buchung %s', 'error', 'dvuh'),
('DVUH_SC_0010', 'bpkUngueltig', NULL, 'BPK ungültig, muss aus 27 Zeichen (alphanum. mit / +) gefolgt von = bestehen', 'error', 'dvuh'),
('DVUH_SC_0011', 'titelpreUngueltig', NULL, 'Titel pre hat ungültiges Format', 'error', 'dvuh'),
('DVUH_SC_0012', 'titelpostUngueltig', NULL, 'Titel post hat ungültiges Format', 'error', 'dvuh'),
('DVUH_SC_0013', 'vorschreibungUngueltig', NULL, 'Vorschreibung ungültig, Zahlungstypen: %s', 'error', 'dvuh'),
('DVUH_SP_0001', 'zlgUngleichVorschreibung', NULL, 'Buchung: %s: Zahlungsbetrag abweichend von Vorschreibungsbetrag', 'error', 'dvuh'),
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
('DVUH_SS_0013', 'ungueltigeMeldeStudiengangskennzahl', NULL, 'Ungültige Meldestudiengangskennzahl für Studiengang %s, gültiges Format: (3 Stellen für Erhalter wenn Lehrgang) [4 Stellen Studiengang]', 'error', 'dvuh'),
('DVUH_SS_0014', 'studienkennunguniUngueltig', NULL, 'Ungültige Studienkennung Uni für GS mit Programmcode %s, muss z.B. UUT190593347UA sein', 'error', 'dvuh'),
('DVUH_SS_0015', 'herkunftslandFehlt', NULL, 'Herkunftsland fehlt', 'error', 'dvuh'),
('DVUH_SS_0016', 'gsdatenFehlen', NULL, 'Daten für gemeinsames Studium fehlen: %s', 'error', 'dvuh'),
('DVUH_SS_0017', 'orgformUngueltig', NULL, 'Orgform ungültig', 'error', 'dvuh'),
('DVUH_RE_0001', 'personMitEkzExistiert', NULL, 'Person (person Id %s) mit EKZ %s existiert bereits', 'error', 'dvuh'),
/* DVUH Errors */
('DVUH_ERROR', 'dvuhFehler', NULL, 'DVUH Fehler ist aufgetreten', 'error', 'dvuh'),
('DVUH_SM_MATRNR_STATUS_2', NULL, 'MATRNR_STATUS_2', 'Matrikelnummer gesperrt, Matrikelnummer prüfen, Datenverbund kontaktieren.', 'error', 'dvuh'),
('DVUH_SM_MATRNR_STATUS_4', NULL, 'MATRNR_STATUS_4', 'Aktive, noch nicht scharfgeschaltene Matrikelnummer an einer Bildungseinrichtung. In Evidenz halten, bis Student an einer Bildungseinrichtung scharf geschalten ist.', 'error', 'dvuh'),
('DVUH_SM_MATRNR_STATUS_6', NULL, 'MATRNR_STATUS_6', 'Zwei Datensätze existieren zur Person mit der Matrikelnummer, Datenverbund kontaktieren.', 'error', 'dvuh'),
('DVUH_SS_ZD00030', NULL, 'ZD00030', 'Keine Stammdaten vor den Studiendaten gesendet, evtl. Folgefehler wegen Stammdaten Sendefehler.', 'error', 'dvuh'),
('DVUH_SC_ZD10073', NULL, 'ZD10073', 'Matrikelnummer aus ungültigem Kontingent, Matrikelnummer mit Studiendaten abgleichen.', 'error', 'dvuh'),
('DVUH_SS_ZD10074', NULL, 'ZD10074', 'Studienjahr der Matrikelnummer (zweite und dritte Stelle) passt nicht mit Semester des Gemeldeten überein, Matrikelnummer prüfen und evtl. neue vergeben.', 'error', 'dvuh'),
('DVUH_SC_ZD10075', NULL, 'ZD10075', 'Personendaten stimmen nicht mit Datenverbund Daten überein, u.a. Vorname, Nachname, Geburtsdatum... überprüfen.', 'error', 'dvuh'),
('DVUH_SC_ZD10076', NULL, 'ZD10076', 'Es gibt eine andere, bereits scharf geschaltete Matrikelnummer. Matrikelnummer mit DVUH abgleichen.', 'error', 'dvuh'),
('DVUH_SC_ZD10077', NULL, 'ZD10077', 'Matrikelnummer aus Kontingent einer anderen Bildungseinrichtung, Matrikelnummer prüfen.', 'error', 'dvuh'),
('DVUH_SS_ZD10078', NULL, 'ZD10078', 'Matrikelnummer gesperrt, Matrikelnummer prüfen, mit Datenverbund abklären.', 'error', 'dvuh'),
('DVUH_YD21245', NULL, 'YD21245', 'Doppelmeldung, gleiche Daten für einen Studiengang doppelt gesendet, Korrektur der Studiendaten', 'error', 'dvuh'),
('DVUH_SPA_YD52608', NULL, 'YD52608', 'Keine Studiumsdatenmeldung vor Prüfungsaktivitätenmeldung, Studiumsmeldung durchführen.', 'error', 'dvuh'),
('DVUH_RE_EKZ_STATUS_2', NULL, 'EKZ_STATUS_2', 'mehrere Ersatzkennzeichen Personenkanditaten, erneute Anfrage mit korrektem Forcierungskey notwendig.', 'error', 'dvuh'),
('DVUH_RE_EKZ_STATUS_4', NULL, 'EKZ_STATUS_4', 'mehrere Ersatzkennzeichen Personenkanditaten, Stammdaten prüfen, Datenverbund kontaktieren.', 'error', 'dvuh'),
('DVUH_RE_EKZ_STATUS_10', NULL, 'EKZ_STATUS_10', 'Fehler beim Holen vom Ersatzkennzeichen aufgetreten', 'error', 'dvuh'),
/* self-defined FHC Warnings */
('DVUH_SC_W_0001', 'andereBeBezahltSapGesendet', NULL, 'Buchung %s ist in SAP gespeichert, obwohl ÖH-Beitrag bereits an anderer Bildungseinrichtung bezahlt wurde', 'warning', 'dvuh'),
('DVUH_SP_W_0001', 'vorgeschrBetragUngleichFestgesetzt', NULL, 'Vorgeschriebener Beitrag %s nach Abzug der Versicherung stimmt nicht mit festgesetztem Betrag für Semester, %s, überein', 'warning', 'dvuh'),
('DVUH_SP_W_0002', 'zlgKeineVorschreibungGesendet', NULL, 'Buchung %s: Zahlung nicht gesendet, vor der Zahlung wurde keine Vorschreibung an DVUH gesendet', 'warning', 'dvuh'),
('DVUH_SP_W_0003', 'offeneBuchungen', NULL, 'Es gibt noch offene Buchungen', 'warning', 'dvuh'),
('DVUH_SS_W_0001', 'zgvFehlt', NULL, 'ZGV fehlt', 'warning', 'dvuh'),
('DVUH_SS_W_0002', 'zgvDatumFehlt', NULL, 'ZGV Datum fehlt', 'warning', 'dvuh'),
('DVUH_SS_W_0003', 'zgvMasterFehlt', NULL, 'ZGV Master fehlt', 'warning', 'dvuh'),
('DVUH_SS_W_0004', 'zgvMasterDatumFehlt', NULL, 'ZGV Masterdatum fehlt', 'warning', 'dvuh'),
('DVUH_SS_W_0005', 'berufstaetigkeitcodeFehlt', NULL, 'Berufstätigkeitcode fehlt', 'warning', 'dvuh'),
/* DVUH Warnings */
('DVUH_SC_W_AD10065', NULL, 'AD10065', 'Bpk fehlt oder im Datenverbund anders, Bpk prüfen', 'warning', 'dvuh'),
('DVUH_SC_W_AD10208', NULL, 'AD10208', 'SVNR oder Ersatzkennzeichen fehlt, prüfen und ergänzen', 'warning', 'dvuh')
ON CONFLICT (fehlercode) DO NOTHING;
