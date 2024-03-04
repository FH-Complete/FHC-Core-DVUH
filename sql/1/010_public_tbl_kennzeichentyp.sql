INSERT INTO public.tbl_kennzeichentyp (kennzeichentyp_kurzbz, bezeichnung, aktiv) VALUES
('vbpkAs', 'Statistik vBpk', TRUE)
ON CONFLICT (kennzeichentyp_kurzbz) DO NOTHING;
