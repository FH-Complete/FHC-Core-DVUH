<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

class Plausichecks extends Auth_Controller
{
	private $_fehlerKurzbzToExclude = array(
		'andereBeBezahltSapGesendet',
		'dvuhFehler'
	);

	public function __construct()
	{
		parent::__construct(
			array(
				'index' => array('system/issues_verwalten:r'),
				'runChecks' => array('system/issues_verwalten:r')
			)
		);

		// Load libraries
		$this->load->library('WidgetLib');
		$this->load->library('extensions/FHC-Core-DVUH/DVUHIssueLib');
		$this->load->library('extensions/FHC-Core-DVUH/JQMSchedulerLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHStammdatenLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHPaymentLib');
		$this->load->library('extensions/FHC-Core-DVUH/syncdata/DVUHStudyDataLib');

		// Load models
		$this->load->model('system/Fehler_model', 'FehlerModel');
		$this->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->load->model('organisation/Studiengang_model', 'StudiengangModel');

		// load configs
		$this->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	/*
	 * Get data for filtering the plausichecks and load the view.
	 */
	public function index()
	{
		$filterData = $this->_getFilterData();
		$this->load->view('extensions/FHC-Core-DVUH/plausichecks', $filterData);
	}

	/**
	 * Initiate plausichecks run.
	 */
	public function runChecks()
	{
		$studiensemester_kurzbz = $this->input->get('studiensemester_kurzbz');
		$studiengang_kz = $this->input->get('studiengang_kz');
		$fehler_kurzbz = $this->input->get('fehler_kurzbz');

		// all fehler kurzbz which are going to be checked
		$fehlerKurzbz = array();

		// if no particular fehler_kurzbz given...
		if (isEmptyString($fehler_kurzbz))
		{
			// ...get all dvuh fehler
			$fehlerKurzbz = $this->_getDVUHFehler();
		}
		else
		{
			// ...otherwise use only given fehler_kurzbz
			$fehlerKurzbz[] = $fehler_kurzbz;
		}

		// set Studiengang to null if not passed
		if (isEmptyString($studiengang_kz)) $studiengang_kz = null;

		$status_kurzbz = $this->config->item('fhc_dvuh_status_kurzbz');
		$prestudentRes = $this->fhcmanagementlib->getReportablePrestudents(
			$studiensemester_kurzbz,
			$studiengang_kz,
			$status_kurzbz[JQMSchedulerLib::JOB_TYPE_SEND_CHARGE]
		);

		$allWarnings = array();
		$allErrors = array();
		if (hasData($prestudentRes))
		{
			$prestudents = getData($prestudentRes);
			foreach ($prestudents as $prestudent)
			{
				$studentinfoRes = $this->dvuhstammdatenlib->getStammdatenData($prestudent->person_id, $studiensemester_kurzbz);
				$vorschreibungRes = $this->dvuhstammdatenlib->getVorschreibungData($prestudent->person_id, $studiensemester_kurzbz);

				// get and reset warnings produced by Stammdatenlib
				$warnings = $this->dvuhstammdatenlib->readWarnings();

				foreach ($warnings as $stWarning)
				{
					$stWarning->person_id = $prestudent->person_id;
					$stWarning->oe_kurzbz = $prestudent->oe_kurzbz;
					$stWarning->fehler_kurzbz = $prestudent->oe_kurzbz;
					$stWarning->type = 'warning';
					$allWarnings[] = $stWarning;
				}

				$stammdatenErrors = array();

				// merge errors from stammdaten and vorschreibung data
				if (isError($studentinfoRes))
				{
					$studentinfoErr = getError($studentinfoRes);
					if (is_array($studentinfoErr)) $stammdatenErrors = $studentinfoErr;
				}

				if (isError($vorschreibungRes))
				{
					$vorschreibungErr = getError($vorschreibungRes);
					if (is_array($vorschreibungErr)) $stammdatenErrors = array_merge($stammdatenErrors, $vorschreibungErr);
				}

				// add stammdaten Errors
				foreach ($stammdatenErrors as $err)
				{
					$err->person_id = $prestudent->person_id;
					$err->oe_kurzbz = $prestudent->oe_kurzbz;
					$err->type = 'error';
					$allErrors[] = $err;
				}

				$studentinfoRes = $this->dvuhpaymentlib->getPaymentData($prestudent->person_id, $studiensemester_kurzbz);

				// get and reset warnings produced by Paymentdatalib
				$warnings = $this->dvuhpaymentlib->readWarnings();

				foreach ($warnings as $stWarning)
				{
					$stWarning->person_id = $prestudent->person_id;
					$stWarning->oe_kurzbz = $prestudent->oe_kurzbz;
					$stWarning->fehler_kurzbz = $prestudent->oe_kurzbz;
					$stWarning->type = 'warning';
					$allWarnings[] = $stWarning;
				}

				// add payment errors
				if (isError($studentinfoRes))
				{
					$paymentErrors = getError($studentinfoRes);

					if (is_array($paymentErrors))
					{
						foreach ($paymentErrors as $err)
						{
							$err->person_id = $prestudent->person_id;
							$err->oe_kurzbz = $prestudent->oe_kurzbz;
							$err->type = 'error';
							$allErrors[] = $err;
						}
					}
				}

				$studentinfoRes = $this->dvuhstudydatalib->getStudyData($prestudent->person_id, $studiensemester_kurzbz, $prestudent->prestudent_id);

				// get and reset warnings produced by Studydatalib
				$warnings = $this->dvuhstudydatalib->readWarnings();

				foreach ($warnings as $stWarning)
				{
					$stWarning->person_id = $prestudent->person_id;
					$stWarning->oe_kurzbz = $prestudent->oe_kurzbz;
					$stWarning->fehler_kurzbz = $prestudent->oe_kurzbz;
					$stWarning->type = 'warning';
					$allWarnings[] = $stWarning;
				}

				// add study data errors
				if (isError($studentinfoRes))
				{
					$studyDataErrors = getError($studentinfoRes);

					if (is_array($studyDataErrors))
					{
						foreach ($studyDataErrors as $err)
						{
							$err->person_id = $prestudent->person_id;
							$err->oe_kurzbz = $prestudent->oe_kurzbz;
							$err->type = 'error';
							$allErrors[] = $err;
						}
					}
				}
			}
		}

		$allIssues = array_merge($allErrors, $allWarnings);
		// issues array for passing issue texts
		$issueTexts = array_fill_keys($fehlerKurzbz, array());

		// display all the issues
		foreach ($allIssues as $issue)
		{
			$fehler_kurzbz = $issue->issue_fehler_kurzbz;
			$type = $issue->type;

			// skip excluded fehler_kurzbz
			if (in_array($fehler_kurzbz, $this->_fehlerKurzbzToExclude) || !isset($issueTexts[$fehler_kurzbz])) continue;

			// optionally replace fehler parameters in text, output the fehlertext
			if (isset($issue->issue_fehlertext))
			{
				$fehlerText = $issue->issue_fehlertext;

				if (isset($issue->person_id)) $fehlerText .= "; person_id: ".$issue->person_id;
				if (isset($issue->oe_kurzbz)) $fehlerText .= "; oe_kurzbz: ".$issue->oe_kurzbz;

				$issueObj = new StdClass();
				$issueObj->fehlertext = $fehlerText;
				$issueObj->type = $type;
				$issueTexts[$fehler_kurzbz][] = $issueObj;
			}
		}

		$this->outputJsonSuccess($issueTexts);
	}

	/**
	 * Get the data needed for filtering for limiting checks.
	 */
	private function _getFilterData()
	{
		$this->StudiensemesterModel->addOrder('start', 'DESC');
		$studiensemesterRes = $this->StudiensemesterModel->load();

		if (isError($studiensemesterRes)) show_error(getError($studiensemesterRes));

		$currSemRes = $this->StudiensemesterModel->getAkt();

		if (isError($currSemRes)) show_error(getError($currSemRes));

		$this->StudiengangModel->addSelect('studiengang_kz, tbl_studiengang.bezeichnung, tbl_studiengang.typ,
			tbl_studiengangstyp.bezeichnung AS typbezeichnung, UPPER(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as kuerzel');
		$this->StudiengangModel->addJoin('public.tbl_studiengangstyp', 'typ');
		$this->StudiengangModel->addOrder('kuerzel, tbl_studiengang.bezeichnung, studiengang_kz');
		$studiengaengeRes = $this->StudiengangModel->loadWhere(array('aktiv' => true));

		if (isError($studiengaengeRes)) show_error(getError($studiengaengeRes));

		$fehlerKurzbz = $this->_getDVUHFehler();

		return array(
			'semester' => hasData($studiensemesterRes) ? getData($studiensemesterRes) : array(),
			'currsemester' => hasData($currSemRes) ? getData($currSemRes) : array(),
			'studiengaenge' => hasData($studiengaengeRes) ? getData($studiengaengeRes) : array(),
			'fehler' => $fehlerKurzbz
		);
	}

	/**
	 * Get all self-defined dvuh fehler.
	 * @return array with fehler_kurzbz
	 */
	private function _getDVUHFehler()
	{
		$fehlerKurzbz = array();
		$this->FehlerModel->addSelect('fehler_kurzbz');
		$this->FehlerModel->addOrder("CASE WHEN fehlertyp_kurzbz = 'error' THEN 0 ELSE 1 END, fehlertyp_kurzbz, fehler_kurzbz");
		$fehlerRes = $this->FehlerModel->loadWhere(
			"app = '".DVUHIssueLib::APP
			."' AND fehler_kurzbz IS NOT NULL AND fehler_kurzbz NOT IN ('".implode("', '", $this->_fehlerKurzbzToExclude)."')"
		);

		if (hasData($fehlerRes))
		{
			$fehlerKurzbzData = getData($fehlerRes);

			foreach ($fehlerKurzbzData as $fk)
			{
				$fehlerKurzbz[] = $fk->fehler_kurzbz;
			}
		}

		return $fehlerKurzbz;
	}
}
