<?php

/**
 * Job for resolving DVUH issues
 */
class IssueResolver extends IssueResolver_Controller
{
	public function __construct()
	{
		parent::__construct();

		// set fehler codes which can be resolved by the job
		$this->_fehlercodes = array(
			'DVUH_SC_0001',
			'DVUH_SC_0002',
			'DVUH_SC_0003',
			'DVUH_SC_0004',
			'DVUH_SC_0005',
			'DVUH_SC_0008',
			'DVUH_SC_0009',
			'DVUH_SC_0010',
			'DVUH_SC_0011',
			'DVUH_SC_0012',
			'DVUH_SC_0013',
			'DVUH_SP_0001',
			'DVUH_SP_W_0001',
			'DVUH_SP_W_0002',
			'DVUH_SP_W_0003',
			'DVUH_SS_0001',
			'DVUH_SS_0004',
			'DVUH_SS_0014',
			'DVUH_SS_0015',
			'DVUH_SS_0016',
			'DVUH_SS_0018',
			'DVUH_SS_W_0001',
			'DVUH_SS_W_0002',
			'DVUH_SS_W_0003',
			'DVUH_SS_W_0004',
			'DVUH_SS_W_0005',
			'DVUH_RE_0001'
		);
	}
}
