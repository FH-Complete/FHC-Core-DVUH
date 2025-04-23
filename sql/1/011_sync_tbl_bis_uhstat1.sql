CREATE TABLE IF NOT EXISTS sync.tbl_bis_uhstat1 (
	uhstat1_id bigint NOT NULL,
	uhstat1daten_id integer NOT NULL,
	gemeldetamum timestamp without time zone NOT NULL
);

COMMENT ON TABLE sync.tbl_bis_uhstat1 IS 'Table to save information about sent UHSTAT1 data';
COMMENT ON COLUMN sync.tbl_bis_uhstat1.uhstat1daten_id IS 'id of uhstat1 data sent';
COMMENT ON COLUMN sync.tbl_bis_uhstat1.gemeldetamum IS 'day and time on which uhstat data was sent';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_bis_uhstat1_uhstat1_id_seq
	INCREMENT BY 1
	NO MAXVALUE
	NO MINVALUE
	CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_bis_uhstat1_uhstat1_id_seq TO vilesci;

ALTER TABLE sync.tbl_bis_uhstat1 ALTER COLUMN uhstat1_id SET DEFAULT nextval('sync.tbl_bis_uhstat1_uhstat1_id_seq');

DO $$
	BEGIN
		ALTER TABLE sync.tbl_bis_uhstat1 ADD CONSTRAINT tbl_bis_uhstat1_pkey PRIMARY KEY (uhstat1_id);
	EXCEPTION WHEN OTHERS THEN NULL;
	END $$;

DO $$
	BEGIN
		ALTER TABLE sync.tbl_bis_uhstat1
			ADD CONSTRAINT tbl_bis_uhstat1_uhstat1daten_id_fkey FOREIGN KEY (uhstat1daten_id)
				REFERENCES bis.tbl_uhstat1daten(uhstat1daten_id) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
	END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_bis_uhstat1 TO vilesci;
GRANT SELECT ON TABLE sync.tbl_bis_uhstat1 TO web;
