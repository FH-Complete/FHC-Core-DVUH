<?php

class DVUHMatrikelnummerreservierung_model extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_dvuh_matrikelnummerreservierung';
		$this->pk = array('matrikelnummer', 'jahr');
	}

	/**
	 * Inserts Matrikelnrreservierung in sync table if not already present.
	 * @param string $matrikelnummer
	 * @param string $jahr
	 * @return object success or error
	 */
	public function addMatrikelnummerreservierung($matrikelnummer, $jahr)
	{
		$matrnrReservedInFhc = $this->load(
			array(
				'matrikelnummer' => $matrikelnummer,
				'jahr' => $jahr
			)
		);

		if (isError($matrnrReservedInFhc))
			return $matrnrReservedInFhc;

		if (hasData($matrnrReservedInFhc))
		{
			return success(null);
		}
		else
		{
			return $this->insert(
				array(
					'matrikelnummer' => $matrikelnummer,
					'jahr' => $jahr
				)
			);
		}
	}
}
