<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'bPK Wartung',
		'jquery' => true,
		'jqueryui' => true,
		'bootstrap' => true,
		'fontawesome' => true,
		'sbadmintemplate' => true,
		'tablesorter' => true,
		'ajaxlib' => true,
		'filterwidget' => true,
		'navigationwidget' => true,
		'phrases' => array(
			'ui' => array('bitteEintragWaehlen')
		),
		'customCSSs' => 'public/css/sbadmin2/tablesort_bootstrap.css',
		'customJSs' => array('public/js/bootstrapper.js')
	)
);
?>

<body>
<div id="wrapper">

	<?php echo $this->widgetlib->widget('NavigationWidget'); ?>

	<div id="page-wrapper">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-12">
					<h3 class="page-header">
						bPK <?php echo ucfirst($this->p->t('global', 'uebersicht')); ?>
					</h3>
				</div>
			</div>
			<div>
				<?php echo $this->p->t('bpkmanagement', 'bpkUebersichtBeschreibung') ?>
				<br /><br />
				<?php $this->load->view('extensions/FHC-Core-DVUH/BPKManagementData.php'); ?>
			</div>
		</div>
	</div>
</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
