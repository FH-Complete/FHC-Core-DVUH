<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * Correct the Matrikelnummer
 */
class Matrikelkorrektur_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'matrikelkorrektur.xml';
	}

	/**
	 * Correct Matrikelnummer
	 *
	 * @param $matrikelnummer Matrikelnummer
	 * @param $be Code of Bildungseinrichtung
	 * @param $semester Semester
	 * @param $matrikelalt Old Matrikelnummer
	 */
	public function post($be, $matrikelnummer, $semester, $matrikelalt)
	{
		if (isEmptyString($matrikelnummer))
			$result = error('Matrikelnummer nicht gesetzt');
		elseif(isEmptyString($semester))
			$result = error('Semester nicht gesetzt');
		elseif(isEmptyString($matrikelalt))
			$result = error('Matrikelnr alt nicht gesetzt');
		else
		{

			$params = array(
				"uuid" => getUUID(),
				"matrikelnummer" => $matrikelnummer,
				"be" => $be,
				"semester" => $semester,
				"matrikelalt" => $matrikelalt
			);

			$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/matrikelkorrektur', $params, true);
			$result = $this->_call('POST', null, $postData);
		}

		return $result;
	}
}
