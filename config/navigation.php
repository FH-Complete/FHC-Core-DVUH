<?php

// Add Menu-Entry to Main Page
$config['navigation_header']['*']['Administration']['children']['dvuh'] = array(
			'link' => site_url('extensions/FHC-Core-DVUH/DVUH'),
			'description' => 'Datenverbund',
			'expand' => true,
			'requiredPermissions' => 'admin:r'
);

// Add Menu-Entry to Extension Page
$config['navigation_menu']['extensions/FHC-Core-DVUH/*'] = array(
	'export' => array(
		'link' => site_url('extensions/FHC-Core-DVUH/DVUH'),
		'description' => 'Datenverbund',
		'icon' => 'vcard'
	),
	'codex' => array(
		'link' => site_url('extensions/FHC-Core-DVUH/Codex/index'),
		'description' => 'Codex',
		'icon' => 'user-secret'
	),
	'feeds' => array(
		'link' => site_url('extensions/FHC-Core-DVUH/FeedOverview/index'),
		'description' => 'Feedübersicht',
		'icon' => 'rss'
	)
);
