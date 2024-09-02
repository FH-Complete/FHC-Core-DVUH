INSERT INTO system.tbl_jobtypes (type, description) VALUES
('DVUHRequestMatrikelnummer', 'Request Matrikelnummer from Datenverbund'),
('DVUHSendCharge', 'Send student Stammdaten with payment charge to Datenverbund'),
('DVUHSendPayment', 'Send student payment to Datenverbund'),
('DVUHSendStudyData', 'Send final data of enrolled students'),
('DVUHRequestBpk', 'Request Bpk from Datenverbund'),
('DVUHRequestEkz', 'Request Ekz from Datenverbund'),
('DVUHSendPruefungsaktivitaeten', 'Send Prüfungsaktivitäten (Zeugnisnoten) to Datenverbund'),
('DVUHGetBpk', 'Get Bpk from Datenverbund (without a request to Stammzahlenregister)')
ON CONFLICT (type) DO NOTHING;
