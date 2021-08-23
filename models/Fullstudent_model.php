<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';
/**
 * Get Information about Students
 */
class Fullstudent_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'fullstudent.xml';
	}

	/**
	 * Performs the Webservie Call
	 *
	 * @param $matrikelnummer Matrikelnummer of the Person you are Searching for
	 * @param $be Code of the Bildungseinrichtung (optional)
	 * @param $semester Studysemester in format 2019W
	 */
	public function get($matrikelnummer, $be = null, $semester = null)
	{
		if (isEmptyString($matrikelnummer))
		{
			$result = error('Matrikelnummer nicht gesetzt');
		}
		else
		{
			$callParametersArray = array(
				'matrikelnummer' => $matrikelnummer,
				'uuid' => getUUID()
			);

			if (!is_null($be))
				$callParametersArray['be'] = $be;
			if (!is_null($semester))
				$callParametersArray['semester'] = $semester;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}
}
