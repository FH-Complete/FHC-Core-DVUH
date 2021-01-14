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
					<h3 class="page-header">Client f&uuml;r Datenverbund-Webservice</h3>
				</div>
			</div>
			<div class="row">
				<div class="col-lg-12">
					Diese Seite stellt die Schnittstellen zum Datenverbund für Universitäten und Hochschulen zur
					Verfügung.&nbsp;
					<span class="btn-group">
						<button type="button" class="btn btn-default btn-sm" id="toggleMenu">
							<span id="toggleMenuText">Menü zuklappen</span>
							<span class="fa fa-caret-down" id="toggleMenuCaret"></span>
						</button>
					</span>
				</div>
			</div>
			<br />
			<div id="menuContainer">
				<div class="row first-row">
					<div class="col-lg-6">
						<div class="panel panel-default">
							<div class="panel-heading">Matrikelnummermanagement</div>
							<div class="panel-body">
								<ul class="list-unstyled dvuhMenu">
									<li id="getBpkByPersonId"><a href="javascript:void(0)">BPK ermitteln</a></li>
									<li id="getBpk"><a href="javascript:void(0)">BPK manuell ermitteln</a></li>
									<li id="getMatrikelnummer"><a href="javascript:void(0)">Matrikelnummer prüfen</a></li>
									<li id="getMatrikelnummerReservierungen"><a href="javascript:void(0)">Matrikelnummerreservierungen anzeigen</a></li>
									<li id="reserveMatrikelnummer"><a href="javascript:void(0)">Matrikelnummer reservieren</a></li>
									<li id="postMatrikelkorrektur"><a href="javascript:void(0)">Matrikelnummer korrigieren</a></li>
								</ul>
							</div>
						</div>
					</div>
					<div class="col-lg-6">
						<div class="panel panel-default">
							<div class="panel-heading">Stammdatenmanagement</div>
							<div class="panel-body">
								<ul class="list-unstyled dvuhMenu">
									<li id="getStammdaten"><a href="javascript:void(0)">Stammdaten und Zahlungsvorschreibung abfragen
									<li id="postMasterData"><a href="javascript:void(0)">Stammdaten und Matrikelnummer melden</a></li>
<!--									<li id="postCharge"><a href="javascript:void(0)">Stammdaten und Matrikelnummer melden (mit
											Zahlungsvorschreibung)</a></li>-->
								</ul>
							</div>
						</div>
					</div>
				</div>
				<div class="row second-row">
					<div class="col-lg-6">
						<div class="panel panel-default">
							<div class="panel-heading">Zahlungsmanagement</div>
							<div class="list-unstyled panel-body">
								<ul class="list-unstyled dvuhMenu">
									<li id="getKontostaende"><a href="javascript:void(0)">Kontostand abfragen</a></li>
									<li id="postPayment"><a href="javascript:void(0)">Zahlungseingang melden</a></li>
								</ul>
							</div>
						</div>
					</div>
					<div class="col-lg-6">
						<div class="panel panel-default mx-auto">
							<div class="panel-heading">Studiumsdatenmanagement</div>
							<div class="panel-body">
								<ul class="list-unstyled dvuhMenu">
									<li id="getStudium"><a href="javascript:void(0)">Studiumsdaten abfragen</a></li>
									<li id="postStudium"><a href="javascript:void(0)">Studiumsdaten melden</a></li>
									<li id="getFullstudent"><a href="javascript:void(0)">Detaillierte Studiendaten abfragen</a></li>
								</ul>
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
