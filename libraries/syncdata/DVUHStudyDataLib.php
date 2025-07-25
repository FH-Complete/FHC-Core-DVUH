<?php

require_once 'DVUHErrorProducerLib.php';

/**
 * Library for retrieving StudyData data from FHC for DVUH.
 * Extracts data from FHC db, performs data quality checks and puts data in DVUH form.
 */
class DVUHStudyDataLib extends DVUHErrorProducerLib
{
	protected $_ci;
	private $_dbModel;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/JQMSchedulerLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');

		// load models
		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_ci->load->model('crm/prestudent_model', 'PrestudentModel');
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('codex/Orgform_model', 'OrgformModel');
		$this->_ci->load->model('codex/Zweck_model', 'ZweckModel');
		$this->_ci->load->model('codex/Aufenthaltfoerderung_model', 'AufenthaltfoerderungModel');
		$this->_ci->load->model('codex/Bismeldestichtag_model', 'BismeldestichtagModel');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');

		$this->_dbModel = new DB_Model();
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Retrieves studydata for a person and semester, performs checks, prepares data for DVUH.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz
	 * @param int $prestudent_id optionally, retrieve only data for one prestudent of the person
	 * @return object success with studentinfo or error
	 */
	public function getStudyData($person_id, $studiensemester_kurzbz, $prestudent_id = null)
	{
		$resultObj = new stdClass();

		$personresult = $this->_ci->PersonModel->load($person_id);

		if (hasData($personresult))
		{
			$person = getData($personresult)[0];

			if (isEmptyString($person->matr_nr))
				$this->addError('Matrikelnummer nicht gesetzt', 'matrNrFehlt');
			elseif (!$this->_ci->dvuhcheckinglib->checkMatrikelnummer($person->matr_nr))
				$this->addError("Matrikelnummer ungültig", 'matrikelnrUngueltig', array($person->matr_nr));

			$resultObj->matrikelnummer = $person->matr_nr;
			$gebdatum = $person->gebdatum;

			$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz');
			$active_status_kurzbz = $this->_ci->config->item('fhc_dvuh_active_student_status_kurzbz');
			$finished_status_kurzbz = $this->_ci->config->item('fhc_dvuh_finished_student_status_kurzbz');
			$terminated_status_kurzbz = $this->_ci->config->item('fhc_dvuh_terminated_student_status_kurzbz');

			// Meldung pro Student, Studium und Semester
			$qry = "SELECT DISTINCT ON (ps.prestudent_id) ps.person_id, ps.prestudent_id, tbl_student.student_uid,
						pss.status_kurzbz, pss.datum AS status_datum,
						stg.studiengang_kz, stg.typ AS studiengang_typ,
						stg.orgform_kurzbz AS studiengang_orgform, tbl_studienplan.orgform_kurzbz AS studienplan_orgform,
						pss.orgform_kurzbz AS prestudentstatus_orgform, stg.erhalter_kz, stg.max_semester AS studiengang_maxsemester, stg.lgartcode,
						tbl_lgartcode.lgart_biscode, pss.orgform_kurzbz AS studentstatus_orgform, pss.ausbildungssemester, ps.berufstaetigkeit_code,
						tbl_student.matrikelnr AS personenkennzeichen, ps.zgv_code, ps.zgvdatum, ps.zgvnation,
						ps.zgvmas_code, ps.zgvmadatum, ps.zgvmanation, ps.gsstudientyp_kurzbz, ps.dual,
						(
							SELECT datum FROM public.tbl_prestudentstatus
							WHERE prestudent_id=ps.prestudent_id
							AND status_kurzbz IN ('Student', 'Unterbrecher', 'Incoming')
							ORDER BY datum ASC LIMIT 1
						) AS beginndatum,
						(
							SELECT -- use abschlusspruefung date, except when there is none or it is a terminated status
								CASE WHEN ps_datum.status_kurzbz IN ?
								THEN ps_datum.datum
								ELSE COALESCE (pr_datum.datum, ps_datum.datum)
								END
							FROM
							(
								SELECT prestudent_id, pss_datum.datum, pss_datum.status_kurzbz
								FROM public.tbl_prestudentstatus pss_datum
								WHERE pss_datum.prestudent_id=ps.prestudent_id
								AND pss_datum.studiensemester_kurzbz = pss.studiensemester_kurzbz
								AND pss_datum.status_kurzbz IN ?
								AND pss_datum.datum <= NOW()
							) ps_datum
							LEFT JOIN -- if there is an abschlusspruefung date, use it as end date
							(
								SELECT std.prestudent_id, pr.datum
								FROM lehre.tbl_abschlusspruefung pr
								JOIN public.tbl_student std USING (student_uid)
								WHERE pr.datum <= NOW()
							) pr_datum USING (prestudent_id)
							ORDER BY pr_datum.datum DESC NULLS LAST, ps_datum.datum DESC
							LIMIT 1
						) AS beendigungsdatum
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
				isEmptyArray($terminated_status_kurzbz) ? array('') : $terminated_status_kurzbz,
				isEmptyArray($finished_status_kurzbz) ? array('') : $finished_status_kurzbz,
				$person_id,
				$studiensemester_kurzbz
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
					$prestudent_ids[] = $prestudent_id; // save prestudent id to know which prestudents were synced
					$status_kurzbz = $prestudentstatus->status_kurzbz;
					$studiengang_kz = $prestudentstatus->studiengang_kz;

					// personenkennzeichen
					$perskz = trim($prestudentstatus->personenkennzeichen);

					if (!$this->_ci->dvuhcheckinglib->checkPersonenkennzeichen($perskz))
					{
						$this->addError(
							"Personenkennzeichen ungültig",
							'personenkennzeichenUngueltig',
							array($perskz),
							array('student_uid' => $prestudentstatus->student_uid)
						);
					}

					// studstatuscode
					$studstatuscode = null;
					$studstatuscodeResult = $this->_getStatuscode($status_kurzbz);

					if (isError($studstatuscodeResult))
						return $studstatuscodeResult;
					if (hasData($studstatuscodeResult))
					{
						$studstatuscode = getData($studstatuscodeResult);
					}

					// booleans isIncoming, isAusserordentlich, isLehrgang
					$isIncoming = $status_kurzbz == 'Incoming';

					// Ausserordentlicher Studierender (4.Stelle in Personenkennzeichen = 9)
					$isAusserordentlich = $this->_ci->dvuhcheckinglib->checkIfAusserordentlich($prestudentstatus->personenkennzeichen);

					// Lehrgang - if Lehrgangsart is set, but not ausserordentlich
					$isLehrgang = !$isAusserordentlich && isset($prestudentstatus->lgartcode);

					// studiengang kz
					$meldeStudiengangRes = $this->_ci->dvuhconversionlib->getMeldeStudiengangKz(
						$studiengang_kz,
						$prestudentstatus->erhalter_kz,
						$isAusserordentlich
					);

					if (isError($meldeStudiengangRes))
						return $meldeStudiengangRes;

					$melde_studiengang_kz = null;
					if (hasData($meldeStudiengangRes))
						$melde_studiengang_kz = getData($meldeStudiengangRes);

					// studtyp - if extern, certain data should not be sent to DVUH
					$gsstudientyp_kurzbz = $prestudentstatus->gsstudientyp_kurzbz;

					$kodex_studientyp_array = array();
					$gsstudientypResult = $this->_dbModel->execReadOnlyQuery(
						"SELECT * FROM bis.tbl_gsstudientyp"
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

					// orgform_kurzbz
					$orgform_kurzbz = null;
					if (isset($prestudentstatus->studienplan_orgform))
						$orgform_kurzbz = $prestudentstatus->studienplan_orgform;
					elseif (isset($prestudentstatus->prestudentstatus_orgform))
						$orgform_kurzbz = $prestudentstatus->prestudentstatus_orgform;
					elseif (isset($prestudentstatus->studiengang_orgform))
						$orgform_kurzbz = $prestudentstatus->studiengang_orgform;

					$zugangsberechtigung = null;
					if (!$isAusserordentlich)
					{
						// orgform code if not ausserordentlich
						$orgformcode = null;
						$orgform_code = $this->_getOrgformcode($orgform_kurzbz);

						if (isError($orgform_code))
							return $orgform_code;

						if (hasData($orgform_code))
						{
							$orgformcode = getData($orgform_code);
						}

						// zgv if not ausserordentlich
						$zugangsberechtigungResult = $this->_getZgv($prestudentstatus, $gebdatum, $isIncoming);

						if (isError($zugangsberechtigungResult))
							return $zugangsberechtigungResult;

						if (hasData($zugangsberechtigungResult))
						{
							$zugangsberechtigung = getData($zugangsberechtigungResult);
						}
					}

					// zgv master
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

					// Mobilität
					$mobilitaet = null;
					$mobilitaetResult = $this->_getMobilitaet($studiensemester_kurzbz, $prestudentstatus);

					if (isset($mobilitaetResult) && isError($mobilitaetResult))
						return $mobilitaetResult;

					if (hasData($mobilitaetResult))
					{
						$mobilitaet = getData($mobilitaetResult);
					}

					// gemeinsame Studien
					$gemeinsam = null;
					$gemeinsamResult = $this->_getGemeinsameStudien(
						$prestudent_id,
						$studiensemester_kurzbz,
						$studtyp,
						$prestudentstatus->beendigungsdatum
					);

					if (isset($gemeinsamResult) && isError($gemeinsamResult))
						return $gemeinsamResult;

					if (hasData($gemeinsamResult))
					{
						$gemeinsam = getData($gemeinsamResult);
					}

					// meldestatus
					$meldeStatusRes = $this->_getMeldeStatus(
						$prestudent_id,
						$studiensemester_kurzbz,
						$studstatuscode,
						$prestudentstatus->status_datum,
						isset($mobilitaet)
					);

					if (isError($meldeStatusRes))
						return $meldeStatusRes;

					if (hasData($meldeStatusRes))
					{
						$meldestatus = getData($meldeStatusRes);
					}

					// lehrgang
					if ($isLehrgang)
					{
						$lehrgang = array(
							'lehrgangsnr' => $melde_studiengang_kz,
							'perskz' => $perskz
						);

						foreach ($lehrgang as $idx => $item)
						{
							if (!isset($item) || isEmptyString($item))
								$this->addError('Lehrgangdaten fehlen: ' . $idx, 'lehrgangdatenFehlen', array($idx));
						}

						if (isset($orgform_code))
							$lehrgang['orgformcode'] = $orgformcode;

						if (isset($studstatuscode) && !$isExtern)
							$lehrgang['studstatuscode'] = $studstatuscode;

						if (isset($meldestatus))
							$lehrgang['meldestatus'] = $meldestatus;

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

						if (isset($gemeinsam))
							$lehrgang['gemeinsam'] = $gemeinsam;

						if (isset($mobilitaet))
							$lehrgang['mobilitaet'] = $mobilitaet;

						$lehrgaenge[] = $lehrgang;
					}
					else // studiengang
					{
						// ausbildungssemester
						if (!$isIncoming && !$isAusserordentlich)
						{
							$ausbildungssemesterResult = $this->_getAusbildungssemester($prestudentstatus);

							if (isError($ausbildungssemesterResult))
								return $ausbildungssemesterResult;
							else
								$ausbildungssemester = getData($ausbildungssemesterResult);

							// Unterbrechungsdatum if Unterbrecher
							if ($status_kurzbz == 'Unterbrecher')
							{
								$unterbrechungsdatumRes = $this->_ci->fhcmanagementlib->getFirstDateOfPrestudentStatusSeries(
									$prestudent_id,
									$studiensemester_kurzbz,
									array('Unterbrecher')
								);

								if (isError($unterbrechungsdatumRes)) return $unterbrechungsdatumRes;

								$unterbrechungsdatum = hasData($unterbrechungsdatumRes) ? getData($unterbrechungsdatumRes) : null;
							}

							// Wiedereintrittsdatum if Student after Unterbrecher
							if (in_array($status_kurzbz, $active_status_kurzbz))
							{
								$wiedereintrittsdatumRes = $this->_ci->fhcmanagementlib->getFirstDateOfPrestudentStatusSeriesAfterStatus(
									$prestudent_id,
									$studiensemester_kurzbz,
									$active_status_kurzbz,
									'Unterbrecher'
								);

								if (isError($wiedereintrittsdatumRes))
									return $wiedereintrittsdatumRes;

								$wiedereintrittsdatum = hasData($wiedereintrittsdatumRes) ? getData($wiedereintrittsdatumRes) : null;
							}
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

						// duales Studium
						$dualesstudium = $prestudentstatus->dual === true ? 'J' : 'N';

						if (!$isAusserordentlich)
						{
							// berufstätigkeitcode, wenn nicht Vollzeit und nicht ausserordentlich
							if ($orgformcode != '1')
							{
								if (!isset($prestudentstatus->berufstaetigkeit_code) || !is_numeric($prestudentstatus->berufstaetigkeit_code))
								{
									$this->addWarning(
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
							'dualesstudium' => $dualesstudium,
							'perskz' => $perskz,
							'stgkz' => $melde_studiengang_kz // Laut Dokumentation 3stellige ErhKZ + 4stellige STGKz
						);

						foreach ($studiengang as $idx => $item)
						{
							if (!isset($item) || isEmptyString($item))
								$this->addError('Studiumdaten fehlen', 'studiumdatenFehlen', array($idx));
						}

						if (isset($orgform_code))
							$studiengang['orgformcode'] = $orgformcode;

						if (isset($studstatuscode) && !$isExtern)
							$studiengang['studstatuscode'] = $studstatuscode;

						if (isset($meldestatus))
							$studiengang['meldestatus'] = $meldestatus;

						if (isset($zulassungsdatum) && !$isExtern)
							$studiengang['zulassungsdatum'] = $zulassungsdatum;

						if (isset($unterbrechungsdatum) && !$isExtern)
							$studiengang['unterbrechungsdatum'] = $unterbrechungsdatum;

						if (isset($wiedereintrittsdatum) && !$isExtern)
							$studiengang['wiedereintrittsdatum'] = $wiedereintrittsdatum;

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

		if ($this->hasError())
			return error($this->readErrors());

		return success($resultObj);
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
		$diplomandResult = $this->_dbModel->execReadOnlyQuery(
			"SELECT
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
	 * @param int $prestudent_id
	 * @param string $studiensemester_kurzbz for getting previous semester for Absolventen
	 * @param string $studtyp pass to gsdata
	 * @param string $beendiungsdatum end date of GS stay
	 * @return object error or success with gsdata
	 */
	private function _getGemeinsameStudien($prestudent_id, $studiensemester_kurzbz, $studtyp, $beendigungsdatum)
	{
		if (!isset($studtyp))
			return error('Kein Studientyp für gemeinsame Studien gefunden');

		$kodex_studstatuscode_array = $this->_ci->config->item('fhc_dvuh_sync_student_statuscode');
		$finished_status_kurzbz = $this->_ci->config->item('fhc_dvuh_finished_student_status_kurzbz');

		$gemeinsamestudienResult = $this->_dbModel->execReadOnlyQuery(
			"SELECT
					mo.*,
					tbl_gsprogramm.programm_code,
					tbl_gsprogramm.studienkennung_uni,
					tbl_firma.partner_code,
						CASE WHEN EXISTS
						(SELECT 1 FROM bis.tbl_mobilitaet
						WHERE prestudent_id = mo.prestudent_id
						AND studiensemester_kurzbz = mo.studiensemester_kurzbz
						AND status_kurzbz IN ?)
					THEN TRUE
					ELSE FALSE
					END AS abgeschlossen
				FROM
					bis.tbl_mobilitaet mo
					LEFT JOIN bis.tbl_gsprogramm USING(gsprogramm_id)
					LEFT JOIN public.tbl_firma USING(firma_id)
				WHERE
					prestudent_id=?
					AND studiensemester_kurzbz=?
				ORDER BY mo.insertamum DESC LIMIT 1;",
			array(
				isEmptyArray($finished_status_kurzbz) ? array('') : $finished_status_kurzbz,
				$prestudent_id,
				$studiensemester_kurzbz
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

			// check required fields for gemeinsame Studien
			$requiredFields = array('mobilitaetsprogramm_code', 'partner_code', 'programm_code');

			foreach ($requiredFields as $field)
			{
				if (!isset($gemeinsamestudien->{$field}))
				{
					$this->addError(
						"Daten für gemeinsames Studium fehlen: ".$field,
						'gsdatenFehlen',
						array($field),
						array('mobilitaet_id' => $gemeinsamestudien->mobilitaet_id, 'fehlendes_feld' => $field)
					);
				}
			}

			$gemeinsamData = array(
				'ausbildungssemester' => $gemeinsamestudien->ausbildungssemester,
				'mobilitaetprogrammcode' => $gemeinsamestudien->mobilitaetsprogramm_code,
				'partnercode' => $gemeinsamestudien->partner_code,
				'programmnr' => $gemeinsamestudien->programm_code,
				'studstatuscode' => $gs_studstatuscode,
				'studtyp' => $studtyp
			);

			if (isset($gemeinsamestudien->studienkennung_uni)
				&& !$this->_ci->dvuhcheckinglib->checkStudienkennunguni($gemeinsamestudien->studienkennung_uni))
			{
				$this->addError(
					"Studienkennung Uni ungültig, GS Programm Code ".$gemeinsamestudien->programm_code,
					'studienkennunguniUngueltig',
					array($gemeinsamestudien->programm_code),
					array('gsprogramm_id' => $gemeinsamestudien->gsprogramm_id)
				);
			}

			$gemeinsamData['studienkennunguni'] = $gemeinsamestudien->studienkennung_uni;

			// set beendigungsdatum only if finished studies
			if ($gemeinsamestudien->abgeschlossen === true)
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

		// get Mobilitäten of the semester, stay must have already started
		$ioResult = $this->_dbModel->execReadOnlyQuery(
			"SELECT *
			FROM bis.tbl_bisio WHERE student_uid=?
			AND von <= NOW() AND (bis >= ? OR bis IS NULL) AND von <= ?;",
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
				$herkunftslandcode = $ioitem->herkunftsland_code;
				$adauer = (is_null($avon) || is_null($abis)) ? null : dateDiff($avon, $abis);
				if (strtotime($abis) <= strtotime(date('Y-m-d')))
					$aufenthalt_finished = true;
				else
					$aufenthalt_finished = false;

				// Herkunftsland cannot be Empty
				if (isEmptyString($herkunftslandcode))
				{
					$this->addError(
						"Herkunftsland fehlt",
						'herkunftslandFehlt',
						null,
						array('bisio_id' => $bisio_id)
					);
				}

				// Aufenthaltszweckcode --------------------------------------------------------------------------------
				$this->_ci->ZweckModel->addSelect('tbl_zweck.zweck_code');
				$this->_ci->ZweckModel->addJoin('bis.tbl_bisio_zweck', 'zweck_code');
				$bisio_zweck_result = $this->_ci->ZweckModel->loadWhere(array('bisio_id' => $bisio_id));
				$zweck_code_arr = array();

				if (hasData($bisio_zweck_result))
				{
					$bisio_zweck = getData($bisio_zweck_result);


					// Bei Incomings...
					if ($prestudentstatus->status_kurzbz == 'Incoming')
					{
						// ...max 1 Aufenthaltszweck
						if (count($bisio_zweck) > 1)
						{
							$this->addError(
								"Es sind" . count($bisio_zweck) . " Aufenthaltszwecke eingetragen (max. 1 Zweck für Incomings)",
								'zuVieleZweckeIncomingPlausi',
								array(count($bisio_zweck)),
								array('bisio_id' => $bisio_id)
							);
						}

						//...nur Zweck 1, 2 oder 3 erlaubt
						if (count($bisio_zweck) == 1 && !in_array($bisio_zweck[0]->zweck_code, array(1, 2, 3)))
						{
							$this->addError(
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
								$this->addError(
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
							$this->addError(
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

						if ($adauer >= 29 && $aufenthalt_finished)
						{
							if (isEmptyString($ioitem->ects_erworben))
							{
								$this->addError(
									"Erworbene ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)",
									'outgoingErworbeneEctsFehlenPlausi',
									null,
									array('bisio_id' => $bisio_id)
								);
							}

							if (isEmptyString($ioitem->ects_angerechnet))
							{
								$this->addError(
									"Angerechnete ECTS fehlen (Meldepflicht bei Outgoings >= 29 Tage Monat im Ausland)",
									'outgoingAngerechneteEctsFehlenPlausi',
									null,
									array('bisio_id' => $bisio_id)
								);
							}
						}

						$ects_erworben = $ioitem->ects_erworben;
						$ects_angerechnet = $ioitem->ects_angerechnet;
					}
				}
				else
				{
					$this->addError(
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
					'zweck' => $zweck_code_arr,
					'herkunftslandcode' => $herkunftslandcode
				);

				if ($aufenthalt_finished)
					$mobilitaet['bis'] = $abis;

				if (isset($aufenthaltfoerderung_code_arr) && count($aufenthaltfoerderung_code_arr) > 0)
					$mobilitaet['aufenthaltfoerderungcode'] = $aufenthaltfoerderung_code_arr;

				if (isset($ects_angerechnet) && !isEmptyString($ects_angerechnet))
					$mobilitaet['ectsangerechnet'] = round($ects_angerechnet);

				if (isset($ects_erworben) && !isEmptyString($ects_erworben))
					$mobilitaet['ectserworben'] = round($ects_erworben);

				$mobilitaet['id'] = $bisio_id;

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
		$orgformCode = null;
		$orgform_code_array = array();

		// load valid Orgforms
		$this->_ci->OrgformModel->addSelect('orgform_kurzbz, code');
		$orgformcodesResult = $this->_ci->OrgformModel->loadWhere(array('rolle' => true));

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

			// error when valid Orgform not found
			if (isset($orgform_code_array[$orgform_kurzbz]))
				$orgformCode = $orgform_code_array[$orgform_kurzbz];
			else
				$this->addError("Orgform ungültig", 'orgformUngueltig');

			return success($orgformCode);
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
		$studstatuscode_array = $this->_ci->config->item('fhc_dvuh_sync_student_statuscode');

		if (!isset($studstatuscode_array[$status_kurzbz]))
			return error("Kein Statuscode gefunden!");

		return success($studstatuscode_array[$status_kurzbz]);
	}

	/**
	 * Gets meldestatus for a prestudent in a semester.
	 * @param int prestudent_id
	 * @param string studiensemester_kurzbz
	 * @param int studstatuscode
	 * @param string statusDatum
	 * @param bool hasMobilitaet
	 * @return object success or error with meldestatus
	 */
	private function _getMeldeStatus($prestudent_id, $studiensemester_kurzbz, $studstatuscode, $statusDatum, $hasMobilitaet)
	{
		$unfinished_status_kurzbz = $this->_ci->config->item('fhc_dvuh_unfinished_student_status_kurzbz');
		$all_meldestatus = $this->_ci->config->item('fhc_dvuh_sync_student_meldestatus');

		$meldestatus = null;

		// status for currently studying, students with mobility have own status
		$meldestatus_active = $hasMobilitaet === true ? $all_meldestatus['zugelassen_ausland'] : $all_meldestatus['zugelassen_inland'];

		switch($studstatuscode)
		{
			case 1: // currently studying
				$meldestatus = $meldestatus_active;
				break;
			case 2: // "Unterbrecher" - study interruption
				$meldestatus = $all_meldestatus['unterbrochen'];
				break;
			case 3: // "Absolvent" - successfully finished studies
				$meldestatus = $meldestatus_active;
				break;
			case 4: // "Abbrecher" - study termination
				$terminationAfterStichtag = false;

				$bisMeldestichtagRes = $this->_ci->BismeldestichtagModel->getByStudiensemester($studiensemester_kurzbz);

				if (isError($bisMeldestichtagRes)) return $bisMeldestichtagRes;

				if (hasData($bisMeldestichtagRes))
				{
					$bisMeldestichtag = getData($bisMeldestichtagRes)[0]->meldestichtag;

					// termination after bismeldestichtag
					if (new DateTime($statusDatum) >= new DateTime($bisMeldestichtag))
					{
						// active status if after Stichtag
						$terminationAfterStichtag = true;
						$meldestatus = $meldestatus_active;
					}
				}

				if (!$terminationAfterStichtag)
				{
					// if terminated status, check if there is a "non-finishing" status in previous Semester...
					$previousStatusRes = $this->_ci->fhcmanagementlib->checkPreviousPrestudentStatusType(
						$prestudent_id,
						$studiensemester_kurzbz,
						$unfinished_status_kurzbz
					);

					if (isError($previousStatusRes))
						return $previousStatusRes;

					// ... if yes, set status zugelassen (active), otherwise storniert
					$meldestatus =
						hasData($previousStatusRes) && getData($previousStatusRes)[0] === true ? $meldestatus_active : $all_meldestatus['storniert'];
				}
				break;
			default:
				return error("Unbekannter Statuscode");
		}

		return success($meldestatus);
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
			$this->addWarning(
				'ZGV fehlt',
				'zgvFehlt',
				null,
				array('prestudent_id' => $prestudentstatus->prestudent_id)
			);
		}

		if (!isset($prestudentstatus->zgvdatum))
		{
			$this->addWarning(
				'ZGV Datum fehlt',
				'zgvDatumFehlt',
				null,
				array('prestudent_id' => $prestudentstatus->prestudent_id)
			);
		}
		else
		{
			if (!$this->_ci->dvuhcheckinglib->checkZgvDatumInTime($prestudentstatus->zgvdatum))
			{
				$this->addError(
					"ZGV Datum in Zukunft",
					'zgvDatumInZukunft',
					null,
					array('prestudent_id' => $prestudentstatus->prestudent_id)
				);
			}

			if ($prestudentstatus->zgvdatum < $gebdatum)
			{
				$this->addError(
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
				$this->addWarning(
					'ZGV Master fehlt',
					'zgvMasterFehlt',
					null,
					array('prestudent_id' => $prestudentstatus->prestudent_id)
				);
			}

			if (!isset($prestudentstatus->zgvmadatum))
			{
				$this->addWarning(
					'ZGV Masterdatum fehlt',
					'zgvMasterDatumFehlt',
					null,
					array('prestudent_id' => $prestudentstatus->prestudent_id)
				);
			}
			else
			{
				if (!$this->_ci->dvuhcheckinglib->checkZgvDatumInTime($prestudentstatus->zgvmadatum))
				{
					$this->addError(
						"ZGV Masterdatum in Zukunft",
						'zgvMasterDatumInZukunft',
						null,
						array('prestudent_id' => $prestudentstatus->prestudent_id)
					);
				}

				if ($prestudentstatus->zgvmadatum < $prestudentstatus->zgvdatum)
				{
					$this->addError(
						"ZGV Masterdatum vor Zgvdatum",
						'zgvMasterDatumVorZgvdatum',
						null,
						array('prestudent_id' => $prestudentstatus->prestudent_id)
					);
				}

				if ($prestudentstatus->zgvmadatum < $gebdatum)
				{
					$this->addError(
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
}
