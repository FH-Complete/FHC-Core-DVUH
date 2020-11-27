<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'SAP DVUH Feeds',
		'jquery' => true,
		'jqueryui' => true,
		'bootstrap' => true,
		'fontawesome' => true,
		'sbadmintemplate' => true,
		'dialoglib' => true,
		'ajaxlib' => true,
		'customJSs' => array(
			'public/extensions/FHC-Core-DVUH/js/feedoverview.js'
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
					<h3 class="page-header">DVUH Feedliste</h3>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12">
                    <div class="form-group">
                        <label>
                            Feed erstellt seit:
                        </label>
                        <input type="text" id="erstelltSeit">
                        &nbsp;
                        <label>
                            Matrikelnummer:
                        </label>
                        <input type="text" id="matrikelnummer">
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
