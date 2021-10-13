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
	 * @param string $matrikelnummer Matrikelnummer
	 * @param string $be Code of Bildungseinrichtung
	 * @param string $semester Semester
	 * @param string $matrikelalt Old Matrikelnummer
	 * @return object success or error
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
