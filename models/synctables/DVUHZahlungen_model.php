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
			"SELECT dvuh_zlg.betrag, dvuh_zlg.buchungsnr
			FROM sync.tbl_dvuh_zahlungen dvuh_zlg
			JOIN public.tbl_konto kto USING (buchungsnr)
			WHERE buchungsnr = ?
			AND kto.buchungsnr_verweis IS NULL
			AND dvuh_zlg.betrag <= 0
			ORDER BY dvuh_zlg.buchungsdatum DESC, dvuh_zlg.insertamum DESC NULLS LAST, dvuh_zlg.zahlung_id DESC
			LIMIT 1",
			array(
				$buchungsnr
			)
		);
	}
}
