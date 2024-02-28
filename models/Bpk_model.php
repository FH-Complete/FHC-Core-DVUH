<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Request BPK of a Student
 */
class Bpk_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'bpk.xml';
	}

	/**
	 * Performs request to get bpk (works if Stammdaten have already been transferred).
	 * @param string $be
	 * @param string $semester
	 * @return object success or error
	 */
	public function get(
		$be,
		$semester
	) {
		if (isEmptyString($semester))
			$result = error('Semester nicht gesetzt');
		else
		{
			$callParametersArray = array(
				'uuid' => getUUID(),
				'be' => $be,
				'semester' => $semester
			);

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}
}
