<?php

/**
 * Functionality for writing errors and warnings.
 * Any library extending this library is capable of producing errors and warnings.
 */
abstract class ErrorProducerLib
{
	protected $_ci;
	protected $_errors = array();
	protected $_warnings = array();
	protected $_infos = array();

	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');
	}

	/**
	 * Adds info to info list.
	 * @param string $info
	 */
	protected function addInfo($info)
	{
		$this->_infos[] = $info;
	}

	/**
	 * Checks if at least one error was produced.
	 * @return bool
	 */
	public function hasError()
	{
		return !isEmptyArray($this->_errors);
	}

	/**
	 * Checks if at least one error was produced.
	 * @return bool
	 */
	public function hasWarning()
	{
		return !isEmptyArray($this->_warnings);
	}

	/**
	 * Checks if at least one info message was produced.
	 * @return bool
	 */
	public function hasInfo()
	{
		return !isEmptyArray($this->_infos);
	}

	/**
	 * Gets occured errors and resets them.
	 * @return array
	 */
	public function readErrors()
	{
		$errors = $this->_errors;
		$this->_errors = array();
		return $errors;
	}

	/**
	 * Gets occured warnings and resets them.
	 * @return array
	 */
	public function readWarnings()
	{
		$warnings = $this->_warnings;
		$this->_warnings = array();
		return $warnings;
	}

	/**
	 * Gets infos and resets them.
	 * @return array
	 */
	public function readInfos()
	{
		$infos = $this->_infos;
		$this->_infos = array();
		return $infos;
	}
}
