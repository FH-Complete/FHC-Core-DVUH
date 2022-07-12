<?php

/**
 * Job for resolving DVUH issues
 */
class IssueResolver extends IssueResolver_Controller
{
	protected $_extensionName = 'FHC-Core-DVUH'; // name of extension for file path

	public function __construct()
	{
		parent::__construct();

		// set fehler codes which can be resolved by the job
		// structure: fehlercode => class (library) name for resolving
		$this->_codeLibMappings = array(
			'DVUH_SC_0001' => 'DVUH_SC_0001',
			'DVUH_SC_0002' => 'DVUH_SC_0002',
			'DVUH_SC_0003' => 'DVUH_SC_0003',
			'DVUH_SC_0004' => 'DVUH_SC_0004',
			'DVUH_SC_0005' => 'DVUH_SC_0005',
			'DVUH_SC_0008' => 'DVUH_SC_0008',
			'DVUH_SC_0009' => 'DVUH_SC_0009',
			'DVUH_SC_0010' => 'DVUH_SC_0010',
			'DVUH_SP_0001' => 'DVUH_SP_0001',
			'DVUH_SP_W_0001' => 'DVUH_SP_W_0001',
			'DVUH_SP_W_0002' => 'DVUH_SP_W_0002',
			'DVUH_SP_W_0003' => 'DVUH_SP_W_0003',
			'DVUH_SS_0001' => 'DVUH_SS_0001',
			'DVUH_SS_0004' => 'DVUH_SS_0004',
			'DVUH_SS_0014' => 'DVUH_SS_0014',
			'DVUH_SS_W_0001' => 'DVUH_SS_W_0001',
			'DVUH_SS_W_0002' => 'DVUH_SS_W_0002',
			'DVUH_SS_W_0003' => 'DVUH_SS_W_0003',
			'DVUH_SS_W_0004' => 'DVUH_SS_W_0004',
			'DVUH_SS_W_0005' => 'DVUH_SS_W_0005'
		);
	}
}
