<?php

/**
 * Job for producing DVUH issues
 */
class IssueProducer extends PlausiIssueProducer_Controller
{
	public function __construct()
	{
		parent::__construct();

		// set fehler which can be produced by the job
		$this->_fehlerKurzbz = array(
			'nichtGemeldeteStudierende'
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
