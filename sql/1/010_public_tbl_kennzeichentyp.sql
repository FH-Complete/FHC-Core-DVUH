INSERT INTO public.tbl_kennzeichentyp (kennzeichentyp_kurzbz, bezeichnung, aktiv) VALUES
('vbpkAs', 'Statistik vBpk', TRUE),
('vbpkBf', 'Bildung vBpk', TRUE),
('vbpkTd', 'Transparenzdatenbank vBpk', TRUE)
ON CONFLICT (kennzeichentyp_kurzbz) DO NOTHING;
