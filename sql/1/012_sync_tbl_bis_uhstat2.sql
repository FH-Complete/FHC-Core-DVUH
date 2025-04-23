CREATE TABLE IF NOT EXISTS sync.tbl_bis_uhstat2 (
	uhstat2_id bigint NOT NULL,
	prestudent_id integer NOT NULL,
	gemeldetamum timestamp without time zone NOT NULL
);

COMMENT ON TABLE sync.tbl_bis_uhstat2 IS 'Table to save information about sent UHSTAT2 data';
COMMENT ON COLUMN sync.tbl_bis_uhstat2.gemeldetamum IS 'day and time on which uhstat data was sent';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_bis_uhstat2_uhstat2_id_seq
	INCREMENT BY 1
	NO MAXVALUE
	NO MINVALUE
	CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_bis_uhstat2_uhstat2_id_seq TO vilesci;

ALTER TABLE sync.tbl_bis_uhstat2 ALTER COLUMN uhstat2_id SET DEFAULT nextval('sync.tbl_bis_uhstat2_uhstat2_id_seq');

DO $$
	BEGIN
		ALTER TABLE sync.tbl_bis_uhstat2 ADD CONSTRAINT tbl_bis_uhstat2_pkey PRIMARY KEY (uhstat2_id);
	EXCEPTION WHEN OTHERS THEN NULL;
	END $$;

DO $$
	BEGIN
		ALTER TABLE sync.tbl_bis_uhstat2
			ADD CONSTRAINT tbl_bis_uhstat2_prestudent_id_fkey FOREIGN KEY (prestudent_id)
				REFERENCES public.tbl_prestudent(prestudent_id) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
	END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_bis_uhstat2 TO vilesci;
GRANT SELECT ON TABLE sync.tbl_bis_uhstat2 TO web;

