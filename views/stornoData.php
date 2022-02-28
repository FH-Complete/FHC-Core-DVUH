<?php
$VALID_STATUS_KURZBZ = "('" . implode("','", $valid_status_kurzbz) . "')";
$LANGUAGE = getUserLanguage();
$STUDIENGANG_BEZEICHNUNG = $LANGUAGE === 'German' ? 'bezeichnung' : 'english';

$filterWidgetArray = array(
	'query' => '
		SELECT prestudent_id, person_id, vorname, nachname, matrikelnummer, studiengangskennzahl, studiengang, studiensemester FROM
		(
			SELECT DISTINCT ON (prestudent_id) ps.prestudent_id, pers.person_id, vorname, nachname, matr_nr as matrikelnummer,
			                                   storniert, studiengang_kz as studiengangskennzahl, stg.'.$STUDIENGANG_BEZEICHNUNG.' as studiengang,
											   std_daten.studiensemester_kurzbz as studiensemester, sem.start as sem_start
			FROM public.tbl_prestudent ps
			JOIN public.tbl_studiengang stg USING (studiengang_kz)
			JOIN public.tbl_person pers USING (person_id)
			JOIN sync.tbl_dvuh_studiumdaten std_daten USING (prestudent_id) /* if in sync table ... */
			JOIN public.tbl_studiensemester sem USING (studiensemester_kurzbz)
			WHERE NOT EXISTS ( /* ... but no valid prestudent status*/
				SELECT 1
				FROM public.tbl_prestudentstatus
				WHERE prestudent_id = ps.prestudent_id
				AND status_kurzbz IN '.$VALID_STATUS_KURZBZ.'
				AND studiensemester_kurzbz = std_daten.studiensemester_kurzbz
			)
			ORDER BY prestudent_id, meldedatum DESC, std_daten.insertamum DESC NULLS LAST, studiumdaten_id DESC
		) prestudents
		WHERE storniert = FALSE /* exclude already storniert */
		ORDER BY sem_start DESC, prestudent_id DESC
	',
	'requiredPermissions' => 'admin',
	'datasetRepresentation' => 'tablesorter',
	'additionalColumns' => array('Storno'),
	'columnsAliases' => array(
		'PrestudentID',
		'PersonID',
		ucfirst($this->p->t('person', 'vorname')) ,
		ucfirst($this->p->t('person', 'nachname')),
		ucfirst($this->p->t('person', 'matrikelnummer')),
		ucfirst($this->p->t('lehre', 'studiengangskennzahl')),
		ucfirst($this->p->t('lehre', 'studiengang')),
		ucfirst($this->p->t('lehre', 'studiensemester'))
	),
	'formatRow' => function($datasetRaw) {

		/* NOTE: Dont use $this here for PHP Version compatibility */
		$datasetRaw->{'Storno'} = sprintf(
			'<a href="%s&matr_nr=%s&studiensemester_kurzbz=%s&studiengang_kz=%s" target="_blank">Storno</a>',
			site_url('extensions/FHC-Core-DVUH/DVUH#page=postStudiumStorno'),
			$datasetRaw->{'matrikelnummer'},
			$datasetRaw->{'studiensemester'},
			$datasetRaw->{'studiengangskennzahl'}
		);

		if ($datasetRaw->{'matrikelnummer'} == null)
		{
			$datasetRaw->{'matrikelnummer'} = '-';
		}

		return $datasetRaw;
	}
);

$filterWidgetArray['app'] = 'core';
$filterWidgetArray['datasetName'] = 'overview';
$filterWidgetArray['filterKurzbz'] = 'DVUHStorno';
$filterWidgetArray['filter_id'] = $this->input->get('filter_id');

echo $this->widgetlib->widget('FilterWidget', $filterWidgetArray);
?>
