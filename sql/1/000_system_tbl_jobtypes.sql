INSERT INTO system.tbl_jobtypes (type, description) VALUES
('DVUHRequestMatrikelnummer', 'Request Matrikelnummer from Datenverbund'),
('DVUHSendCharge', 'Send student Stammdaten with payment charge to Datenverbund'),
('DVUHSendPayment', 'Send student payment to Datenverbund'),
('DVUHSendStudyData', 'Send final data of enrolled students'),
('DVUHRequestBpk', 'Request Bpk from Datenverbund'),
('DVUHSendPruefungsaktivitaeten', 'Send Prüfungsaktivitäten (Zeugnisnoten) to Datenverbund')
ON CONFLICT (type) DO NOTHING;