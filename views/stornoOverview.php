<?php
	$this->load->view(
		'templates/FHC-Header',
		array(
			'title' => 'Storno Übersicht',
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
							Storno <?php echo ucfirst($this->p->t('global', 'uebersicht')); ?>
						</h3>
					</div>
				</div>
				<div>
					Folgende StudentInnen wurden an den Datenverbund gemeldet, haben jedoch keinen validen Status im gemeldeten Semester.
					Sie sind daher KanditatInnen für einen Datenverbund Storno. <!--//TODO phrases-->
					<br /><br />
					<?php $this->load->view('extensions/FHC-Core-DVUH/stornoData.php'); ?>
				</div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
