<?php

require_once APPPATH.'models/extensions/FHC-Core-DVUH/UHSTATClientModel.php';

/**
 * Implements the UHSTAT webservice calls for UHSTAT2
 */
class UHSTAT2Model extends UHSTATClientModel
{
	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_url = 'rest/uhstat2';
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Checks if an UHSTAT2 entry exists for a student
	 */
	public function checkEntry($persIdType, $persId, $studierendeJahr, $studierendeMonat)
	{
		return $this->_call(
			ClientLib::HTTP_HEAD_METHOD,
			array($persIdType, $persId, $studierendeJahr, $studierendeMonat)
		);
	}

	/**
	 * Get data of an UHSTAT2 entry
	 */
	public function getEntry($persIdType, $persId, $studierendeJahr, $studierendeMonat)
	{
		return $this->_call(
			ClientLib::HTTP_GET_METHOD,
			array($persIdType, $persId, $studierendeJahr, $studierendeMonat)
		);
	}

	/**
	 * Adds or updates an UHSTAT2 entry for a student
	 */
	public function saveEntry($persIdType, $persId, $studierendeJahr, $studierendeMonat, $studentDataBodyParams)
	{
		return $this->_call(
			ClientLib::HTTP_PUT_METHOD,
			array($persIdType, $persId, $studierendeJahr, $studierendeMonat),
			$studentDataBodyParams
		);
	}
}
