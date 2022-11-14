<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'DVUH Feeds',
		'jquery3' => true,
		'jqueryui1' => true,
		'bootstrap3' => true,
		'fontawesome4' => true,
		'navigationwidget' => true,
		'sbadmintemplate3' => true,
		'dialoglib' => true,
		'ajaxlib' => true,
		'customJSs' => array(
			'public/extensions/FHC-Core-DVUH/js/DVUHLib.js',
			'public/extensions/FHC-Core-DVUH/js/feedoverview.js'
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
					<h3 class="page-header">DVUH Feedliste</h3>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12">
					<div class="form-group form-inline">
						<label>
							Feed erstellt seit:
						</label>
						<input type="text" class="form-control" id="erstelltSeit">
						&nbsp;
						<label>
							Matrikelnummer:
						</label>
						<input type="text" class="form-control" id="matrikelnummer">
						&nbsp;
						<button class="btn btn-default" id="showfeeds">Anzeigen</button>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12" id="feedlist">
				</div>
			</div>
		</div>
	</div>
</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
