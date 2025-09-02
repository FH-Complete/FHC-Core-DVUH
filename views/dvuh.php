<?php
$this->load->view('templates/FHC-Header', array(
	'title' => 'DVUH',
	'jquery3' => true,
	'jqueryui1' => true,
	'bootstrap3' => true,
	'fontawesome4' => true,
	'dialoglib' => true,
	'ajaxlib' => true,
	'navigationwidget' => true,
	'sbadmintemplate3' => true,
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
				<div class="col-xs-10">
					Diese Seite stellt die Schnittstellen zum Datenverbund für Universitäten und Hochschulen zur
					Verfügung.&nbsp;
					<span class="btn-group">
						<button type="button" class="btn btn-default btn-sm" id="toggleMenu">
							<span id="toggleMenuText">Menü zuklappen</span>
							<span class="fa fa-caret-down" id="toggleMenuCaret"></span>
						</button>
					</span>
				</div>
				<div class="col-xs-2 text-right">
					Umgebung: <strong><?php echo $environment ?></strong>
					<br />
					API-Version: <strong><?php echo $apiVersion ?></strong>
				</div>
			</div>
			<br />
			<div id="menuContainer">
				<div class="row first-row">
					<?php $panelColWidthUsed = 0; ?>
					<?php foreach ($menu as $menuCategory => $menuEntries): ?>
						<?php $panelColWidth = $menuEntries['width']; ?>
						<?php unset($menuEntries['width']); ?>
						<?php $menuColWidth = isset($menuEntries['left']) && isset($menuEntries['right']) ? '6' : '12'; ?>
						<?php $panelColWidthUsed += $panelColWidth; ?>
						<div class="col-lg-<?php echo $panelColWidth; ?> panelcolumn">
							<div class="panel panel-default">
								<div class="panel-heading"><?php echo $menuCategory; ?></div>
								<div class="panel-body">
									<div class="row">
										<?php foreach ($menuEntries as $position => $entries): ?>
										<div class="col-lg-<?php echo $menuColWidth ?> menucolumn">
											<ul class="list-unstyled dvuhMenu">
												<?php foreach ($entries as $menuEntry): ?>
												<?php if (!$menuEntry['active']) continue; ?>
												<li id="<?php echo $menuEntry['id'] ?>"><a href="javascript:void(0)"><?php echo $menuEntry['description'] ?></a></li>
												<?php endforeach; ?>
											</ul>
										</div>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>
						<?php if ($panelColWidthUsed >= 12): ?>
							<?php $panelColWidthUsed = 0; ?>
				</div>
				<div class="row second-row">
						<?php endif; ?>
					<?php endforeach; ?>
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
