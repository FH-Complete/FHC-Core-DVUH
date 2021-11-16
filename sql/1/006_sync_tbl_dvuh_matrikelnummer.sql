CREATE TABLE IF NOT EXISTS sync.tbl_dvuh_matrikelnummerreservierung (
    matrikelnummer varchar(32) NOT NULL,
    jahr smallint NOT NULL,
    insertamum timestamp DEFAULT now()
);

COMMENT ON TABLE sync.tbl_dvuh_matrikelnummerreservierung IS 'Table to save Matrikelnummer reserved in DVUH, to be assigned to students';
COMMENT ON COLUMN sync.tbl_dvuh_matrikelnummerreservierung.matrikelnummer IS 'the Matrikelnummer';
COMMENT ON COLUMN sync.tbl_dvuh_matrikelnummerreservierung.jahr IS 'year for which Mtrikelnummer was reserved';



DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_matrikelnummerreservierung ADD CONSTRAINT tbl_dvuh_matrikelnummerreservierung_pkey PRIMARY KEY (matrikelnummer, jahr);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_matrikelnummerreservierung ADD CONSTRAINT uk_dvuh_matrikelnummerreservierung_matrikelnummer UNIQUE (matrikelnummer);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_dvuh_matrikelnummerreservierung TO vilesci;
GRANT SELECT ON TABLE sync.tbl_dvuh_matrikelnummerreservierung TO web;
