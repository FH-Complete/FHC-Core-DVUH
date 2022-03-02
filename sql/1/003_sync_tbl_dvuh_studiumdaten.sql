CREATE TABLE IF NOT EXISTS sync.tbl_dvuh_studiumdaten (
    studiumdaten_id bigint NOT NULL,
    prestudent_id integer NOT NULL,
    studiensemester_kurzbz varchar(16) NOT NULL,
    meldedatum date NOT NULL,
    insertamum timestamp DEFAULT now()
);

COMMENT ON TABLE sync.tbl_dvuh_studiumdaten IS 'Table to save information about studydata sent to DVUH';
COMMENT ON COLUMN sync.tbl_dvuh_studiumdaten.prestudent_id IS 'id of prestudent sent';
COMMENT ON COLUMN sync.tbl_dvuh_studiumdaten.studiensemester_kurzbz IS 'semester for which the studydata was sent';
COMMENT ON COLUMN sync.tbl_dvuh_studiumdaten.meldedatum IS 'day on which studiumdata was sent';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_dvuh_studiumdaten_studiumdaten_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_dvuh_studiumdaten_studiumdaten_id_seq TO vilesci;

ALTER TABLE sync.tbl_dvuh_studiumdaten ALTER COLUMN studiumdaten_id SET DEFAULT nextval('sync.tbl_dvuh_studiumdaten_studiumdaten_id_seq');

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_studiumdaten ADD CONSTRAINT tbl_dvuh_studiumdaten_pkey PRIMARY KEY (studiumdaten_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_studiumdaten
            ADD CONSTRAINT tbl_dvuh_studiumdaten_prestudent_id_fkey FOREIGN KEY (prestudent_id)
                REFERENCES public.tbl_prestudent(prestudent_id) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_studiumdaten
            ADD CONSTRAINT tbl_dvuh_studiumdaten_studiensemester__kurzbz_fkey FOREIGN KEY (studiensemester_kurzbz)
                REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_dvuh_studiumdaten TO vilesci;
GRANT SELECT ON TABLE sync.tbl_dvuh_studiumdaten TO web;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_studiumdaten ADD COLUMN storniert boolean NOT NULL DEFAULT false;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;
