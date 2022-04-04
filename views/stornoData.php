<?php
$VALID_STATUS_KURZBZ = "('" . implode("','", $valid_status_kurzbz) . "')";
$LANGUAGE = getUserLanguage();
$STUDIENGANG_BEZEICHNUNG = $LANGUAGE === 'German' ? 'bezeichnung' : 'english';

$filterWidgetArray = array(
	'query' => '
		SELECT prestudent_id, person_id, vorname, nachname, matrikelnummer, studiengangskennzahl, studiengang, studiensemester, last_status, bismelden FROM
		(
			SELECT DISTINCT ON (prestudent_id) ps.prestudent_id, pers.person_id, vorname, nachname, matr_nr as matrikelnummer,
			                                   storniert, studiengang_kz as studiengangskennzahl, stg.'.$STUDIENGANG_BEZEICHNUNG.' as studiengang,
											   std_daten.studiensemester_kurzbz as studiensemester, sem.start as sem_start, ps.bismelden,
											   (SELECT status_kurzbz
												FROM public.tbl_prestudentstatus
												WHERE prestudent_id = ps.prestudent_id
												ORDER BY datum DESC, insertamum DESC
												LIMIT 1) AS last_status
			FROM public.tbl_prestudent ps
			JOIN public.tbl_studiengang stg USING (studiengang_kz)
			JOIN public.tbl_person pers USING (person_id)
			JOIN sync.tbl_dvuh_studiumdaten std_daten USING (prestudent_id) /* if in sync table ... */
			JOIN public.tbl_studiensemester sem USING (studiensemester_kurzbz)
			WHERE NOT EXISTS ( /* ... but no valid prestudent status*/
				SELECT 1
				FROM public.tbl_prestudentstatus
				JOIN public.tbl_prestudent USING (prestudent_id)
				WHERE prestudent_id = ps.prestudent_id
				AND status_kurzbz IN '.$VALID_STATUS_KURZBZ.'
				AND studiensemester_kurzbz = std_daten.studiensemester_kurzbz
				AND bismelden
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
		ucfirst($this->p->t('lehre', 'studiengangskennzahlLehre')),
		ucfirst($this->p->t('lehre', 'studiengang')),
		ucfirst($this->p->t('lehre', 'studiensemester')),
		ucfirst($this->p->t('global', 'letzterStatus')),
		'Bismelden'
	),
	'formatRow' => function($datasetRaw) {

		/* NOTE: Dont use $this here for PHP Version compatibility */
		$datasetRaw->{'Storno'} = sprintf(
			'<a href="%s&prestudent_id=%s&studiensemester_kurzbz=%s" target="_blank">Storno</a>',
			site_url('extensions/FHC-Core-DVUH/DVUH#page=postStudiumStorno'),
			$datasetRaw->{'prestudent_id'},
			$datasetRaw->{'studiensemester'}
		);

		if ($datasetRaw->{'matrikelnummer'} == null)
		{
			$datasetRaw->{'matrikelnummer'} = '-';
		}

		if ($datasetRaw->{'bismelden'} == 'true')
		{
			$datasetRaw->{'bismelden'} = ucfirst($this->p->t('ui', 'ja'));
		}
		else
		{
			$datasetRaw->{'bismelden'} = ucfirst($this->p->t('ui', 'nein'));
		}

		return $datasetRaw;
	}
);

$filterWidgetArray['app'] = 'dvuh';
$filterWidgetArray['datasetName'] = 'storno';
$filterWidgetArray['filterKurzbz'] = 'DVUHStorno';
$filterWidgetArray['filter_id'] = $this->input->get('filter_id');

echo $this->widgetlib->widget('FilterWidget', $filterWidgetArray);
?>
