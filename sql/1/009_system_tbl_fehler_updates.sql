UPDATE system.tbl_fehler SET fehler_kurzbz = NULL WHERE fehlercode = 'DVUH_YD21245' AND fehler_kurzbz IS NOT NULL;
UPDATE system.tbl_fehler SET fehlertext = 'Bpk oder Ersatzkennzeichen fehlt, prüfen und ergänzen' WHERE fehlercode = 'DVUH_SC_W_AD10208';
