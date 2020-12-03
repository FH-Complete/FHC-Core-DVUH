<?php


class DVUHSyncLib
{
	private $_ci;
	private $_dbModel;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
		$this->_dbModel = new DB_Model();

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('person/benutzer_model', 'BenutzerModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('organisation/Studienplan_model', 'StudienplanModel');
		$this->_ci->load->model('codex/Orgform_model', 'OrgformModel');
		$this->_ci->load->model('codex/Zweck_model', 'ZweckModel');
		$this->_ci->load->model('codex/Aufenthaltfoerderung_model', 'AufenthaltfoerderungModel');

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	public function getStammdatenData($person_id)
	{
		$stammdaten = $this->_ci->PersonModel->getPersonStammdaten($person_id);

		if (hasData($stammdaten))
		{
			$stammdaten = getData($stammdaten);

			$adressen = array();
			$emailliste = array();

			// adresses
			$hasZustelladresse = false;
			$hasHeimatadresse = false;
			foreach ($stammdaten->adressen as $adresse)
			{
				$addr = array();
				$addr['ort'] = $adresse->ort;
				$addr['plz'] = $adresse->plz;
				$addr['strasse'] = $adresse->strasse;
				$addr['staat'] = $adresse->nation;

				if ($adresse->zustelladresse)
				{
					$addr['typ'] = 'S';
					$hasZustelladresse = true;
					$adressen[] = $addr;
				}
				if ($adresse->heimatadresse)
				{
					$addr['typ'] = 'H';
					$hasHeimatadresse = true;
					$adressen[] = $addr;
				}
			}

			if (!$hasZustelladresse)
				return error("Keine Zustelladresse angegeben!");

			if (!$hasHeimatadresse)
				return error("Keine Heimatadresse angegeben!");

			// private mail
			/*				foreach ($stammdaten->kontakte as $kontakt)
							{
								if ($kontakt->kontakttyp == 'email')
								{
									$knt = array();
									$knt['emailadresse'] = $kontakt->kontakt;
									$knt['emailtyp'] = 'PR';
									$emailliste[] = $knt;
								}
							}*/

			// business mail
			$this->_ci->BenutzerModel->addSelect('uid');
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

			$geschlecht = 'X';

			if ($stammdaten->geschlecht == 'm')
				$geschlecht = 'M';
			elseif ($stammdaten->geschlecht == 'w')
				$geschlecht = 'W';

			$studentinfo = array(
				'matrikelnummer' => $stammdaten->matr_nr,
				'adressen' => $adressen,
				'akadgrad' => $stammdaten->titelpre,
				'akadgradnach' => $stammdaten->titelpost,
				'beitragsstatus' => 'X', // TODO: X gilt nur für FHs, Bei Uni anders
				'emailliste' => $emailliste,
				'geburtsdatum' => $stammdaten->gebdatum,
				'geschlecht' => $geschlecht,
				'nachname' => $stammdaten->nachname,
				'staatsbuergerschaft' => $stammdaten->staatsbuergerschaft_code,
				'vorname' => $stammdaten->vorname,
			);

			if (isset($stammdaten->svnr))
				$studentinfo['snvr'] = $stammdaten->svnr;

			if (isset($stammdaten->ersatzkennzeichen))
				$studentinfo['ekz'] = $stammdaten->ersatzkennzeichen;

			if (isset($stammdaten->bpk))
				$studentinfo['bpk'] = $stammdaten->bpk;


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

	public function getStudyData($person_id, $semester, $prestudent_id = null)
	{
		$resultObj = new stdClass();

		$personresult = $this->_ci->PersonModel->load($person_id);

		if (hasData($personresult))
		{
			$person = getData($personresult)[0];

			if (isEmptyString($person->matr_nr) || !preg_match("/^\d{8}$/", $person->matr_nr))
				return error("Matrikelnummer ungültig");

			$resultObj->matrikelnummer = $person->matr_nr;
			$gebdatum = $person->gebdatum;

			$semester = $this->convertSemesterToFHC($semester);

			// Meldung pro Student, Studium und Semester
			$active_status = array(/*'Aufgenommener',*/ 'Student', 'Incoming', 'Diplomand');

			$qry = "SELECT ps.person_id, ps.prestudent_id, tbl_student.student_uid, pss.status_kurzbz, stg.studiengang_kz, stg.typ AS studiengang_typ,
				       stg.orgform_kurzbz AS studiengang_orgform, tbl_studienplan.orgform_kurzbz AS studienplan_orgform, 
				       pss.orgform_kurzbz AS prestudentstatus_orgform, stg.erhalter_kz, stg.max_semester AS studiengang_maxsemester,
				       tbl_lgartcode.lgart_biscode, pss.orgform_kurzbz AS studentstatus_orgform, pss.ausbildungssemester, ps.berufstaetigkeit_code,
				       tbl_student.matrikelnr AS personenkennzeichen, ps.zgv_code, ps.zgvdatum, ps.zgvnation, ps.zgvmas_code, ps.zgvmadatum, ps.zgvmanation,
				       ps.gsstudientyp_kurzbz,
				       (SELECT datum FROM public.tbl_prestudentstatus
							WHERE prestudent_id=ps.prestudent_id
							AND (status_kurzbz='Student' OR status_kurzbz='Unterbrecher')
							ORDER BY datum asc LIMIT 1) AS beginndatum,	
				       	(SELECT datum FROM public.tbl_prestudentstatus
							WHERE prestudent_id=ps.prestudent_id
							AND (status_kurzbz='Absolvent' OR status_kurzbz='Abbrecher')
							ORDER BY datum desc LIMIT 1) AS beendigungsdatum
				  FROM public.tbl_prestudent ps
				  JOIN public.tbl_student using(prestudent_id)
				  JOIN public.tbl_prestudentstatus pss USING(prestudent_id)
				  LEFT JOIN lehre.tbl_studienplan USING(studienplan_id)
				  LEFT JOIN public.tbl_studiengang stg ON ps.studiengang_kz = stg.studiengang_kz
				  LEFT JOIN bis.tbl_lgartcode ON (stg.lgartcode = tbl_lgartcode.lgartcode)
				 WHERE ps.bismelden = true
				   AND (stg.studiengang_kz < 10000 AND stg.studiengang_kz <> 0) 
				   AND ps.person_id = ? 
				   AND pss.studiensemester_kurzbz = ?
				   AND pss.status_kurzbz IN ?";

			$params = array(
				$person_id,
				$semester,
				$active_status
			);

			if (isset($prestudent_id))
			{
				$qry .= ' AND ps.prestudent_id = ?';
				$params[] = $prestudent_id;
			}

			$prestudentstatusesResult = $this->_dbModel->execReadOnlyQuery($qry, $params);

			if (hasData($prestudentstatusesResult))
			{
				$not_foerderrelevant = $this->_ci->config->item('fhc_dvuh_sync_not_foerderrelevant');

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

					if (isEmptyString($perskz) || !preg_match("/^\d{10}$/", $perskz))
						return error("Personenkennzeichen ungültig");


					// studstatuscode
					$studstatuscodeResult = $this->_getStatuscode($status_kurzbz);

					if (isError($studstatuscodeResult))
						return $studstatuscodeResult;
					if (hasData($studstatuscodeResult))
					{
						$studstatuscode = getData($studstatuscodeResult);
					}

					// studiengang kz
					$erhalter_kz = str_pad($prestudentstatus->erhalter_kz, 3, '0', STR_PAD_LEFT);
					$dvuh_stgkz = $erhalter_kz . str_pad($studiengang_kz, 4, '0', STR_PAD_LEFT);

					// zgv
					$zugangsberechtigungResult = $this->_getZgv($prestudentstatus, $gebdatum);

					if (isError($zugangsberechtigungResult))
						return $zugangsberechtigungResult;
					if (hasData($zugangsberechtigungResult))
					{
						$zugangsberechtigung = getData($zugangsberechtigungResult);
					}

					// zgv master
					$zugangsberechtigungMAResult = $this->_getZgvMaster($prestudentstatus, $gebdatum);

					if (isset($zugangsberechtigungMAResult) && isError($zugangsberechtigungMAResult))
						return $zugangsberechtigungMAResult;
					if (hasData($zugangsberechtigungMAResult))
					{
						$zugangsberechtigungMA = getData($zugangsberechtigungMAResult);
					}

					// lehrgang
					if ($prestudentstatus->studiengang_typ == 'l')
					{
						$lehrgang = array(
							'lehrgangsnr' => $dvuh_stgkz,
							'perskz' => $perskz,
							'studstatuscode' => $studstatuscode,
							'zugangsberechtigung' => $zugangsberechtigung,
							'zulassungsdatum' => $prestudentstatus->beginndatum
						);

						if (isset($prestudentstatus->beendigungsdatum))
							$lehrgang['beendigungsdatum'] = $prestudentstatus->beendigungsdatum;

						if (isset($zugangsberechtigungMA))
							$lehrgang['zugangsberechtigungMA'] = $zugangsberechtigungMA;

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

						// ausbildungssemester
						$ausbildungssemesterResult = $this->_getAusbildungssemester($prestudentstatus);

						if (isError($ausbildungssemesterResult))
							return $ausbildungssemesterResult;
						else
							$ausbildungssemester = getData($ausbildungssemesterResult);

						$isIncoming = $prestudentstatus->status_kurzbz == 'Incoming';
						$isAusserordentlich = mb_substr($prestudentstatus->personenkennzeichen,3,1) == '9';

						// gemeinsame Studien
						$gemeinsam = null;
						$gemeinsamResult = $this->_getGemeinsameStudien($prestudentstatus, $semester);

						if (isset($gemeinsamResult) && isError($gemeinsamResult))
							return $gemeinsamResult;
						if (hasData($gemeinsamResult))
						{
							$gemeinsam = getData($gemeinsamResult);
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

						// bmffoerderrelevant - students marked in configarray, incomingsm, ausserordentliche, gemeinsame Studien externe not foerderrelevant
						if (in_array($prestudent_id, $not_foerderrelevant) || $isIncoming || $isAusserordentlich
							|| (isset($gemeinsam) && $gemeinsam['studtyp'] =='E'))
							$bmffoerderrelevant = 'N';
						else
							$bmffoerderrelevant = 'J';

						// orgform code
						$orgform_code = $this->_getOrgformcode($orgform_kurzbz);

						if (isError($orgform_code))
							return $orgform_code;
						if (hasData($orgform_code))
						{
							$orgformcode = getData($orgform_code);
						}

						// standortcode
						$standortcode = $this->_getStandort($prestudent_id, $studiengang_kz);

						if (isError($standortcode))
							return $standortcode;
						if (hasData($standortcode))
						{
							$standortcode = getData($standortcode);
						}

						// berufstätigkeitcode
						if (isEmptyString($prestudentstatus->berufstaetigkeit_code) && $orgformcode != '1' ) // wenn nicht Vollzeit
							return error('Berufstätigkeitcode fehlt');

						// TODO: there is no code 0 - use 1 instead?
						$berufstaetigkeit_code = $prestudentstatus->berufstaetigkeit_code == '0' ? '1' : $prestudentstatus->berufstaetigkeit_code;

						$studiengang = array(
							'disloziert' => 'N', // J,N,j,n
							'ausbildungssemester' => $ausbildungssemester,
							'bmwfwfoerderrelevant' => $bmffoerderrelevant,
							'orgformcode' => $orgformcode,
							'perskz' => $perskz,
							'standortcode' => $standortcode,
							'stgkz' => $dvuh_stgkz, // Laut Dokumentation 3stellige ErhKZ + 4stellige STGKz
							'studstatuscode' => $studstatuscode,
							//'vornachperskz' => '1910331006',
							'zugangsberechtigung' => $zugangsberechtigung,
							'zulassungsdatum' => $prestudentstatus->beginndatum
						);

						if (isset($berufstaetigkeit_code))
							$studiengang['berufstaetigkeit_code'] = $berufstaetigkeit_code;

						if (isset($gemeinsam))
							$studiengang['gemeinsam'] = $gemeinsam;

						if (isset($mobilitaet))
							$studiengang['mobilitaet'] = $mobilitaet;

						if (isset($zugangsberechtigungMA))
							$studiengang['zugangsberechtigungMA'] = $zugangsberechtigungMA;

						if (isset($prestudentstatus->beendigungsdatum))
							$studiengang['beendigungsdatum'] = $prestudentstatus->beendigungsdatum;


						$studiengaenge[] = $studiengang;
					}

				}
			}
			else
			{
				return error('Keine aktiven Studenten für das gegebene Semester');
			}
		}

		$resultObj->studiengaenge = $studiengaenge;
		$resultObj->lehrgaenge = $lehrgaenge;
		$resultObj->prestudent_ids = $prestudent_ids;

		return success($resultObj);
	}

	public function convertSemesterToFHC($semester)
	{
		if (!preg_match("/^\d{4}(S|W)$/", $semester))
			return $semester;

		return mb_substr($semester, -1).'S'.mb_substr($semester, 0,4);
	}

	private function _getAusbildungssemester($prestudentstatus)
	{
		$ausbildungssemester = $prestudentstatus->ausbildungssemester > $prestudentstatus->studiengang_maxsemester
			? $prestudentstatus->studiengang_maxsemester
			: $prestudentstatus->ausbildungssemester;

		// ausbildungssemester for Diplomanden
		if ($prestudentstatus->status_kurzbz == 'Diplomand')
		{
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
				return error("error when getting Diplomanden");
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
		}

		return success($ausbildungssemester);
	}

	private function _getGemeinsameStudien($prestudentstatus, $semester)
	{
		$prestudent_id = $prestudentstatus->prestudent_id;
		$gsstudientyp_kurzbz = $prestudentstatus->gsstudientyp_kurzbz;

		$kodex_studstatuscode_array = $this->_ci->config->item('fhc_dvuh_sync_student_statuscode');

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

		$gemeinsamestudienResult = $this->_dbModel->execReadOnlyQuery("SELECT
								tbl_mobilitaet.*,
								tbl_gsprogramm.programm_code,
								tbl_firma.partner_code
							FROM
								bis.tbl_mobilitaet
								LEFT JOIN bis.tbl_gsprogramm USING(gsprogramm_id)
								LEFT JOIN public.tbl_firma USING(firma_id)
							WHERE
								prestudent_id=?
								AND (studiensemester_kurzbz=? AND status_kurzbz = 'Absolvent')
							ORDER BY tbl_mobilitaet.insertamum DESC limit 1;",
			array(
				$prestudent_id,
				$semester
			)
		);

		if (isError($gemeinsamestudienResult))
			return error("Fehler beim Holen gemeinsamer Studien");
		if (hasData($gemeinsamestudienResult))
		{
			$gemeinsamestudien = getData($gemeinsamestudienResult)[0];

			if (isset($kodex_studstatuscode_array[$gemeinsamestudien->status_kurzbz]))
				$gs_studstatuscode = $kodex_studstatuscode_array[$gemeinsamestudien->status_kurzbz];
			else
				return error('Kein Status für gemeinsame Studien gefunden');

			if (isset($kodex_studientyp_array[$gsstudientyp_kurzbz]))
				$studtyp = $kodex_studientyp_array[$gsstudientyp_kurzbz];
			else
				return error('Kein Studientyp für gemeinsame Studien gefunden');

			$gemeinsam = array(
				'ausbildungssemester' => $gemeinsamestudien->ausbildungssemester,
				'mobilitaetprogrammcode' => $gemeinsamestudien->mobilitaetsprogramm_code,
				'partnercode' => $gemeinsamestudien->partner_code,
				'programmnr' => $gemeinsamestudien->programm_code,
				'studstatuscode' => $gs_studstatuscode,
				'studtyp' => $studtyp
			);

			return success($gemeinsam);
		}
	}

	private function _getMobilitaet($studiensemester, $prestudentstatus)
	{
		$this->_ci->StudiensemesterModel->addSelect('start, ende');
		$semesterResult = $this->_ci->StudiensemesterModel->load($studiensemester);

		if (hasData($semesterResult))
		{
			$semester = getData($semesterResult)[0];
		}
		else
			return error("no correct semester provided");

		$ioResult = $this->_dbModel->execReadOnlyQuery("SELECT *
					FROM bis.tbl_bisio WHERE student_uid=?
					AND bis >= ? AND von <= ?;",
					array($prestudentstatus->student_uid, $semester->start, $semester->ende)
		);

		$mobilitaeten = array();

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
				$adauer = (is_null($avon) || is_null($abis)) ? null : $this->_dateDiff($avon, $abis);

				// Aufenthaltszweckcode --------------------------------------------------------------------------------
				$this->_ci->ZweckModel->addSelect('tbl_zweck.zweck_code');
				$this->_ci->ZweckModel->addJoin('bis.tbl_bisio_zweck', 'zweck_code');
				$bisio_zweck_result = $this->_ci->ZweckModel->loadWhere(array('bisio_id' => $bisio_id));

				if (hasData($bisio_zweck_result))
				{

					$bisio_zweck = getData($bisio_zweck_result);

					$zweck_code_arr = array();

					// Bei Incomings...
					if ($prestudentstatus == 'Incoming')
					{
						// ...max 1 Aufenthaltszweck
						if (count($bisio_zweck) > 1)
						{
							return error("Es sind" . count($bisio_zweck) . " Aufenthaltszwecke eingetragen (max. 1 Zweck für Incomings)");
						}

						//...nur Zweck 1, 2 oder 3 erlaubt
						if (count($bisio_zweck) == 1 && !in_array($bisio_zweck[0]->zweck_code, array(1, 2, 3)))
						{
							return error("Aufenthaltszweckcode ist " . $bisio_zweck[0]->zweck_code . " (f&uuml;r Incomings ist nur Zweck 1, 2, 3 erlaubt)");
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
								return error("Aufenthaltzweckcode 1, 2, 3 d&uuml;rfen nicht gemeinsam gemeldet werden");
							}

							$zweck_code_arr[] = $row_zweck->zweck_code;
						}
					}

					// Aufenthaltfoerderungscode ---------------------------------------------------------------------------
					$aufenthaltfoerderung_code_arr = array();

					// Nur bei Outgoings Aufenthaltsfoerderungscode melden
					if ($prestudentstatus != 'Incoming')
					{
						$this->_ci->AufenthaltfoerderungModel->addSelect('tbl_aufenthaltfoerderung.aufenthaltfoerderung_code');
						$this->_ci->AufenthaltfoerderungModel->addJoin('bis.tbl_bisio_aufenthaltfoerderung', 'aufenthaltfoerderung_code');
						$this->_ci->AufenthaltfoerderungModel->addOrder('tbl_aufenthaltfoerderung.aufenthaltfoerderung_code');
						$bisio_foerderung_result = $this->_ci->AufenthaltfoerderungModel->loadWhere(array('bisio_id' => $bisio_id));

/*						$bisio_foerderung = new bisio();
						$bisio_foerderung->getFoerderungen($rowio->bisio_id);*/

						// ... mindestens 1 Aufenthaltfoerderung melden, wenn Auslandsaufenthalt >= 29 Tage
						if ((!hasData($bisio_foerderung_result)) && $adauer >= 29)
						{
							return error(
								"Keine Aufenthaltsfoerderung angegeben (bei Outgoings >= 29 Tage Monat im Ausland muss mind. 1 gemeldet werden)"
							);
						}

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

						if (strtotime($abis) < strtotime(date('Y-m-d')))
							$aufenthalt_finished = true;
						else
							$aufenthalt_finished = false;

						if (isEmptyString($ioitem->ects_erworben) && $adauer >= 29 && $aufenthalt_finished)
						{
							return error(
								"Erworbene ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)");
						}

						if (isEmptyString($ioitem->ects_angerechnet) && $adauer >= 29 && $aufenthalt_finished)
						{
							return error(
								"Angerechnete ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)");
						}
					}
				}
				else
					return error('Bisio Zweck nicht gefunden');

				$mobilitaet = array(
					'bis' => $abis,
					'programm' => $programm,
					'staat' => $staat,
					'von' => $avon,
					'zweck' => $zweck_code_arr
				);

				if (isset($aufenthaltfoerderung_code_arr) && count($aufenthaltfoerderung_code_arr) > 1)
					$mobilitaet['aufenthaltfoerderungcode'] = $aufenthaltfoerderung_code_arr;

				if (!isEmptyString($ioitem->ects_angerechnet))
					$mobilitaet['ectsangerechnet'] = $ioitem->ects_angerechnet;

				if (!isEmptyString($ioitem->ects_erworben))
					$mobilitaet['ectserworben'] = $ioitem->ects_erworben;

				$mobilitaeten[] = $mobilitaet;
			}
		}

		return success($mobilitaeten);
	}

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

	private function _getStandort($prestudent_id, $studiengang_kz)
	{
		$student_standort = $this->_ci->config->item('fhc_dvuh_sync_student_standort');
		$standortcode_wien = $this->_ci->config->item('fhc_dvuh_sync_standortcode_wien');
		$stg_standort_array = $this->_ci->config->item('fhc_dvuh_sync_stg_standortcode');

		// if standort specified in config, take it, otherwise fallback depending on studiengang location
		if (isset($student_standort[$prestudent_id]))
			$standortcode = $student_standort[$prestudent_id];
		else
		{
			$standortcode = $standortcode_wien;
			if (isset($stg_standort_array[$studiengang_kz]))
				$standortcode = $stg_standort_array[$studiengang_kz];
		}

		$standortcode = str_pad($standortcode, 3, '0', STR_PAD_LEFT);

		return success($standortcode);
	}

	private function _getStatuscode($status_kurzbz)
	{
		if ($status_kurzbz == "Student" || $status_kurzbz == "Outgoing"
			|| $status_kurzbz == "Incoming" || $status_kurzbz == "Praktikant"
			|| $status_kurzbz == "Diplomand")
		{
			$studstatuscode = 1;
		}
		else if($status_kurzbz == "Unterbrecher" )
		{
			$studstatuscode = 2;
		}
		else if($status_kurzbz == "Absolvent" )
		{
			$studstatuscode = 3;
		}
		else if($status_kurzbz == "Abbrecher" )
		{
			$studstatuscode = 4;
		}
		else
		{
			return error("Kein Statuscode gefunden!");
		}

		return success($studstatuscode);
	}

	private function _getZgv($prestudentstatus, $gebdatum)
	{
		$zugangsberechtigung = null;

		if (!isset($prestudentstatus->zgv_code))
			return error("Zgv fehlt");

		if($prestudentstatus->zgvdatum > date("Y-m-d"))
		{
			return error("ZGV Datum in Zukunft");
		}
		if($prestudentstatus->zgvdatum < $gebdatum)
		{
			return error("ZGV Datum vor Geburtsdatum");
		}

		$zugangsvoraussetzung = str_pad($prestudentstatus->zgv_code, 2, '0', STR_PAD_LEFT);

		$zugangsberechtigung = array(
			'datum' => $prestudentstatus->zgvdatum,
			'staat' => $prestudentstatus->zgvnation,
			'voraussetzung' => $zugangsvoraussetzung  // Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden
		);

		return success($zugangsberechtigung);
	}

	private function _getZgvMaster($prestudentstatus, $gebdatum)
	{
		$zugangsberechtigungMA = null;

		if ($prestudentstatus->studiengang_typ == 'm' || $prestudentstatus->lgart_biscode == '1')
		{
			if (!isset($prestudentstatus->zgvmas_code))
				return error("Zgv Master fehlt");

			if($prestudentstatus->zgvmadatum > date("Y-m-d"))
			{
				return error("ZGV Masterdatum in Zukunft");
			}
			if($prestudentstatus->zgvmadatum < $prestudentstatus->zgvdatum)
			{
				return error("ZGV Masterdatum bevor Zgvdatum");
			}
			if($prestudentstatus->zgvmadatum < $gebdatum)
			{
				return error("ZGV Masterdatum vor Geburtsdatum");
			}

			$zugangsvoraussetzung_ma = str_pad($prestudentstatus->zgvmas_code, 2, '0', STR_PAD_LEFT);

			$zugangsberechtigungMA = array(
				'datum' => $prestudentstatus->zgvmadatum,
				'staat' => $prestudentstatus->zgvmanation,
				'voraussetzung' => $zugangsvoraussetzung_ma  // Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden
			);
		}

		return success($zugangsberechtigungMA);
	}

	private function _dateDiff($datum1, $datum2)
	{
		$datetime1 = new DateTime($datum1);
		$datetime2 = new DateTime($datum2);
		$interval = $datetime1->diff($datetime2);
		return $interval->format('%R%a');
	}
}
