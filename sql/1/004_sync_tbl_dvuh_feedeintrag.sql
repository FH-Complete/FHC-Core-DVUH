CREATE TABLE IF NOT EXISTS sync.tbl_dvuh_feedeintrag (
    feedeintrag_id bigint,
    id varchar(64) NOT NULL UNIQUE,
    title varchar(64) NOT NULL,
    author varchar(64) NOT NULL,
    published timestamp NOT NULL,
    content xml,
    verarbeitetamum timestamp,
    verarbeitetvon varchar(32),
    anmerkung text,
    insertamum timestamp DEFAULT NOW()
);

COMMENT ON TABLE sync.tbl_dvuh_feedeintrag IS 'Table to save feedentries coming from DVUH for processing';
COMMENT ON COLUMN sync.tbl_dvuh_feedeintrag.id IS 'alphanumeric feed entry id, with hyphen separators';
COMMENT ON COLUMN sync.tbl_dvuh_feedeintrag.title IS 'feed title, corresponds to feed type';
COMMENT ON COLUMN sync.tbl_dvuh_feedeintrag.author IS 'author of the feed, can be an organisation';
COMMENT ON COLUMN sync.tbl_dvuh_feedeintrag.content IS 'xml feed content';
COMMENT ON COLUMN sync.tbl_dvuh_feedeintrag.verarbeitetamum IS 'time and date when feed entry was processed on FHC side';
COMMENT ON COLUMN sync.tbl_dvuh_feedeintrag.verarbeitetvon IS 'uid or name of person or system who/which processed feed entry on FHC side';
COMMENT ON COLUMN sync.tbl_dvuh_feedeintrag.anmerkung IS 'optional note providing info about processing of feed entry on FHC side';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_dvuh_feedeintrag_feedeintrag_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_dvuh_feedeintrag_feedeintrag_id_seq TO vilesci;

ALTER TABLE sync.tbl_dvuh_feedeintrag ALTER COLUMN feedeintrag_id SET DEFAULT nextval('sync.tbl_dvuh_feedeintrag_feedeintrag_id_seq');

DO $$
    BEGIN
        ALTER TABLE sync.tbl_dvuh_feedeintrag ADD CONSTRAINT tbl_dvuh_feedeintrag_pkey PRIMARY KEY (feedeintrag_id);
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_dvuh_feedeintrag TO vilesci;
GRANT SELECT ON TABLE sync.tbl_dvuh_feedeintrag TO web;
