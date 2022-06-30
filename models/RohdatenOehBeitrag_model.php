<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';
/**
 * Get list of Oehbeitraege
 */
class RohdatenOehBeitrag_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'rohdatenOehBeitrag';
	}

	/**
	 * Performs the Webservie Call
	 * @param string $be Code of the Bildungseinrichtung
	 * @param string $dateFrom
	 * @param string $dateTo
	 * @return object success or error
	 */
	public function get($be, $dateFrom, $dateTo)
	{
		if (isEmptyString($dateFrom))
		{
			$result = error('Vondatum nicht gesetzt');
		}
		elseif (isEmptyString($dateTo))
		{
			$result = error('Bisdatum nicht gesetzt');
		}
		else
		{
			$callParametersArray = array(
				'dateFrom' => $dateFrom,
				'dateTo' => $dateTo
			);

			if (!is_null($be))
				$callParametersArray['be'] = $be;

			$result = $this->_call('GET', $callParametersArray);
		}

		return $result;
	}
}
