<?php
$limitClause = isset($display_limit) && is_integer($display_limit) ? " LIMIT $display_limit" : "";

$filterWidgetArray = array(
	'query' => "
		WITH personen AS
		(
			SELECT
				person_id, vorname, nachname, geschlecht, svnr, ersatzkennzeichen, matr_nr,
				staatsbuergerschaft, gebdatum, ben.uid,
				(SELECT count(*) FROM public.tbl_akte WHERE person_id=tbl_person.person_id) AS anzahl_dokumente
			FROM
				public.tbl_person
				JOIN public.tbl_benutzer ben USING (person_id)
			WHERE
				ben.aktiv
				AND bpk IS NULL
		)
		SELECT
			DISTINCT ON (person_id)
			person_id, vorname, nachname, geschlecht, svnr, ersatzkennzeichen, matr_nr,
			staatsbuergerschaft, gebdatum, mitarbeiter, anzahl_dokumente
		FROM (
			SELECT *,
			EXISTS(SELECT 1 FROM public.tbl_student WHERE student_uid = personen.uid AND matr_nr IS NOT NULL) AS student,
			EXISTS(SELECT 1 FROM public.tbl_mitarbeiter WHERE mitarbeiter_uid = personen.uid) AS mitarbeiter
			FROM personen
		) personen_erweitert
		WHERE
			student OR mitarbeiter
		{$limitClause}
		",
	'requiredPermissions' => 'admin',
	'datasetRepresentation' => 'tablesorter',
	'additionalColumns' => array('Details'),
	'columnsAliases' => array(
		'PersonID',
		ucfirst($this->p->t('person', 'vorname')) ,
		ucfirst($this->p->t('person', 'nachname')),
		ucfirst($this->p->t('person', 'geschlecht')),
		ucfirst($this->p->t('person', 'svnr')),
		ucfirst($this->p->t('person', 'ersatzkennzeichen')),
		ucfirst($this->p->t('person', 'matrikelnummer')),
		ucfirst($this->p->t('person', 'staatsbuergerschaft')),
		ucfirst($this->p->t('person', 'geburtsdatum')),
		'Mitarbeiter',
		'Anzahl Dokumente'
	),
	'formatRow' => function($datasetRaw) {

		/* NOTE: Dont use $this here for PHP Version compatibility */
		$datasetRaw->{'Details'} = sprintf(
			'<a href="%s?person_id=%s&origin_page=%s&fhc_controller_id=%s">Details</a>',
			site_url('extensions/FHC-Core-DVUH/BPKManagement/showDetails'),
			$datasetRaw->{'person_id'},
			'index',
			(isset($_GET['fhc_controller_id'])?$_GET['fhc_controller_id']:'')
		);

		if ($datasetRaw->{'ersatzkennzeichen'} == null)
		{
			$datasetRaw->{'ersatzkennzeichen'} = '-';
		}
		if ($datasetRaw->{'svnr'} == null)
		{
			$datasetRaw->{'svnr'} = '-';
		}
		if ($datasetRaw->{'matr_nr'} == null)
		{
			$datasetRaw->{'matr_nr'} = '-';
		}
		$datasetRaw->{'mitarbeiter'} = $datasetRaw->{'mitarbeiter'} == 'true' ? 'Ja' : 'Nein';

		return $datasetRaw;
	}
);

$filterWidgetArray['app'] = 'dvuh';
$filterWidgetArray['datasetName'] = 'overview';
$filterWidgetArray['filterKurzbz'] = 'BPKWartungDVUH';
$filterWidgetArray['filter_id'] = $this->input->get('filter_id');

echo $this->widgetlib->widget('FilterWidget', $filterWidgetArray);
