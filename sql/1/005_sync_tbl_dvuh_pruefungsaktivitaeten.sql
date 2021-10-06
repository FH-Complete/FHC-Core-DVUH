CREATE TABLE IF NOT EXISTS sync.tbl_dvuh_pruefungsaktivitaeten(
    pruefungsaktivitaeten_id bigint NOT NULL,
    prestudent_id integer NOT NULL,
    studiensemester_kurzbz varchar(16) NOT NULL,
    ects_angerechnet numeric(5,2),
    ects_erworben numeric(5,2),
    meldedatum date NOT NULL,
    insertamum timestamp DEFAULT now()
);

COMMENT ON TABLE sync.tbl_dvuh_pruefungsaktivitaeten IS 'Table to save information about Prüfungsaktivitäten (Zeugnisnoten) sent to DVUH';
COMMENT ON COLUMN sync.tbl_dvuh_pruefungsaktivitaeten.prestudent_id IS 'id of prestudent sent';
COMMENT ON COLUMN sync.tbl_dvuh_pruefungsaktivitaeten.studiensemester_kurzbz IS 'semester for which the Prüfungsaktivitäten were sent';
COMMENT ON COLUMN sync.tbl_dvuh_pruefungsaktivitaeten.ects_angerechnet IS 'amount of ects angerechnet sent';
COMMENT ON COLUMN sync.tbl_dvuh_pruefungsaktivitaeten.ects_erworben IS 'amount of ects erworben sent';
COMMENT ON COLUMN sync.tbl_dvuh_pruefungsaktivitaeten.meldedatum IS 'day on which masterdata was sent';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_dvuh_pruefungsaktivitaeten_pruefungsaktivitaeten_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_dvuh_pruefungsaktivitaeten_pruefungsaktivitaeten_id_seq TO vilesci;

ALTER TABLE sync.tbl_dvuh_pruefungsaktivitaeten ALTER COLUMN pruefungsaktivitaeten_id SET DEFAULT nextval('sync.tbl_dvuh_pruefungsaktivitaeten_pruefungsaktivitaeten_id_seq');

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_pruefungsaktivitaeten ADD CONSTRAINT tbl_dvuh_pruefungsaktivitaeten_pkey PRIMARY KEY (pruefungsaktivitaeten_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
    ALTER TABLE sync.tbl_dvuh_pruefungsaktivitaeten
        ADD CONSTRAINT tbl_dvuh_pruefungsaktivitaeten_prestudent_id_fkey FOREIGN KEY (prestudent_id)
            REFERENCES public.tbl_prestudent(prestudent_id) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_pruefungsaktivitaeten
            ADD CONSTRAINT tbl_dvuh_pruefungsaktivitaeten_studiensemester__kurzbz_fkey FOREIGN KEY (studiensemester_kurzbz)
                REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        CREATE INDEX idx_tbl_dvuh_pruefungsaktivitaeten_prestudent_id ON sync.tbl_dvuh_pruefungsaktivitaeten USING btree (prestudent_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_dvuh_pruefungsaktivitaeten TO vilesci;
GRANT SELECT ON TABLE sync.tbl_dvuh_pruefungsaktivitaeten TO web;
