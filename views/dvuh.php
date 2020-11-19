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
	)/*,
		'customCSSs' => array(
			'public/css/sbadmin2/admintemplate_contentonly.css'
		)*/
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
					Verfügung.
				</div>
			</div>
			<br />
			<div class="row">
				<div class="col-lg-12">

					<ul id="dvuhMenu">
						<!-- <li><a href="DVUH?action=getOAuth">OAuth Token anfordern</a></li>-->
						<li id="getMatrikelnummer"><a href="javascript:void(0)">Matrikelnummer prüfen</a></li>
						<li id="getMatrikelnummerReservierungen"><a href="javascript:void(0)">Matrikelnummerreservierungen anzeigen</a></li>
						<li id="reserveMatrikelnummer"><a href="javascript:void(0)">Matrikelnummer reservieren</a></li>
						<li id="correctMatrikelnummer"><a href="javascript:void(0)">Matrikelnummer korrigieren</a></li>
						<li id="getStammdaten"><a href="javascript:void(0)">Stammdaten und Zahlungsvorschreibung abfragen
						<li id="postStammdaten"><a href="javascript:void(0)">Stammdaten und Matrikelnummer melden (ohne
								Zahlungsvorschreibung)</a></li>
						<li id="postStammdatenVorschreibung"><a href="javascript:void(0)">Stammdaten und Matrikelnummer melden (mit
								Zahlungsvorschreibung)</a></li>
						<li id="getKontostaende"><a href="javascript:void(0)">Kontostand abfragen</a></li>
						<li id="postZahlung"><a href="javascript:void(0)">Zahlungseingang melden</a></li>
						<li id="getStudium"><a href="javascript:void(0)">Studiumsdaten abfragen</a></li>
						<li id="postStudium"><a href="javascript:void(0)">Studiumsdaten melden</a></li>
						<li id="getFullstudent"><a href="javascript:void(0)">Detaillierte Studiendaten abfragen</a></li>
						<li id="getBpk"><a href="javascript:void(0)">BPK ermitteln</a></li>
						<!--<li><a href="datenverbund_client.php?action=getByMatrikelnummer">Personendaten anhand der Matrikelnummer suchen</a></li>
						<li><a href="datenverbund_client.php?action=getReservations">Matrikelnummer Reservierungen anzeigen</a></li>
						<li><a href="datenverbund_client.php?action=getKontingent">Matrikelnummer Kontingent anfordern</a></li>
						<li><a href="datenverbund_client.php?action=setMatrikelnummer">Matrikelnummer Vergabe melden</a></li>
						<li><a href="datenverbund_client.php?action=assignMatrikelnummer">Gesamtprozess (Abfrage, ggf Vergabemeldung, Speichern bei Person)</a></li>
						<li><a href="datenverbund_client.php?action=getBPK">BPK ermitteln</a></li>
						<li><a href="datenverbund_client.php?action=pruefeBPK">BPK ermitteln manuell</a></li>-->
					</ul>
				</div>
			</div>
			<br/>
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

</body>
<?php
$this->load->view('templates/FHC-Footer');
?>
