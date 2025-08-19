<?php

/**
 * Job for producing DVUH issues
 */
class IssueProducer extends PlausiIssueProducer_Controller
{
	protected $_extensionName = 'FHC-Core-DVUH'; // name of extension for file path

	public function __construct()
	{
		parent::__construct();

		// set fehler which can be produced by the job
		// structure: fehler_kurzbz => class (library) name for resolving
		$this->_fehlerLibMappings = array(
			'nichtGemeldeteStudierende' => 'NichtGemeldeteStudierende'
		);
	}

	/**
	 * Runs issue production job.
	 */
	public function run()
	{
		// producing issues
		$this->producePlausicheckIssues(array());
	}
}
