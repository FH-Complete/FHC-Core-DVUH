<?php

class DVUHPruefungsaktivitaeten_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_dvuh_pruefungsaktivitaeten';
		$this->pk = 'pruefungsaktivitaeten_id';
	}

	/**
	 * Get the latest Prüfungsaktivität sent to DVUH.
	 * @param int $prestudent_id
	 * @param string $studiensemester_kurzbz
	 * @return success or error
	 */
	public function getLastSentPruefungsaktivitaet($prestudent_id, $studiensemester_kurzbz)
	{
		return $this->execQuery("
			SELECT ects_angerechnet, ects_erworben
			FROM sync.tbl_dvuh_pruefungsaktivitaeten pa
			JOIN public.tbl_prestudent USING (prestudent_id)
			WHERE prestudent_id = ?
			AND studiensemester_kurzbz = ?
			ORDER BY pa.meldedatum DESC, pa.insertamum DESC NULLS LAST, pruefungsaktivitaeten_id DESC
			LIMIT 1",
			array($prestudent_id, $studiensemester_kurzbz)
		);
	}
}
