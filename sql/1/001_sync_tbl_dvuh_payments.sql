CREATE TABLE IF NOT EXISTS sync.tbl_dvuh_zahlungen (
    zahlung_id bigint NOT NULL,
    zahlung_datum date NOT NULL,
    buchungsnr integer NOT NULL,
    betrag integer NOT NULL
);

COMMENT ON TABLE sync.tbl_dvuh_zahlungen IS 'Table to save charges and payments sent to DVUH';
COMMENT ON COLUMN sync.tbl_dvuh_zahlungen.zahlung_datum IS 'date of charge / payment sent to DVUH';
COMMENT ON COLUMN sync.tbl_dvuh_zahlungen.buchungsnr IS 'associated Buchung in FHC';
COMMENT ON COLUMN sync.tbl_dvuh_zahlungen.betrag IS 'amount charged / payed in cents';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_dvuh_zahlungen_zahlung_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_dvuh_zahlungen_zahlung_id_seq TO vilesci;

ALTER TABLE sync.tbl_dvuh_zahlungen ALTER COLUMN zahlung_id SET DEFAULT nextval('sync.tbl_dvuh_zahlungen_zahlung_id_seq');

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_zahlungen ADD CONSTRAINT tbl_dvuh_zahlungen_pkey PRIMARY KEY (zahlung_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_zahlungen
            ADD CONSTRAINT tbl_dvuh_zahlungen_buchungsnr_fkey FOREIGN KEY (buchungsnr)
                REFERENCES public.tbl_konto(buchungsnr) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_dvuh_zahlungen TO vilesci;
GRANT SELECT ON TABLE sync.tbl_dvuh_zahlungen TO web;
