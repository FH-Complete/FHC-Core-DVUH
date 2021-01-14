CREATE TABLE IF NOT EXISTS sync.tbl_dvuh_stammdaten (
    stammdaten_id bigint NOT NULL,
    person_id integer NOT NULL,
    studiensemester_kurzbz varchar(16) NOT NULL,
    meldedatum date NOT NULL,
    insertamum timestamp DEFAULT now()
);

COMMENT ON TABLE sync.tbl_dvuh_stammdaten IS 'Table to save information about masterdata sent to DVUH';
COMMENT ON COLUMN sync.tbl_dvuh_stammdaten.person_id IS 'id of person sent';
COMMENT ON COLUMN sync.tbl_dvuh_stammdaten.studiensemester_kurzbz IS 'semester for which the masterdata was sent';
COMMENT ON COLUMN sync.tbl_dvuh_stammdaten.meldedatum IS 'day on which masterdata was sent';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_dvuh_stammdaten_stammdaten_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_dvuh_stammdaten_stammdaten_id_seq TO vilesci;

ALTER TABLE sync.tbl_dvuh_stammdaten ALTER COLUMN stammdaten_id SET DEFAULT nextval('sync.tbl_dvuh_stammdaten_stammdaten_id_seq');

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_stammdaten ADD CONSTRAINT tbl_dvuh_stammdaten_pkey PRIMARY KEY (stammdaten_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_stammdaten
            ADD CONSTRAINT tbl_dvuh_stammdaten_person_id_fkey FOREIGN KEY (person_id)
                REFERENCES public.tbl_person(person_id) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_stammdaten
            ADD CONSTRAINT tbl_dvuh_stammdaten_studiensemester__kurzbz_fkey FOREIGN KEY (prestudent_id)
                REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_dvuh_stammdaten TO vilesci;
GRANT SELECT ON TABLE sync.tbl_dvuh_stammdaten TO web;
