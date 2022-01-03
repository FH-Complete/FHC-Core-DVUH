<?php
$this->load->view('templates/FHC-Header', array(
	'title' => 'Codex',
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
					<h3 class="page-header">Codex</h3>
				</div>
			</div>
			<?php echo $this->p->t('codex', 'codexSeiteBeschreibung'); ?>
			<br />
			<br />
			<ul>
				<li>
					<a href="<?php echo site_url(); ?>/extensions/FHC-Core-DVUH/Codex/exportbecodes"><?php echo $this->p->t('codex', 'beCodes'); ?></a>
				</li>
				<li>
					<a href="<?php echo site_url(); ?>/extensions/FHC-Core-DVUH/Codex/exportlaendercodes"><?php echo $this->p->t('codex', 'laenderCodes'); ?></a>
				</li>
				<li>
					<a href="<?php echo site_url(); ?>/extensions/FHC-Core-DVUH/Codex/fehlerliste"><?php echo $this->p->t('codex', 'fehlerliste'); ?></a>
				</li>
			</div>
		</div>
	</div>
</div>

</body>
<?php
$this->load->view('templates/FHC-Footer');
?>
