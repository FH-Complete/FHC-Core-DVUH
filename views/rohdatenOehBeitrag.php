<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'ÖH-Beitragsliste',
		'jquery3' => true,
		'jqueryui1' => true,
		'bootstrap3' => true,
		'fontawesome4' => true,
		'navigationwidget' => true,
		'sbadmintemplate3' => true,
		'tablesorter2' => true,
		'dialoglib' => true,
		'ajaxlib' => true,
		'customCSSs' => 'public/css/sbadmin2/tablesort_bootstrap.css',
		'customJSs' => array(
			'public/js/tablesort/tablesort.js',
			'public/extensions/FHC-Core-DVUH/js/DVUHLib.js',
			'public/extensions/FHC-Core-DVUH/js/rohdatenOehBeitrag.js'
		)
	)
);
?>

<body>
<div id="wrapper">
	<?php
	echo $this->widgetlib->widget('NavigationWidget');
	?>
	<div id="page-wrapper">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xs-12">
					<h3 class="page-header">ÖH-Beitragsliste</h3>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12 form-inline">
					<div class="form-group">
						<label>
							Datum von:
						</label>
						<input type="text" class="form-control" id="dateFrom">
					</div>
					&nbsp;
					<div class="form-group">
						<label>
							Datum bis:
						</label>
						<input type="text" class="form-control" id="dateTo">
					</div>
					&nbsp;
					<div class="form-group">
						<button class="btn btn-default" id="showOehbeitraege">Anzeigen</button>
						<button class="btn btn-default" id="downloadOehbeitraege">Download</button>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12" id="oehbeitragsliste">
				</div>
			</div>
		</div>
	</div>
</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
