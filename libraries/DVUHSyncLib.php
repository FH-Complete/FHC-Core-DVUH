<?php

/**
 * Library for retrieving data from FHC for DVUH.
 * Extracts data from FHC db, performs data quality checks and puts data in DVUH form.
 */
class DVUHSyncLib
{
	private $_ci;
	private $_dbModel;
	private $_warnings = array();

	const DVUH_STGKZ_LENGTH = 4;
	const DVUH_ERHALTER_LENGTH = 3;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
		$this->_dbModel = new DB_Model();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/JQMSchedulerLib');

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->_ci->load->model('crm/prestudent_model', 'PrestudentModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studienplan_model', 'StudienplanModel');
		$this->_ci->load->model('codex/Orgform_model', 'OrgformModel');
		$this->_ci->load->model('codex/Zweck_model', 'ZweckModel');
		$this->_ci->load->model('codex/Aufenthaltfoerderung_model', 'AufenthaltfoerderungModel');
		$this->_ci->load->model('education/Zeugnisnote_model', 'ZeugnisnoteModel');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Retrieves Stammdaten inkl. contacts for a person, performs checks, prepares data for DVUH.
	 * @param $person_id
	 * @return object success with studentinfo or error
	 */
	public function getStammdatenData($person_id)
	{
		$stammdaten = $this->_ci->PersonModel->getPersonStammdaten($person_id);

		if (hasData($stammdaten))
		{
			$stammdaten = getData($stammdaten);

			$adressen = array();
			$emailliste = array();

			// adresses
			$zustellAdresse = null;
			$heimatAdresse = null;
			$zustellInsertamum = null;
			$heimatInsertamum = null;

			foreach ($stammdaten->adressen as $adresse)
			{
				// only Heimat- or Zustelladressen are sent to DVUH
				if (!$adresse->zustelladresse && !$adresse->heimatadresse)
					continue;

				$addr = array();
				$addr['ort'] = $adresse->gemeinde;
				$addr['plz'] = $adresse->plz;
				$addr['strasse'] = $adresse->strasse;
				$addr['staat'] = $adresse->nation;

				$addrCheck = $this->checkAdresse($addr);

				if (isError($addrCheck))
				{
					return createError(
						"Adresse ungültig: " . getError($addrCheck),
						'adresseUngueltig',
						array(getError($addrCheck)),
						array('adresse_id' => $adresse->adresse_id)
					);
				}

				if ($adresse->zustelladresse)
				{
					if (is_null($zustellInsertamum) || $adresse->insertamum > $zustellInsertamum)
					{
						$addr['typ'] = 'S';
						$zustellInsertamum = $adresse->insertamum;
						$zustellAdresse = $addr;
					}
				}

				if ($adresse->heimatadresse)
				{
					if (is_null($heimatInsertamum) || $adresse->insertamum > $heimatInsertamum)
					{
						$addr['typ'] = 'H';
						$heimatInsertamum = $adresse->insertamum;
						$heimatAdresse = $addr;
					}
				}
			}

			if (isEmptyString($zustellAdresse))
				return createError('Zustelladresse fehlt', 'keineZustelladresse');

			if (isEmptyString($heimatAdresse))
				return createError('Heimatadresse fehlt', 'keineHeimatadresse');

			$adressen[] = $zustellAdresse;
			$adressen[] = $heimatAdresse;

			// private mail
			foreach ($stammdaten->kontakte as $kontakt)
			{
				if ($kontakt->kontakttyp == 'email')
				{
					if (!validateXmlTextValue($kontakt->kontakt))
						return createError(
							'Email enthält Sonderzeichen',
							'emailEnthaeltSonderzeichen',
							null, // issue_fehlertext_params
							array('kontakt_id' => $kontakt->kontakt_id) // issue_resolution_params
						);

					$knt = array();
					$knt['emailadresse'] = $kontakt->kontakt;
					$knt['emailtyp'] = 'PR';
					$emailliste[] = $knt;
				}
			}

			// university mail
			$this->_ci->BenutzerModel->addSelect('uid');
			$this->_ci->BenutzerModel->addOrder('insertamum', 'DESC'); // TODO: really? benutzer aktiv?
			$uids = $this->_ci->BenutzerModel->loadWhere(array('person_id' => $person_id));

			if (hasData($uids))
			{
				$uids = getData($uids);

				foreach ($uids as $uid)
				{
					$bsmail = array();
					$bsmail['emailadresse'] = $uid->uid . '@' . DOMAIN;
					$bsmail['emailtyp'] = 'BE';
					$emailliste[] = $bsmail;
				}
			}

			$geschlecht = $this->convertGeschlechtToDVUH($stammdaten->geschlecht);

			$studentinfo = array(
				'adressen' => $adressen,
				'beitragstatus' => 'X', // X gilt nur für FHs, Bei Uni anders
				'emailliste' => $emailliste,
				'geburtsdatum' => $stammdaten->gebdatum,
				'geschlecht' => $geschlecht,
				'nachname' => $stammdaten->nachname,
				'staatsbuergerschaft' => $stammdaten->staatsbuergerschaft_code,
				'vorname' => $stammdaten->vorname,
			);

			foreach ($studentinfo as $idx => $item)
			{
				if (!isset($item) || isEmptyString($item))
					return createError('Stammdaten fehlen: ' . $idx, 'stammdatenFehlen', array($idx));
			}

			if (isset($stammdaten->matr_nr))
				$studentinfo['matr_nr'] = $stammdaten->matr_nr;

			if (isset($stammdaten->titelpre))
				$studentinfo['akadgrad'] = $stammdaten->titelpre;

			if (isset($stammdaten->titelpost))
				$studentinfo['akadgradnach'] = $stammdaten->titelpost;

			if (isset($stammdaten->svnr))
				$studentinfo['svnr'] = $stammdaten->svnr;

			if (isset($stammdaten->ersatzkennzeichen) && isEmptyString($stammdaten->svnr))
			{
				if (!$this->checkEkz($stammdaten->ersatzkennzeichen))
				{
					return createError(
						'Ersatzkennzeichen ungültig, muss aus 4 Grossbuchstaben gefolgt von 6 Zahlen bestehen',
						'ersatzkennzeichenUngueltig'
					);
				}

				$studentinfo['ekz'] = $stammdaten->ersatzkennzeichen;
			}

			if (isset($stammdaten->bpk))
			{
				if (!$this->checkBpk($stammdaten->bpk))
				{
					return createError(
						'BPK ungültig, muss aus 27 Zeichen (alphanum. mit / +) gefolgt von = bestehen',
						'bpkUngueltig'
					);
				}

				$studentinfo['bpk'] = $stammdaten->bpk;
			}

			$textValues = array('vorname', 'nachname', 'akadgrad', 'akadgradnach', 'bpk', 'titelpre', 'titelpost', 'ersatzkennzeichen');

			foreach ($textValues as $textValue)
			{
				if (isset($studentinfo[$textValue]) && !validateXmlTextValue($studentinfo[$textValue]))
				{
					return createError("$textValue enthält ungültige Sonderzeichen", 'ungueltigeSonderzeichen', array($textValue));
				}
			}

			return success(
				array(
					'matrikelnummer' => $stammdaten->matr_nr,
					'studentinfo' => $studentinfo
				)
			);
		}
		else
			return error("keine Stammdaten gefunden");
	}

	/**
	 * Retrieves studydata for a person and semester, performs checks, prepares data for DVUH.
	 * @param int $person_id
	 * @param string $semester
	 * @param int $prestudent_id optionally, retrieve only data for one prestudent of the person
	 * @return object success with studentinfo or error
	 */
	public function getStudyData($person_id, $semester, $prestudent_id = null)
	{
		$resultObj = new stdClass();

		$personresult = $this->_ci->PersonModel->load($person_id);

		if (hasData($personresult))
		{
			$person = getData($personresult)[0];

			if (isEmptyString($person->matr_nr))
				return createError('Matrikelnummer nicht gesetzt', 'matrNrFehlt');

			if (!$this->checkMatrikelnummer($person->matr_nr))
				return createError("Matrikelnummer ungültig", 'matrikelnrUngueltig', array($person->matr_nr));

			$resultObj->matrikelnummer = $person->matr_nr;
			$gebdatum = $person->gebdatum;

			$semester = $this->convertSemesterToFHC($semester);

			$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz');

			// Meldung pro Student, Studium und Semester
			$qry = "SELECT DISTINCT ON (ps.prestudent_id) ps.person_id, ps.prestudent_id, tbl_student.student_uid, pss.status_kurzbz, stg.studiengang_kz, stg.typ AS studiengang_typ,
				       stg.orgform_kurzbz AS studiengang_orgform, tbl_studienplan.orgform_kurzbz AS studienplan_orgform, 
				       pss.orgform_kurzbz AS prestudentstatus_orgform, stg.erhalter_kz, stg.max_semester AS studiengang_maxsemester,
				       tbl_lgartcode.lgart_biscode, pss.orgform_kurzbz AS studentstatus_orgform, pss.ausbildungssemester, ps.berufstaetigkeit_code,
				       tbl_student.matrikelnr AS personenkennzeichen, ps.zgv_code, ps.zgvdatum, ps.zgvnation, ps.zgvmas_code, ps.zgvmadatum, ps.zgvmanation,
				       ps.gsstudientyp_kurzbz,
				       (SELECT datum FROM public.tbl_prestudentstatus
							WHERE prestudent_id=ps.prestudent_id
							AND status_kurzbz IN ('Student', 'Unterbrecher', 'Incoming')
							ORDER BY datum ASC LIMIT 1) AS beginndatum,	
				       (SELECT datum FROM public.tbl_prestudentstatus
							WHERE prestudent_id=ps.prestudent_id
    						AND tbl_prestudentstatus.studiensemester_kurzbz = pss.studiensemester_kurzbz
							AND status_kurzbz IN ('Absolvent', 'Abbrecher')
				       		AND datum <= NOW()
							ORDER BY datum DESC LIMIT 1) AS beendigungsdatum
				  FROM public.tbl_prestudent ps
				  JOIN public.tbl_student using(prestudent_id)
				  JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
				  LEFT JOIN lehre.tbl_studienplan USING(studienplan_id)
				  LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				  LEFT JOIN bis.tbl_lgartcode ON (stg.lgartcode = tbl_lgartcode.lgartcode)
				 WHERE ps.bismelden = TRUE
				   AND stg.melderelevant = TRUE
				   AND ps.person_id = ? 
				   AND pss.studiensemester_kurzbz = ?";

			$params = array(
				$person_id,
				$semester
			);

			if (isset($status_kurzbz[JQMSchedulerLib::JOB_TYPE_SEND_STUDY_DATA]))
			{
				$qry .= " AND pss.status_kurzbz IN ?";
				$params[] = $status_kurzbz[JQMSchedulerLib::JOB_TYPE_SEND_STUDY_DATA];
			}

			if (isset($prestudent_id))
			{
				$qry .= ' AND ps.prestudent_id = ?';
				$params[] = $prestudent_id;
			}

			// newest prestudentstatus, but no future prestudentstatus
			$qry .= ' ORDER BY prestudent_id, (CASE WHEN pss.datum < NOW() THEN 1 ELSE 2 END), pss.datum DESC, pss.insertamum DESC';

			$prestudentstatusesResult = $this->_dbModel->execReadOnlyQuery($qry, $params);

			if (hasData($prestudentstatusesResult))
			{
				$studiengaenge = array();
				$lehrgaenge = array();
				$prestudent_ids = array();

				$prestudentstatuses = getData($prestudentstatusesResult);

				foreach ($prestudentstatuses as $prestudentstatus)
				{
					$prestudent_id = $prestudentstatus->prestudent_id;
					$prestudent_ids[] = $prestudent_id;
					$status_kurzbz = $prestudentstatus->status_kurzbz;
					$studiengang_kz = $prestudentstatus->studiengang_kz;

					// personenkennzeichen
					$perskz = trim($prestudentstatus->personenkennzeichen);

					if (!$this->checkPersonenkennzeichen($perskz))
					{
						return createError(
							"Personenkennzeichen ungültig",
							'personenkennzeichenUngueltig',
							array($perskz),
							array('student_uid' => $prestudentstatus->student_uid)
						);
					}

					// studstatuscode
					$studstatuscodeResult = $this->_getStatuscode($status_kurzbz);

					if (isError($studstatuscodeResult))
						return $studstatuscodeResult;
					if (hasData($studstatuscodeResult))
					{
						$studstatuscode = getData($studstatuscodeResult);
					}

					// booleans isIncoming, isAusserordentlich, isLehrgang
					$isIncoming = $prestudentstatus->status_kurzbz == 'Incoming';
					// Ausserordentlicher Studierender (4.Stelle in Personenkennzeichen = 9)
					$isAusserordentlich = $this->checkIfAusserordentlich($prestudentstatus->personenkennzeichen);

					// lehrgang - either typ l or studiengang_kz < 0, but not ausserordentlich
					$isLehrgang = !$isAusserordentlich && ($prestudentstatus->studiengang_typ == 'l' || $studiengang_kz < 0);

					// studiengang kz
					$erhalter_kz = $this->convertErhalterkennzahlToDVUH($prestudentstatus->erhalter_kz);

					// if ausserordentlich, students are sent with special studiengang_kz
					if ($isAusserordentlich)
						$studiengang_kz = $this->convertStudiengangskennzahlToDVUHAusserordentlich($studiengang_kz, $erhalter_kz);

					$dvuh_stgkz = $erhalter_kz . $this->convertStudiengangskennzahlToDVUH($studiengang_kz);

					// studtyp - if extern, certain data should not be sent to DVUH
					$gsstudientyp_kurzbz = $prestudentstatus->gsstudientyp_kurzbz;

					$kodex_studientyp_array = array();
					$gsstudientypResult = $this->_dbModel->execReadOnlyQuery("
							SELECT * FROM bis.tbl_gsstudientyp
						"
					);

					if (isError($gsstudientypResult))
						return $gsstudientypResult;
					if (hasData($gsstudientypResult))
					{
						$gsstudientypes = getData($gsstudientypResult);

						foreach ($gsstudientypes as $gsstudientype)
						{
							$kodex_studientyp_array[$gsstudientype->gsstudientyp_kurzbz] = $gsstudientype->studientyp_code;
						}
					}

					if (isset($kodex_studientyp_array[$gsstudientyp_kurzbz]))
						$studtyp = $kodex_studientyp_array[$gsstudientyp_kurzbz];

					$isExtern = (isset($studtyp) && $studtyp == 'E');

					// zulassungsdatum (start date of studies)
					$zulassungsdatum = $isIncoming || $isAusserordentlich ? null : $prestudentstatus->beginndatum;

					// zgv if not ausserordentlich
					$zugangsberechtigung = null;
					if (!$isAusserordentlich)
					{
						$zugangsberechtigungResult = $this->_getZgv($prestudentstatus, $gebdatum, $isIncoming);

						if (isError($zugangsberechtigungResult))
							return $zugangsberechtigungResult;

						if (hasData($zugangsberechtigungResult))
						{
							$zugangsberechtigung = getData($zugangsberechtigungResult);
						}
					}

					// zgv master
					$zugangsberechtigungMa = null;
					$zugangsberechtigungMAResult = $this->_getZgvMaster($prestudentstatus, $gebdatum, $isIncoming, $isAusserordentlich);

					if (isset($zugangsberechtigungMAResult) && isError($zugangsberechtigungMAResult))
						return $zugangsberechtigungMAResult;

					if (hasData($zugangsberechtigungMAResult))
					{
						$zugangsberechtigungMA = getData($zugangsberechtigungMAResult);
					}

					// standortcode
					if (!$isAusserordentlich)
					{
						$standortcodeResult = $this->_getStandort($prestudent_id);

						if (isError($standortcodeResult))
							return $standortcodeResult;

						if (hasData($standortcodeResult))
						{
							$standortcode = getData($standortcodeResult);
						}
					}

					// lehrgang
					if ($isLehrgang)
					{
						$lehrgang = array(
							'lehrgangsnr' => $dvuh_stgkz,
							'perskz' => $perskz
						);

						foreach ($lehrgang as $idx => $item)
						{
							if (!isset($item) || isEmptyString($item))
								return createError('Lehrgangdaten fehlen: ' . $idx, 'lehrgangdatenFehlen', array($idx));
						}

						if (isset($zulassungsdatum) && !$isExtern)
							$lehrgang['studstatuscode'] = $studstatuscode;

						if (isset($zulassungsdatum) && !$isExtern)
							$lehrgang['zulassungsdatum'] = $zulassungsdatum;

						if (isset($prestudentstatus->beendigungsdatum) && !$isExtern)
							$lehrgang['beendigungsdatum'] = $prestudentstatus->beendigungsdatum;

						if (isset($standortcode))
							$lehrgang['standortcode'] = $standortcode;

						if (isset($zugangsberechtigungMA))
							$lehrgang['zugangsberechtigungMA'] = $zugangsberechtigungMA;

						if (isset($zugangsberechtigung))
							$lehrgang['zugangsberechtigung'] = $zugangsberechtigung;

						$lehrgaenge[] = $lehrgang;
					}
					else // studiengang
					{
						// orgform_kurzbz
						if (isset($prestudentstatus->studienplan_orgform))
							$orgform_kurzbz = $prestudentstatus->studienplan_orgform;
						elseif (isset($prestudentstatus->prestudentstatus_orgform))
							$orgform_kurzbz = $prestudentstatus->prestudentstatus_orgform;
						elseif (isset($prestudentstatus->studiengang_orgform))
							$orgform_kurzbz = $prestudentstatus->studiengang_orgform;

						// gemeinsame Studien
						$gemeinsam = null;
						// beendigungsdatum for gemeinsame Studien, needs to be set only if extern
						$gsBeendigungsdatum = $isExtern ? $prestudentstatus->beendigungsdatum : null;
						$gemeinsamResult = $this->_getGemeinsameStudien($prestudentstatus, $semester, $studtyp, $gsBeendigungsdatum);

						if (isset($gemeinsamResult) && isError($gemeinsamResult))
							return $gemeinsamResult;
						if (hasData($gemeinsamResult))
						{
							$gemeinsam = getData($gemeinsamResult);
						}

						// ausbildungssemester
						if (!$isIncoming && !$isAusserordentlich)
						{
							$ausbildungssemesterResult = $this->_getAusbildungssemester($prestudentstatus);

							if (isError($ausbildungssemesterResult))
								return $ausbildungssemesterResult;
							else
								$ausbildungssemester = getData($ausbildungssemesterResult);
						}

						// Mobilität
						$mobilitaet = null;
						$mobilitaetResult = $this->_getMobilitaet($semester, $prestudentstatus);

						if (isset($mobilitaetResult) && isError($mobilitaetResult))
							return $mobilitaetResult;
						if (hasData($mobilitaetResult))
						{
							$mobilitaet = getData($mobilitaetResult);
						}

						// bmffoerderrelevant
						$bmffoerderrelevant = null;
						$bmffoerderrelevantResult = $this->_ci->PrestudentModel->getFoerderrelevant($prestudent_id);

						if (isError($bmffoerderrelevantResult))
							return $bmffoerderrelevantResult;
						if (hasData($bmffoerderrelevantResult))
						{
							$bmffoerderrelevant = getData($bmffoerderrelevantResult)[0]->foerderrelevant;

							if ($bmffoerderrelevant === false)
								$bmffoerderrelevant = 'N';
							else
								$bmffoerderrelevant = 'J';
						}

						if (!$isAusserordentlich)
						{
							// orgform code
							$orgform_code = $this->_getOrgformcode($orgform_kurzbz);

							if (isError($orgform_code))
								return $orgform_code;

							if (hasData($orgform_code))
							{
								$orgformcode = getData($orgform_code);
							}

							// berufstätigkeitcode, wenn nicht Vollzeit und nicht ausserordentlich
							if ($orgformcode != '1')
							{
								if (isEmptyString($prestudentstatus->berufstaetigkeit_code))
								{
									$this->_addWarning(
										'Berufstätigkeitcode fehlt',
										'berufstaetigkeitcodeFehlt',
										null,
										array('prestudent_id' => $prestudent_id)
									);
								}
								else
									$berufstaetigkeit_code = $prestudentstatus->berufstaetigkeit_code;
							}
						}

						$studiengang = array(
							'disloziert' => 'N', // J,N,j,n
							'bmwfwfoerderrelevant' => $bmffoerderrelevant,
							'perskz' => $perskz,
							'stgkz' => $dvuh_stgkz, // Laut Dokumentation 3stellige ErhKZ + 4stellige STGKz
						);

						foreach ($studiengang as $idx => $item)
						{
							if (!isset($item) || isEmptyString($item))
								return createError('Studiumdaten fehlen', 'studiumdatenFehlen', array($idx));
						}

						if (isset($orgform_code))
							$studiengang['orgformcode'] = $orgformcode;

						if (isset($studstatuscode) && !$isExtern)
							$studiengang['studstatuscode'] = $studstatuscode;

						if (isset($zulassungsdatum) && !$isExtern)
							$studiengang['zulassungsdatum'] = $zulassungsdatum;

						if (isset($ausbildungssemester) && !$isExtern)
							$studiengang['ausbildungssemester'] = $ausbildungssemester;

						if (isset($berufstaetigkeit_code))
							$studiengang['berufstaetigkeit_code'] = $berufstaetigkeit_code;

						if (isset($standortcode))
							$studiengang['standortcode'] = $standortcode;

						if (isset($gemeinsam))
							$studiengang['gemeinsam'] = $gemeinsam;

						if (isset($mobilitaet))
							$studiengang['mobilitaet'] = $mobilitaet;

						if (isset($zugangsberechtigung))
							$studiengang['zugangsberechtigung'] = $zugangsberechtigung;

						if (isset($zugangsberechtigungMA))
							$studiengang['zugangsberechtigungMA'] = $zugangsberechtigungMA;

						if (isset($prestudentstatus->beendigungsdatum) && !$isExtern)
							$studiengang['beendigungsdatum'] = $prestudentstatus->beendigungsdatum;

						$studiengaenge[] = $studiengang;
					}
				}
				$resultObj->studiengaenge = $studiengaenge;
				$resultObj->lehrgaenge = $lehrgaenge;
				$resultObj->prestudent_ids = $prestudent_ids;
			}
			else
			{
				return error('Keine aktiven Studenten für das gegebene Semester');
			}
		}

		return success($resultObj);
	}

	/**
	 * Retrieves Prüfungsaktivitäten data for sending to DVUH, for each prestudent of a person.
	 * Sums up ects angerechnet and erworben.
	 * @param int $person_id
	 * @param string $studiensemester
	 * @return object prestudent ids with ects data or error
	 */
	public function getPruefungsaktivitaetenData($person_id, $studiensemester)
	{
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz');
		$note_angerechnet_ids = $this->_ci->config->item('fhc_dvuh_sync_note_angerechnet');

		$prestudentEcts = array();

		//get all valid prestudents of person
		$prestudentsRes = $this->_ci->fhcmanagementlib->getPrestudentsOfPerson($person_id, $studiensemester, $status_kurzbz[JQMSchedulerLib::JOB_TYPE_SEND_PRUEFUNGSAKTIVITAETEN]);

		if (isError($prestudentsRes))
			return $prestudentsRes;

		if (hasData($prestudentsRes))
		{
			$prestudents = getData($prestudentsRes);

			foreach ($prestudents as $prestudent)
			{
				// convert erhalter kz and studiengangskennzahl to DVUH format
				$isAusserordentlich = isset($prestudent->personenkennzeichen) && $this->checkIfAusserordentlich($prestudent->personenkennzeichen);
				$studiengang_kz = $prestudent->studiengang_kz;
				$dvuh_erhalter_kz = $this->convertErhalterkennzahlToDVUH($prestudent->erhalter_kz);

				// if ausserordentlich, students are sent with special studiengang_kz
				if ($isAusserordentlich)
					$studiengang_kz = $this->convertStudiengangskennzahlToDVUHAusserordentlich($studiengang_kz, $dvuh_erhalter_kz);

				$dvuh_stgkz = $dvuh_erhalter_kz . $this->convertStudiengangskennzahlToDVUH($studiengang_kz);

				$prestudentEctsObj = new stdClass();
				$prestudentEctsObj->ects_erworben = 0.0;
				$prestudentEctsObj->ects_angerechnet = 0.0;
				$prestudentEctsObj->dvuh_stgkz = $dvuh_stgkz;
				$prestudentEctsObj->matr_nr = $prestudent->matr_nr;
				$prestudentEcts[$prestudent->prestudent_id] = $prestudentEctsObj;
			}
		}

		// get ects sums of Noten which are aktiv, both lehre and non-lehre, offiziell, positiv, have zeugnis = true
		$zeugnisNotenResult = $this->_ci->ZeugnisnoteModel->getByPerson($person_id, $studiensemester, true, null, true, true, true);

		if (isError($zeugnisNotenResult))
			return $zeugnisNotenResult;

		if (hasData($zeugnisNotenResult))
		{
			$zeugnisNoten = getData($zeugnisNotenResult);

			// sum up ects by prestudent, angerechnete Noten separately
			foreach ($zeugnisNoten as $note)
			{
				if (isset($prestudentEcts[$note->prestudent_id]) && isset($note->ects))
				{
					if (in_array($note->note, $note_angerechnet_ids))
						$prestudentEcts[$note->prestudent_id]->ects_angerechnet += $note->ects;
					else
						$prestudentEcts[$note->prestudent_id]->ects_erworben += $note->ects;
				}
			}
		}

		return success($prestudentEcts);
	}

	/**
	 * Converts semester in DVUH format to FHC format
	 * @param string $semester
	 * @return string semester in FHC format
	 */
	public function convertSemesterToFHC($semester)
	{
		if (!preg_match("/^\d{4}(S|W)$/", $semester))
			return $semester;

		return mb_substr($semester, -1).'S'.mb_substr($semester, 0,4);
	}

	/**
	 * Converts semester in FHC format to DVUH format
	 * @param string $semester
	 * @return string semester in DVUH format
	 */
	public function convertSemesterToDVUH($semester)
	{
		if (!preg_match("/^(S|W)S\d{4}$/", $semester))
			return $semester;

		return mb_substr($semester, 2, strlen($semester) - 2).mb_substr($semester, 0,1);
	}

	/**
	 * Converts geschlecht from FHC to DVUH format.
	 * @param string $fhcgeschlecht
	 * @return string geschlecht in DVUH format
	 */
	public function convertGeschlechtToDVUH($fhcgeschlecht)
	{
		$dvuh_geschlecht = 'X';

		if ($fhcgeschlecht == 'm')
			$dvuh_geschlecht = 'M';
		elseif ($fhcgeschlecht == 'w')
			$dvuh_geschlecht = 'W';

		return $dvuh_geschlecht;
	}

	/**
	 * Converts Erhalter Kennzahl to DVUH format.
	 * @param string $erhalter_kz
	 * @return string
	 */
	public function convertErhalterkennzahlToDVUH($erhalter_kz)
	{
		return str_pad($erhalter_kz, self::DVUH_ERHALTER_LENGTH, '0', STR_PAD_LEFT);
	}

	/**
	 * Converts Studiengangskennzahl to DVUH format.
	 * @param string $studiengang_kz
	 * @return string
	 */
	public function convertStudiengangskennzahlToDVUH($studiengang_kz)
	{
		return str_pad(str_replace('-', '', $studiengang_kz), self::DVUH_STGKZ_LENGTH, '0', STR_PAD_LEFT);
	}

	/**
	 * Converts Studiengangskennzahl of a student who is ausserordentlich to DVUH format.
	 * @param $studiengang_kz
	 * @param $erhalter_kz
	 * @return string
	 */
	public function convertStudiengangskennzahlToDVUHAusserordentlich($studiengang_kz, $erhalter_kz)
	{
		$ausserordentlich_prefix = $this->_ci->config->item('fhc_dvuh_sync_ausserordentlich_prefix');
		if (isset($ausserordentlich_prefix) && is_numeric($ausserordentlich_prefix))
			$studiengang_kz = $ausserordentlich_prefix.$erhalter_kz;

		return $studiengang_kz;
	}

	/**
	 * Checks an adress for validity.
	 * @param object $addr
	 * @return error or success with true/false (valid or not)
	 */
	public function checkAdresse($addr)
	{
		$result = success(true);

		$errorText = '';

		if (!isset($addr['ort']) || isEmptyString($addr['ort']) || !validateXmlTextValue($addr['ort']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Ort (Feld Gemeinde) fehlt oder enthält Sonderzeichen';

		if (!isset($addr['plz']) || isEmptyString($addr['plz']) || !validateXmlTextValue($addr['plz']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Plz fehlt oder enthält Sonderzeichen';

		if (!isset($addr['strasse']) || isEmptyString($addr['strasse']) || !validateXmlTextValue($addr['strasse']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Strasse fehlt oder enthält Sonderzeichen';

		if (!isset($addr['staat']) || isEmptyString($addr['staat']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Nation fehlt';

		if (!isEmptyString($errorText))
			$result = error($errorText);

		return $result;
	}

	/**
	 * Checks Matrikelnummer for validity.
	 * @param string $svnr
	 * @return bool valid or not
	 */
	public function checkMatrikelnummer($svnr)
	{
		return preg_match("/^\d{8}$/", $svnr) === 1;
	}

	/**
	 * Checks Ersatzkennzeichen for validity.
	 * @param string $ekz
	 * @return bool valid or not
	 */
	public function checkEkz($ekz)
	{
		return preg_match('/^[A-Z]{4}[0-9]{6}$/', $ekz) === 1;
	}

	/**
	 * Checks Bpk for validity.
	 * @param string $bpk
	 * @return bool valid or not
	 */
	public function checkBpk($bpk)
	{
		return preg_match("/^([A-Za-z0-9+\/]{27})=$/", $bpk) === 1;
	}

	/**
	 * Checks Bpk for validity.
	 * @param string $bpk
	 * @return bool valid or not
	 */
	public function checkPersonenkennzeichen($perskz)
	{
		return preg_match("/^\d{10}$/", $perskz);
	}

	/**
	 * Checks if a student is ausserordentlich.
	 * @param string $personenkennzeichen
	 * @return bool
	 */
	public function checkIfAusserordentlich($personenkennzeichen)
	{
		return mb_substr($personenkennzeichen,3,1) == '9';
	}

	/**
	 * Gets occured warnings and resets them.
	 * @return array
	 */
	public function readWarnings()
	{
		$warnings = $this->_warnings;
		$this->_warnings = array();
		return $warnings;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Gets Ausbildungssemester code for a prestudentstatus.
	 * @param object $prestudentstatus with semesterinfo
	 * @return error or success with ausbildungssemester
	 */
	private function _getAusbildungssemester($prestudentstatus)
	{
		$ausbildungssemester = $prestudentstatus->ausbildungssemester > $prestudentstatus->studiengang_maxsemester
			? $prestudentstatus->studiengang_maxsemester
			: $prestudentstatus->ausbildungssemester;

		// ausbildungssemester for Diplomanden
		$diplomandResult = $this->_dbModel->execReadOnlyQuery("SELECT
								count(*) AS dipl
							FROM public.tbl_prestudentstatus
							WHERE prestudent_id=?
							  AND status_kurzbz='Diplomand'
							  AND (tbl_prestudentstatus.datum<=now())",
			array(
				$prestudentstatus->prestudent_id
			)
		);

		if (isError($diplomandResult))
			return error("Fehler beim Holen der Diplomanden");
		elseif (hasData($diplomandResult))
		{
			$diplomandcount = getData($diplomandResult)[0];

			if ($diplomandcount->dipl > 1)
			{
				$ausbildungssemester = 50;
			}
			if ($diplomandcount->dipl > 3)
			{
				$ausbildungssemester = 60;
			}
		}

		return success($ausbildungssemester);
	}

	/**
	 * Gets gemeinsame Studien data for a prestudent.
	 * @param object $prestudentstatus with gsinfo
	 * @param string $semester for getting previous semester for Absolventen
	 * @param string $studtyp pass to gsdata
	 * @return object error or success with gsdata
	 */
	private function _getGemeinsameStudien($prestudentstatus, $semester, $studtyp, $beendigungsdatum)
	{
		if (!isset($studtyp))
			return error('Kein Studientyp für gemeinsame Studien gefunden');

		$prestudent_id = $prestudentstatus->prestudent_id;

		$kodex_studstatuscode_array = $this->_ci->config->item('fhc_dvuh_sync_student_statuscode');

		$gemeinsamestudienResult = $this->_dbModel->execReadOnlyQuery("SELECT
								mo.*,
								tbl_gsprogramm.programm_code,
								tbl_firma.partner_code,
       							CASE WHEN EXISTS
									(SELECT 1 FROM bis.tbl_mobilitaet
									WHERE prestudent_id = mo.prestudent_id
									AND studiensemester_kurzbz = mo.studiensemester_kurzbz
									AND status_kurzbz = 'Absolvent')
								THEN TRUE
								ELSE FALSE
								END AS ist_absolvent
							FROM
								bis.tbl_mobilitaet mo
								LEFT JOIN bis.tbl_gsprogramm USING(gsprogramm_id)
								LEFT JOIN public.tbl_firma USING(firma_id)
							WHERE
								prestudent_id=?
								AND studiensemester_kurzbz=?
							ORDER BY mo.insertamum DESC LIMIT 1;",
			array(
				$prestudent_id,
				$semester
			)
		);

		$gemeinsam = null;

		if (isError($gemeinsamestudienResult))
			return error("Fehler beim Holen gemeinsamer Studien");
		if (hasData($gemeinsamestudienResult))
		{
			$gemeinsamestudien = getData($gemeinsamestudienResult)[0];

			if (isset($kodex_studstatuscode_array[$gemeinsamestudien->status_kurzbz]))
				$gs_studstatuscode = $kodex_studstatuscode_array[$gemeinsamestudien->status_kurzbz];
			else
				return error('Kein Status für gemeinsame Studien gefunden');

			$gemeinsamData = array(
				'ausbildungssemester' => $gemeinsamestudien->ausbildungssemester,
				'mobilitaetprogrammcode' => $gemeinsamestudien->mobilitaetsprogramm_code,
				'partnercode' => $gemeinsamestudien->partner_code,
				'programmnr' => $gemeinsamestudien->programm_code,
				'studstatuscode' => $gs_studstatuscode,
				'studtyp' => $studtyp
			);

			// set beendigungsdatum only if absolvent
			if ($gemeinsamestudien->ist_absolvent === true)
				$gemeinsamData['beendigungsdatum'] = $beendigungsdatum;

			$gemeinsam = success($gemeinsamData);
		}

		return $gemeinsam;
	}

	/**
	 * Gets Mobilität  data for a prestudent. (e.g. Incoming)
	 * @param string $studiensem ester for getting previous semester for Absolventen
	 * @param object $prestudentstatus with gsinfo
	 * @return object error or success with mobilitaetdata
	 */
	private function _getMobilitaet($studiensemester, $prestudentstatus)
	{
		$this->_ci->StudiensemesterModel->addSelect('start, ende');
		$semesterResult = $this->_ci->StudiensemesterModel->load($studiensemester);

		if (hasData($semesterResult))
		{
			$semester = getData($semesterResult)[0];
		}
		else
			return error("Kein korrektes Semester angegeben");

		// get Mobilitäten of the semester, no bis dates in future
		$ioResult = $this->_dbModel->execReadOnlyQuery(
					"SELECT *
					FROM bis.tbl_bisio WHERE student_uid=?
					AND (bis >= ? OR bis IS NULL) AND von <= ?;",
					array($prestudentstatus->student_uid, $semester->start, $semester->ende)
		);

		$mobilitaeten = array();

		if (isError($ioResult))
			return $ioResult;

		if(hasData($ioResult))
		{
			$io = getData($ioResult);

			foreach ($io as $ioitem)
			{
				$bisio_id = $ioitem->bisio_id;
				$programm = $ioitem->mobilitaetsprogramm_code;
				$staat = $ioitem->nation_code;
				$avon = $ioitem->von;
				$abis = $ioitem->bis;
				$adauer = (is_null($avon) || is_null($abis)) ? null : dateDiff($avon, $abis);
				if (strtotime($abis) <= strtotime(date('Y-m-d')))
					$aufenthalt_finished = true;
				else
					$aufenthalt_finished = false;

				// Aufenthaltszweckcode --------------------------------------------------------------------------------
				$this->_ci->ZweckModel->addSelect('tbl_zweck.zweck_code');
				$this->_ci->ZweckModel->addJoin('bis.tbl_bisio_zweck', 'zweck_code');
				$bisio_zweck_result = $this->_ci->ZweckModel->loadWhere(array('bisio_id' => $bisio_id));

				if (hasData($bisio_zweck_result))
				{
					$bisio_zweck = getData($bisio_zweck_result);

					$zweck_code_arr = array();

					// Bei Incomings...
					if ($prestudentstatus->status_kurzbz == 'Incoming')
					{
						// ...max 1 Aufenthaltszweck
						if (count($bisio_zweck) > 1)
						{
							return createError(
								"Es sind" . count($bisio_zweck) . " Aufenthaltszwecke eingetragen (max. 1 Zweck für Incomings)",
								'zuVieleZweckeIncomingPlausi',
								array(count($bisio_zweck)),
								array('bisio_id' => $bisio_id)
							);
						}

						//...nur Zweck 1, 2 oder 3 erlaubt
						if (count($bisio_zweck) == 1 && !in_array($bisio_zweck[0]->zweck_code, array(1, 2, 3)))
						{
							return createError(
								"Aufenthaltszweckcode ist " . $bisio_zweck[0]->zweck_code . " (f&uuml;r Incomings ist nur Zweck 1, 2, 3 erlaubt)",
								'falscherIncomingZweckPlausi',
								array($bisio_zweck[0]->zweck_code),
								array('bisio_id' => $bisio_id)
							);
						}
					}

					foreach ($bisio_zweck as $row_zweck)
					{
						// Nur eindeutige Werte (bei Mehrfachangaben; trifft auf Outgoings zu)
						if (!in_array($row_zweck->zweck_code, $zweck_code_arr))
						{
							// Aufenthaltszweck 1, 2, 3 nicht gemeinsam melden
							if (in_array(1, $zweck_code_arr) && in_array(2, $zweck_code_arr) && in_array(3, $zweck_code_arr))
							{
								return createError(
									"Aufenthaltzweckcode 1, 2, 3 d&uuml;rfen nicht gemeinsam gemeldet werden",
									'falscherIncomingZweckGemeinsam'
								);
							}

							$zweck_code_arr[] = $row_zweck->zweck_code;
						}
					}

					// Aufenthaltfoerderungscode ---------------------------------------------------------------------------
					$aufenthaltfoerderung_code_arr = array();

					// Nur bei Outgoings Aufenthaltsfoerderungscode melden
					if ($prestudentstatus->status_kurzbz != 'Incoming')
					{
						$this->_ci->AufenthaltfoerderungModel->addSelect('tbl_aufenthaltfoerderung.aufenthaltfoerderung_code');
						$this->_ci->AufenthaltfoerderungModel->addJoin('bis.tbl_bisio_aufenthaltfoerderung', 'aufenthaltfoerderung_code');
						$this->_ci->AufenthaltfoerderungModel->addOrder('tbl_aufenthaltfoerderung.aufenthaltfoerderung_code');
						$bisio_foerderung_result = $this->_ci->AufenthaltfoerderungModel->loadWhere(array('bisio_id' => $bisio_id));

						// ... mindestens 1 Aufenthaltfoerderung melden, wenn Auslandsaufenthalt >= 29 Tage
						if ((!hasData($bisio_foerderung_result)) && $adauer >= 29)
						{
							return createError(
								"Keine Aufenthaltsfoerderung angegeben (bei Outgoings >= 29 Tage Monat im Ausland muss mind. 1 gemeldet werden)",
								'outgoingAufenthaltfoerderungfehltPlausi',
								null,
								array('bisio_id' => $bisio_id)
							);
						}

						if (hasData($bisio_foerderung_result))
						{
							$bisio_foerderung = getData($bisio_foerderung_result);

							foreach ($bisio_foerderung as $row_foerderung)
							{
								// ...wenn code = 5, nur ein Wert erlaubt (keine Mehrfachangaben)
								if ($row_foerderung->aufenthaltfoerderung_code == 5)
								{
									unset($aufenthaltfoerderung_code_arr);
									$aufenthaltfoerderung_code_arr[] = $row_foerderung->aufenthaltfoerderung_code;
									break;
								}

								// nur eindeutige Werte
								if (!in_array($row_foerderung->aufenthaltfoerderung_code, $aufenthaltfoerderung_code_arr))
								{
									$aufenthaltfoerderung_code_arr[] = $row_foerderung->aufenthaltfoerderung_code;
								}
							}
						}

						if (isEmptyString($ioitem->ects_erworben) && $adauer >= 29 && $aufenthalt_finished)
						{
							return createError(
								"Erworbene ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)",
								'outgoingErworbeneEctsFehlenPlausi',
								null,
								array('bisio_id' => $bisio_id)
							);
						}

						if (isEmptyString($ioitem->ects_angerechnet) && $adauer >= 29 && $aufenthalt_finished)
						{
							return createError(
								"Angerechnete ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)",
								'outgoingAngerechneteEctsFehlenPlausi',
								null,
								array('bisio_id' => $bisio_id)
							);
						}

						$ects_erworben = $ioitem->ects_erworben;
						$ects_angerechnet = $ioitem->ects_angerechnet;
					}
				}
				else
				{
					return createError(
						"Kein Aufenthaltszweck gefunden",
						'keinAufenthaltszweckPlausi',
						null,
						array('bisio_id' => $bisio_id)
					);
				}

				$mobilitaet = array(
					'programm' => $programm,
					'staat' => $staat,
					'von' => $avon,
					'zweck' => $zweck_code_arr
				);

				if ($aufenthalt_finished)
					$mobilitaet['bis'] = $abis;

				if (isset($aufenthaltfoerderung_code_arr) && count($aufenthaltfoerderung_code_arr) > 0)
					$mobilitaet['aufenthaltfoerderungcode'] = $aufenthaltfoerderung_code_arr;

				if (isset($ects_angerechnet) && !isEmptyString($ects_angerechnet))
					$mobilitaet['ectsangerechnet'] = round($ects_angerechnet);

				if (isset($ects_erworben) && !isEmptyString($ects_erworben))
					$mobilitaet['ectserworben'] = round($ects_erworben);

				$mobilitaeten[] = $mobilitaet;
			}
		}

		return success($mobilitaeten);
	}

	/**
	 * Gets Orgformcode for an orgform_kurzbz.
	 * @param string $orgform_kurzbz
	 * @return error or success with code
	 */
	private function _getOrgformcode($orgform_kurzbz)
	{
		$orgform_code_array = array();
		$this->_ci->OrgformModel->addSelect('orgform_kurzbz, code');
		$orgformcodesResult = $this->_ci->OrgformModel->load();

		if (hasData($orgformcodesResult))
		{
			$orgformcodes = getData($orgformcodesResult);

			foreach ($orgformcodes as $orgformcode)
			{
				$orgform_code_array[$orgformcode->orgform_kurzbz] = $orgformcode->code;
			}

			// DoubleDegree Studierende werden per Default aus BB gemeldet.
			// Wenn es ein reiner VZ Studiengang ist, dann sollen diese aber als VZ gemeldet werden.
			if($orgform_kurzbz == 'VZ')
				$orgform_code_array['DDP'] = $orgform_code_array['VZ'];

			if (!isset($orgform_code_array[$orgform_kurzbz]))
				return error("Orgform ungültig");

			return success($orgform_code_array[$orgform_kurzbz]);
		}
		else
			return error("Fehler beim Holen der Orgform");
	}

	/**
	 * Gets standort for a prestudent in a Studiengang.
	 * @param int $prestudent_id
	 * @return object with standortcode
	 */
	private function _getStandort($prestudent_id)
	{
		$standortcode = null;
		$standortRes = $this->_ci->PrestudentModel->getStandortCode($prestudent_id);

		if (isError($standortRes))
			return $standortRes;

		if (hasData($standortRes))
		{
			$standortcode = getData($standortRes)[0]->standort_code;
			if (isset($standortcode))
				$standortcode = str_pad($standortcode, 3, '0', STR_PAD_LEFT);
		}

		return success($standortcode);
	}

	/**
	 * Gets DVUH statuscode for FHC status_kurzbz.
	 * @param string $status_kurzbz
	 * @return object with FHC statuscode
	 */
	private function _getStatuscode($status_kurzbz)
	{
		if ($status_kurzbz == "Student" || $status_kurzbz == "Outgoing"
			|| $status_kurzbz == "Incoming" || $status_kurzbz == "Praktikant"
			|| $status_kurzbz == "Diplomand")
		{
			$studstatuscode = 1;
		}
		elseif ($status_kurzbz == "Unterbrecher" )
		{
			$studstatuscode = 2;
		}
		elseif ($status_kurzbz == "Absolvent" )
		{
			$studstatuscode = 3;
		}
		elseif ($status_kurzbz == "Abbrecher" )
		{
			$studstatuscode = 4;
		}
		else
		{
			return error("Kein Statuscode gefunden!");
		}

		return success($studstatuscode);
	}

	/**
	 * Gets ZGV info in DVUH format for a prestudentstatus.
	 * @param object $prestudentstatus with FHC zgvinfo
	 * @param string $gebdatum for date check
	 * @param bool $isIncoming certain data (staat) must be omitted if incoming
	 * @return object
	 */
	private function _getZgv($prestudentstatus, $gebdatum, $isIncoming)
	{
		$zugangsberechtigung = null;

		if (!isset($prestudentstatus->zgv_code))
		{
			$this->_addWarning(
				'Zgv fehlt',
				'zgvFehlt',
				null,
				array('prestudent_id' => $prestudentstatus->prestudent_id)
			);
		}

		if (!isset($prestudentstatus->zgvdatum))
		{
			$this->_addWarning(
				'ZGV Datum fehlt',
				'zgvDatumFehlt',
				null,
				array('prestudent_id' => $prestudentstatus->prestudent_id)
			);
		}
		else
		{
			if ($prestudentstatus->zgvdatum > date("Y-m-d"))
			{
				return createError(
					"ZGV Datum in Zukunft",
					'zgvDatumInZukunft',
					null,
					array('prestudent_id' => $prestudentstatus->prestudent_id)
				);
			}

			if ($prestudentstatus->zgvdatum < $gebdatum)
			{
				return createError(
					"ZGV Datum vor Geburtsdatum",
					'zgvDatumVorGeburtsdatum',
					null,
					array('prestudent_id' => $prestudentstatus->prestudent_id)
				);
			}

			// Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden
			$zugangsvoraussetzung = str_pad($prestudentstatus->zgv_code, 2, '0', STR_PAD_LEFT);

			$zugangsberechtigung = array(
				'voraussetzung' => $zugangsvoraussetzung,
				'datum' => $prestudentstatus->zgvdatum
			);

			if (!$isIncoming)
				$zugangsberechtigung['staat'] = $prestudentstatus->zgvnation;
		}

		return success($zugangsberechtigung);
	}

	/**
	 * Gets ZGV master info in DVUH format for a prestudentstatus.
	 * @param object $prestudentstatus with FHC zgvinfo
	 * @param string $gebdatum for date check
	 * @param bool $isIncoming certain data (staat) must be omitted if incoming
	 * @param bool $isAusserordentlich certain data (staat) must be omitted if ausserordentlich
	 * @return object
	 */
	private function _getZgvMaster($prestudentstatus, $gebdatum, $isIncoming, $isAusserordentlich)
	{
		$zugangsberechtigungMA = null;

		if ($prestudentstatus->studiengang_typ == 'm' || $prestudentstatus->lgart_biscode == '1')
		{
			if (!isset($prestudentstatus->zgvmas_code))
			{
				$this->_addWarning(
					'Zgv Master fehlt',
					'zgvMasterFehlt',
					null,
					array('prestudent_id' => $prestudentstatus->prestudent_id)
				);
			}

			if (!isset($prestudentstatus->zgvmadatum))
			{
				$this->_addWarning(
					'ZGV Masterdatum fehlt',
					'zgvMasterDatumFehlt',
					null,
					array('prestudent_id' => $prestudentstatus->prestudent_id)
				);
			}
			else
			{
				if ($prestudentstatus->zgvmadatum > date("Y-m-d"))
				{
					return createError(
						"ZGV Masterdatum in Zukunft",
						'zgvMasterDatumInZukunft',
						null,
						array('prestudent_id' => $prestudentstatus->prestudent_id)
					);
				}

				if ($prestudentstatus->zgvmadatum < $prestudentstatus->zgvdatum)
				{
					return createError(
						"ZGV Masterdatum vor Zgvdatum",
						'zgvMasterDatumVorZgvdatum',
						null,
						array('prestudent_id' => $prestudentstatus->prestudent_id)
					);
				}

				if ($prestudentstatus->zgvmadatum < $gebdatum)
				{
					return createError(
						"zgvMasterDatumVorGeburtsdatum",
						'ZGV Masterdatum vor Geburtsdatum',
						array('prestudent_id' => $prestudentstatus->prestudent_id)
					);
				}

				// Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden
				$zugangsvoraussetzung_ma = str_pad($prestudentstatus->zgvmas_code, 2, '0', STR_PAD_LEFT);

				$zugangsberechtigungMA = array(
					'voraussetzung' => $zugangsvoraussetzung_ma,
					'datum' => $prestudentstatus->zgvmadatum
				);

				if (!$isAusserordentlich && !$isIncoming)
				{
					$zugangsberechtigungMA['staat'] = $prestudentstatus->zgvmanation;
				}
			}
		}

		return success($zugangsberechtigungMA);
	}

	/**
	 * Adds warning to warning list.
	 * @param $warningtext
	 * @param string $issue_fehler_kurzbz if set, issue is created and added as warning
	 * @param array $issue_fehlertext_params
	 * @param array $issue_resolution_prams
	 */
	private function _addWarning($warningtext, $issue_fehler_kurzbz = null, $issue_fehlertext_params = null, $issue_resolution_prams = null)
	{
		if (isEmptyString($issue_fehler_kurzbz))
		{
			$this->_warnings[] = error($warningtext);
		}
		else
		{
			$this->_warnings[] = createError($warningtext, $issue_fehler_kurzbz, $issue_fehlertext_params, $issue_resolution_prams);
		}
	}
}
