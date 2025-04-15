<?php

require_once APPPATH.'libraries/extensions/FHC-Core-DVUH/uhstat/UHSTATErrorProducerLib.php';

/**
 * Contains logic for interaction of FHC with UHSTAT interface.
 * This includes initializing webservice calls for modifiying UHSTAT data.
 */
class UHSTATDataLib
{
	private $_dbModel;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_dbModel = new DB_Model(); // get db
	}

	// --------------------------------------------------------------------------------------------
	// Public methods
	/**
	 * Gets Mobilitaets data needed for UHSTAT2.
	 * @param $prestudentIdArr
	 * @param $abschlussStatusKurzbz
	 * @return object success or error
	 */
	public function getMobilitaetData($prestudentIdArr, $abschlussStatusKurzbz)
	{
		if (!isset($abschlussStatusKurzbz) || isEmptyArray($abschlussStatusKurzbz)) return error('Abschlussstatus missing');
		if (!isset($prestudentIdArr) || isEmptyArray($prestudentIdArr)) return success(array());

		$params = array($abschlussStatusKurzbz, $prestudentIdArr);

		$qry = "
			SELECT
				DISTINCT ON (aufenthaltsdauer_tage, bisio_id) *
			FROM
			(
				SELECT
					ps.person_id, ps.prestudent_id, bisio.bisio_id,
					bisio.nation_code, bisio.mobilitaetsprogramm_code, bisio.ects_erworben, bisio.ects_angerechnet, nat.nationengruppe_kurzbz,
					STRING_AGG(DISTINCT zweck.zweck_code::text, ',') AS zwecke,
					STRING_AGG(DISTINCT foerd.aufenthaltfoerderung_code::text, ',') AS foerderungen,
					bisio.von, bisio.bis,
					extract(day from bisio.bis::timestamp - bisio.von::timestamp) AS aufenthaltsdauer_tage,
					(
						SELECT
							datum
						FROM
							public.tbl_prestudentstatus
						WHERE
							status_kurzbz IN ?
							AND prestudent_id = ps.prestudent_id
						ORDER BY datum DESC LIMIT 1
					) AS abschlussdatum,
					pers.ersatzkennzeichen, kzVbpkAs.inhalt AS \"vbpkAs\", kzVbpkBf.inhalt AS \"vbpkBf\"
				FROM
					bis.tbl_bisio bisio
					JOIN public.tbl_student stud USING (student_uid)
					JOIN public.tbl_prestudent ps USING (prestudent_id)
					JOIN public.tbl_person pers USING (person_id)
					JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_Kz
					JOIN bis.tbl_nation nat ON bisio.nation_code = nat.nation_code
					LEFT JOIN bis.tbl_bisio_zweck zweck USING (bisio_id)
					LEFT JOIN bis.tbl_bisio_aufenthaltfoerderung foerd USING (bisio_id)
					LEFT JOIN public.tbl_kennzeichen kzVbpkAs
						ON kzVbpkAs.kennzeichentyp_kurzbz = 'vbpkAs'AND kzVbpkAs.person_id = pers.person_id AND kzVbpkAs.aktiv
					LEFT JOIN public.tbl_kennzeichen kzVbpkBf
						ON kzVbpkBf.kennzeichentyp_kurzbz = 'vbpkBf'AND kzVbpkBf.person_id = pers.person_id AND kzVbpkBf.aktiv
				WHERE
					prestudent_id IN ?
					AND ps.bismelden
					AND stg.melderelevant
				GROUP BY
					pers.ersatzkennzeichen, kzVbpkAs.inhalt, kzVbpkBf.inhalt, ps.prestudent_id, bisio_id, nat.nationengruppe_kurzbz
				ORDER BY
					bisio_id DESC
			) AS mobdata
			WHERE
				abschlussdatum IS NOT NULL
				AND bis IS NOT NULL
			ORDER BY
				aufenthaltsdauer_tage DESC, bisio_id";

		return $this->_dbModel->execReadOnlyQuery($qry, $params);
	}
}
