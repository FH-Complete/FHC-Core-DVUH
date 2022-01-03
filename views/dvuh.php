<?php
$this->load->view('templates/FHC-Header', array(
	'title' => 'DVUH',
	'jquery' => true,
	'jqueryui' => true,
	'bootstrap' => true,
	'fontawesome' => true,
	'dialoglib' => true,
	'ajaxlib' => true,
	'navigationwidget' => true,
	'sbadmintemplate' => true,
	'phrases' => array(
		'dvuh'
	),
	'customJSs' => array(
		'public/extensions/FHC-Core-DVUH/js/DVUHMenu.js'
	),
	'customCSSs' => array(
		'public/extensions/FHC-Core-DVUH/css/DVUHMenu.css'
	)
));
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
					<h3 class="page-header"><?php echo $this->p->t('dvuh', 'clientDvuhWebservice'); ?></h3>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-10">
					<?php echo $this->p->t('dvuh', 'clientDvuhWebserviceBeschreibung'); ?>&nbsp;
					<span class="btn-group">
						<button type="button" class="btn btn-default btn-sm" id="toggleMenu">
							<span id="toggleMenuText"><?php echo $this->p->t('dvuh', 'menueZuklappen'); ?></span>
							<span class="fa fa-caret-down" id="toggleMenuCaret"></span>
						</button>
					</span>
				</div>
				<div class="col-xs-2 text-right">
					<?php echo $this->p->t('dvuh', 'umgebung'); ?>: <strong><?php echo $environment ?></strong>
					<br />
					<?php echo $this->p->t('dvuh', 'apiVersion'); ?>: <strong><?php echo $apiVersion ?></strong>
				</div>
			</div>
			<br />
			<div id="menuContainer">
				<div class="row first-row">
					<div class="col-lg-6">
						<div class="panel panel-default">
							<div class="panel-heading"><?php echo $this->p->t('dvuh', 'matrikelnummerManagement'); ?></div>
							<div class="panel-body">
								<div class="row">
									<div class="col-lg-6 menucolumn">
										<ul class="list-unstyled dvuhMenu">
											<li id="getMatrikelnummer"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'matrikelnummerManagement'); ?></a></li>
											<li id="getMatrikelnummerReservierungen"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'reservierungenAnzeigen'); ?></a></li>
											<li id="reserveMatrikelnummer"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'matrikelnummerReservieren'); ?></a></li>
											<li id="postMatrikelkorrektur"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'matrikelnummerKorrigieren'); ?></a></li>
										</ul>
									</div>
									<div class="col-lg-6 menucolumn">
										<ul class="list-unstyled dvuhMenu">
											<li id="getBpkByPersonId"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'bpkErmitteln'); ?></a></li>
											<li id="getBpk"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'bpkManuellErmitteln'); ?></a></li>
											<li id="postEkzanfordern"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'ekzAnfordern'); ?></a></li>
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-lg-6">
						<div class="panel panel-default">
							<div class="panel-heading"><?php echo $this->p->t('dvuh', 'stammdatenManagement'); ?></div>
							<div class="panel-body">
								<ul class="list-unstyled dvuhMenu">
									<li id="getStammdaten"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'stammdatenAbfragen'); ?></a></li>
									<li id="postMasterData"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'stammdatenMelden'); ?></a></li>
									<li id="postErnpmeldung"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'ernpMeldungDurchführen'); ?></a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
				<div class="row second-row">
					<div class="col-lg-6">
						<div class="panel panel-default">
							<div class="panel-heading"><?php echo $this->p->t('dvuh', 'zahlungsmanagement'); ?></div>
							<div class="list-unstyled panel-body">
								<ul class="list-unstyled dvuhMenu">
									<li id="getKontostaende"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'kontostandAbfragen'); ?></a></li>
									<li id="postPayment"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'zahlungseingangMelden'); ?></a></li>
								</ul>
							</div>
						</div>
					</div>
					<div class="col-lg-6">
						<div class="panel panel-default mx-auto">
							<div class="panel-heading"><?php echo $this->p->t('dvuh', 'studiumsdatenManagement'); ?></div>
							<div class="panel-body">
								<div class="row">
									<div class="col-lg-6 menucolumn">
										<ul class="list-unstyled dvuhMenu">
											<li id="getStudium"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'studiumsdatenAbfragen'); ?></a></li>
											<li id="getFullstudent"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'detaillierteStudiendatenAbfragen'); ?></a></li>
											<li id="getPruefungsaktivitaeten"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'pruefungsaktivitaetenAbfragen'); ?></a></li>
										</ul>
									</div>
									<div class="col-lg-6 menucolumn">
										<ul class="list-unstyled dvuhMenu">
											<li id="postStudium"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'studiumsdatenMelden'); ?></a></li>
											<li id="postPruefungsaktivitaeten"><a href="javascript:void(0)"><?php echo $this->p->t('dvuh', 'pruefungsaktivitaetenMelden'); ?></a></li>
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-lg-12">
					<form class="form-horizontal" id="dvuhForm"></form>
				</div>
			</div>
			<div class="row">
				<div class="col-lg-offset-2 col-lg-10">
					<span id="dvuhDatenvorschauButton"></span>
					<span id="dvuhAbsendenButton"></span>
				</div>
			</div>
			<br />
			<div class="row" id="dvuhOutputContainer"></div>
		</div>
	</div>
</div>
<button id="scrollToTop" title="Zum Anfang"><i class="fa fa-chevron-up"></i></button>
</body>
<?php
$this->load->view('templates/FHC-Footer');
?>
