<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'bPK Details',
		'jquery' => true,
		'bootstrap' => true,
		'fontawesome' => true,
		'jqueryui' => true,
		'dialoglib' => true,
		'ajaxlib' => true,
		'tablesorter' => true,
		'sbadmintemplate' => true,
		'navigationwidget' => true,
		'customCSSs' => array(
			'public/css/sbadmin2/admintemplate.css',
			'public/css/sbadmin2/tablesort_bootstrap.css',
			'public/extensions/FHC-Core-DVUH/css/BPKDetails.css'
		),
		'customJSs' => array(
			'public/js/bootstrapper.js',
			'public/js/tablesort/tablesort.js',
			'public/extensions/FHC-Core-DVUH/js/BPKDetails.js'
		),
		'phrases' => array(
			'ui', 'global', 'bpkmanagement'
		)
	)
);
?>
<body>
<div id="wrapper">

	<?php echo $this->widgetlib->widget('NavigationWidget'); ?>

	<div id="page-wrapper">
		<div class="container-fluid">
			<input type="hidden" id="hiddenpersonid" value="<?php echo $stammdaten->person_id ?>">
			<div class="row">
				<div class="col-lg-12">
					<h3 class="page-header">
						bPK Details: <?php echo $stammdaten->vorname.' '.$stammdaten->nachname ?>
					</h3>
				</div>
			</div>
			<br/>
			<section>
				<div class="row">
					<div class="col-lg-12">
						<div class="panel panel-primary">
							<div class="panel-heading text-center">
								<h4><?php echo ucfirst($this->p->t('global', 'stammdaten')) ?></h4>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-lg-5 table-responsive">
										<table class="table">
											<?php if (!empty($stammdaten->titelpre)): ?>
												<tr>
													<td><strong><?php echo  ucfirst($this->p->t('person','titelpre')) ?></strong></td>
													<td><?php echo $stammdaten->titelpre ?></td>
												</tr>
											<?php endif; ?>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','vorname')) ?></strong></td>
												<td><?php echo $stammdaten->vorname ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','nachname')) ?></strong></td>
												<td>
													<?php echo $stammdaten->nachname ?></td>
											</tr>
											<tr>
												<td><strong>Vornamen</strong></td>
												<td>
													<?php echo $stammdaten->vornamen ?></td>
											</tr>
											<?php if (!empty($stammdaten->titelpost)): ?>
												<tr>
													<td><strong><?php echo  ucfirst($this->p->t('person','titelpost')) ?></strong></td>
													<td><?php echo $stammdaten->titelpost ?></td>
												</tr>
											<?php endif; ?>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','geburtsdatum')) ?></strong></td>
												<td>
													<?php echo date_format(date_create($stammdaten->gebdatum), 'd.m.Y') ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','matrikelnummer')) ?></strong></td>
												<td>
													<?php echo $stammdaten->matr_nr ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','svnr')) ?></strong></td>
												<td>
													<?php echo $stammdaten->svnr ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','ersatzkennzeichen')) ?></strong></td>
												<td>
													<?php echo $stammdaten->ersatzkennzeichen ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','staatsbuergerschaft')) ?></strong></td>
												<td>
													<?php echo $stammdaten->staatsbuergerschaft ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','geburtsnation')) ?></strong></td>
												<td>
													<?php echo $stammdaten->geburtsnation ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','geschlecht')) ?></strong></td>
												<td>
													<?php echo $stammdaten->geschlecht ?></td>
											</tr>
											<tr>
												<td><strong><?php echo  ucfirst($this->p->t('person','bpk')) ?></strong></td>
												<td>
													<span id="bpkField">
														<span id="bpkFieldValue"><?php echo $stammdaten->bpk ?></span>&nbsp;<i class="fa fa-edit" id="editBpk" title="bPK bearbeiten"></i>
													</span>
												</td>
											</tr>
										</table>
									</div>
									<div class="col-lg-7 table-responsive">
										<table class="table table-condensed table-bordered">
											<thead>
											<tr>
												<th colspan="5" class="text-center"><?php echo $this->p->t('bpkmanagement','adressen') ?></th>
											</tr>
											<tr>
												<th><?php echo ucfirst($this->p->t('person', 'strasse')); ?></th>
												<th>PLZ</th>
												<th><?php echo ucfirst($this->p->t('person', 'ort')); ?></th>
												<th><?php echo $this->p->t('bpkmanagement','gemeinde'); ?></th>
												<th><?php echo ucfirst($this->p->t('person', 'nation')); ?></th>
											</tr>
											</thead>
											<tbody>
											<?php
												$austrianAdressExists = false;
												if (count($stammdaten->adressen) <= 0):
													echo '<tr><td colspan="5" class="text-center">'.$this->p->t('bpkmanagement','keineAdressenVorhanden').'</td></tr>';
												else:
													foreach($stammdaten->adressen as $adresse):
														if ($adresse->nation == 'A')
															$austrianAdressExists = true;
												?>
												<tr>
													<td><?php echo $adresse->strasse ?></td>
													<td><?php echo $adresse->plz ?></td>
													<td><?php echo $adresse->ort ?></td>
													<td><?php echo $adresse->gemeinde ?></td>
													<td><?php echo $adresse->nationkurztext ?></td>
												</tr>
													<?php endforeach; ?>
												<?php endif; ?>
											</tbody>
										</table>
										<table class="table table-condensed table-bordered">
											<thead>
											<tr>
												<th colspan="5" class="text-center"><?php echo $this->p->t('bpkmanagement','relevanteDokumente') ?></th>
											</tr>
											<tr>
												<th><?php echo $this->p->t('bpkmanagement','dokumentName') ?></th>
												<th><?php echo $this->p->t('bpkmanagement','dokumenttyp') ?></th>
												<th><?php echo $this->p->t('bpkmanagement','uploaddatum') ?></th>
												<th><?php echo $this->p->t('bpkmanagement','ausstellungsland') ?></th>
												<th><?php echo $this->p->t('bpkmanagement','anmerkung') ?></th>
											</tr>
											</thead>
											<tbody>
											<?php
												$meldezettelExists = false;
												$austrianMeldezettelExists = false;
												if (count($dokumente) <= 0):
													echo '<tr><td colspan="5" class="text-center">'.$this->p->t('bpkmanagement','keineDokumenteVorhanden').'</td></tr>';
												else:
													foreach($dokumente as $dokument):
														if ($dokument->dokument_kurzbz == 'Meldezet')
														{
															$meldezettelExists = true;
															if ($dokument->nation_code == 'A')
																$austrianMeldezettelExists = true;
														}
												?>
												<tr>
													<td><a href="outputAkteContent/<?php echo $dokument->akte_id ?>"><?php echo isEmptyString($dokument->titel) ? $dokument->akte_bezeichnung : $dokument->titel ?></a></td>
													<td><?php echo $dokument->dokument_bezeichnung_mehrsprachig[0] ?></td>
													<td><?php echo date_format(date_create($dokument->erstelltam), 'd.m.Y') ?></td>
													<td><?php echo $dokument->nation ?></td>
													<td><?php echo $dokument->akte_anmerkung ?></td>
												</tr>
													<?php endforeach; ?>
												<?php endif; ?>
											</tbody>
										</table>
										<?php if (isEmptyString($stammdaten->bpk)): ?>
											<div class="text-right">
												<a href="https://resources.portal.at/appcall_registration?portal=PAT&appid=STB" target="_blank">
													<i class="fa fa-external-link"></i>&nbsp;<?php echo $this->p->t('bpkmanagement','ZumErnpMeldungsportal') ?>
												</a>
												<br />
												<br />
											</div>
										<?php endif; ?>
									</div>
								</div>
								<div class="row">
									<div class="col-lg-12 text-center">
										<?php if (!isEmptyString($stammdaten->bpk)): ?>
										<span class="text-success">
											<i class="fa fa-check"></i>
											<?php echo $this->p->t('bpkmanagement','bpkVorhanden') ?>
										</span>
										<?php else:?>
											<?php if ($austrianAdressExists): ?>
												<span class="text-warning">
													<i class="fa fa-warning"></i>
													<?php echo $this->p->t('bpkmanagement','oesterrAdresseVorhanden') ?>
												</span>
											<?php endif; ?>
											<?php if ($meldezettelExists) :?>
												<?php if ($austrianMeldezettelExists) :?>
													<span class="text-warning">
														<i class="fa fa-warning"></i>
														<?php echo $this->p->t('bpkmanagement','oesterrMeldezettelVorhanden') ?>
													</span>
												<?php else: ?>
													<span class="text-warning">
														<i class="fa fa-warning"></i>
														<?php echo $this->p->t('bpkmanagement','meldezettelVorhanden') ?>
													</span>
												<?php endif; ?>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
							</div> <!-- ./panel -->
						</div> <!-- ./main column -->
					</div> <!-- ./main row -->
			</section>
			<section>
				<div class="row">
					<div class="col-lg-12">
						<div class="panel panel-primary">
							<div class="panel-heading text-center">
								<h4><?php echo $this->p->t('bpkmanagement','bpkPruefung') ?></h4>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-lg-5">
										<a id="showAllCombinations" target="_self">
											<i class="fa fa-info"></i>&nbsp;<?php echo $this->p->t('bpkmanagement','alleNamenskombinationenAnzeigen') ?>
										</a>
									</div>
									<div class="col-lg-2 text-center">
										<button class="btn btn-default" id="startBpkCheck"><?php echo $this->p->t('bpkmanagement','bpkPruefungStarten') ?></button>
									</div>
									<div class="col-lg-5 text-right">
										<a href="<?php echo site_url('extensions/FHC-Core-DVUH/DVUH#page=getBpk&person_id='.$stammdaten->person_id); ?>" target="_blank">
											<i class="fa fa-external-link"></i>&nbsp;<?php echo $this->p->t('bpkmanagement','zurManuellenBpkPruefung') ?>
										</a>
									</div>
								</div>
								<br />
								<div class="row">
									<div class="col-lg-12" id="bpkBoxes">
									</div>
								</div>
							</div> <!-- ./panel-body -->
						</div> <!-- ./panel -->
					</div> <!-- ./main column -->
				</div> <!-- ./main row -->
			</section>
		</div> <!-- ./container-fluid-->
	</div> <!-- ./page-wrapper-->
</div> <!-- ./wrapper -->
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
