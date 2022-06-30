<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'ÖH-Beitragsliste',
		'jquery' => true,
		'jqueryui' => true,
		'bootstrap' => true,
		'fontawesome' => true,
		'navigationwidget' => true,
		'sbadmintemplate' => true,
		'dialoglib' => true,
		'ajaxlib' => true,
		'customJSs' => array(
			'public/extensions/FHC-Core-DVUH/js/rohdatenOehBeitrag.js'
		)/*,
		'customCSSs' => array(
			'public/css/sbadmin2/admintemplate_contentonly.css'
		)*/
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
				<div class="col-xs-12">
					<div class="form-group form-inline">
						<label>
							Datum von:
						</label>
						<input type="text" class="form-control" id="dateFrom">
						&nbsp;
						<label>
							Datum bis:
						</label>
						<input type="text" class="form-control" id="dateTo">
						&nbsp;
						&nbsp;
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
