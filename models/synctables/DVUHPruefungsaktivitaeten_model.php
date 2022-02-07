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
	 * Checks if Prüfungsaktivitäten were sent for a person and semester.
	 * Ects erworben or ects angerechnet are checked.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz
	 * @return object
	 */
	public function checkIfPruefungsaktivitaetenSent($person_id, $studiensemester_kurzbz)
	{
		return $this->execQuery("
			SELECT 1
			FROM sync.tbl_dvuh_pruefungsaktivitaeten
			JOIN public.tbl_prestudent USING (prestudent_id)
			JOIN public.tbl_person USING (person_id)
			WHERE person_id = ?
			AND studiensemester_kurzbz = ?
			AND ((ects_erworben IS NOT NULL AND ects_erworben > 0) OR (ects_angerechnet IS NOT NULL AND ects_angerechnet > 0))",
			array($person_id, $studiensemester_kurzbz)
		);
	}
}
