INSERT INTO system.tbl_berechtigung(berechtigung_kurzbz, beschreibung) VALUES
('extension/dvuh_gui_ekz_anfordern', 'Berechtigung für Abfage des Ersatzkennzeichens in der Datenverbund GUI')
ON CONFLICT (berechtigung_kurzbz) DO NOTHING;
