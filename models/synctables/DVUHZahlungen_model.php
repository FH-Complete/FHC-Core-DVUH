<?php

class DVUHZahlungen_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_dvuh_zahlungen';
		$this->pk = 'zahlung_id';
	}

	/**
	 * Get the latest charge sent to DVUH.
	 * @param int $buchungsnr
	 * @return success or error
	 */
	public function getLastCharge($buchungsnr)
	{
		return $this->execQuery(
								"SELECT betrag
								FROM sync.tbl_dvuh_zahlungen
								WHERE buchungsnr = ?
								AND betrag < 0
								ORDER BY buchungsdatum DESC, insertamum DESC
								LIMIT 1",
			array(
				$buchungsnr
			)
		);
	}
}
