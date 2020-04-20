<?php
$this->load->view('templates/FHC-Header', array(
	'title' => 'DVUH',
	'jquery' => true,
	'jqueryui' => true,
	'bootstrap' => true,
	'fontawesome' => true,
	'ajaxlib' => true,
	'navigationwidget' => true,
	'sbadmintemplate' => true));
?>
<body>
<div id="wrapper">
	<?php
	echo $this->widgetlib->widget('NavigationWidget');
	?>
	<div id="page-wrapper">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-12">
					<h3 class="page-header">Datenverbund</h3>
				</div>
			</div>
			Diese Seite stellt die Schnittstellen zum Datenverbund für Universitäten und Hochschulen zur Verfügung.
			</div>
		</div>
	</div>
</div>

</body>
<?php
$this->load->view('templates/FHC-Footer');
?>
