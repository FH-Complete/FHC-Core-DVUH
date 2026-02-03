<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/issues/plausichecks/PlausiChecker.php';

/**
 * Check for students which should be reported, but weren't (usually because of a missing payment)
 */
class NichtGemeldeteStudierende extends PlausiChecker
{
	const DAYS_UNTIL_STICHTAG_THRESHOLD = 42;

	public function executePlausiCheck($params)
	{
		$results = array();

		// get parameters from config
		$exkludierte_studiengang_kz = isset($this->_config['exkludierteStudiengaenge']) ? $this->_config['exkludierteStudiengaenge'] : null;

		// get next Meldestichtag
		$this->_ci->load->model('codex/Bismeldestichtag_model', 'BismeldestichtagModel');

		$result = $this->_ci->BismeldestichtagModel->getNextMeldestichtag();

		if (isError($result)) return $result;

		// no stichtag defined - do not execute check
		if (!hasData($result)) return success([]);

		$bismeldestichtag = getData($result)[0];

		$studiensemester_kurzbz = $bismeldestichtag->studiensemester_kurzbz;
		$meldestichtag = $bismeldestichtag->meldestichtag;

		$meldestichtagDate = new DateTime($meldestichtag);
		$now = new DateTime();

		$daysUntilStichtag = $meldestichtagDate->diff($now)->days;

		// execute check only if Stichtag is in near future
		if ($daysUntilStichtag > self::DAYS_UNTIL_STICHTAG_THRESHOLD) return success([]);

		// get all students failing the plausicheck
		$prestudentRes = $this->getNichtGemeldeteStudierende($studiensemester_kurzbz, null, $exkludierte_studiengang_kz);

		if (isError($prestudentRes)) return $prestudentRes;

		if (hasData($prestudentRes))
		{
			$prestudents = getData($prestudentRes);

			// populate results with data necessary for writing issues
			foreach ($prestudents as $prestudent)
			{
				$results[] = array(
					'person_id' => $prestudent->person_id,
					'oe_kurzbz' => $prestudent->prestudent_stg_oe_kurzbz,
					'fehlertext_params' =>
						array('prestudent_id' => $prestudent->prestudent_id, 'studiensemester_kurzbz' => $prestudent->studiensemester_kurzbz),
					'resolution_params' =>
						array('prestudent_id' => $prestudent->prestudent_id, 'studiensemester_kurzbz' => $prestudent->studiensemester_kurzbz)
				);
			}
		}

		// return the results
		return success($results);
	}

	/**
	 * Students, which have not been reported, but should have been.
	 * @param studiensemester_kurzbz string semester for which students should have been reported
	 * @param prestudent_id int if check is to be executed only for one prestudent
	 * @param exkludierte_studiengang_kz array if certain Studiengänge have to be excluded from check
	 * @return success with prestudents or error
	 */
	function getNichtGemeldeteStudierende($studiensemester_kurzbz, $prestudent_id = null, $exkludierte_studiengang_kz = null, $issue_id = null)
	{
		$params = array($studiensemester_kurzbz);

		//$this->_ci =& get_instance(); // get code igniter instance

		// get prestudent status for send study data job from config
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$status_kurzbz = $this->_ci->config->item('fhc_dvuh_status_kurzbz')['DVUHSendStudyData'] ?? null;

		$qry = "
			SELECT
				DISTINCT ON (prestudent_id)
				prestudent.person_id,
				prestudent_id,
				status.studiensemester_kurzbz,
				stg.oe_kurzbz AS prestudent_stg_oe_kurzbz
			FROM
				public.tbl_prestudent prestudent
				JOIN public.tbl_studiengang stg ON prestudent.studiengang_kz = stg.studiengang_kz
				JOIN public.tbl_prestudentstatus status USING (prestudent_id)
				JOIN bis.tbl_bismeldestichtag stichtag USING (studiensemester_kurzbz)
			WHERE
				bismelden
				AND stg.melderelevant
				AND status.studiensemester_kurzbz = ?
				AND NOT EXISTS (-- Studiumdaten not sent yet
					SELECT 1
					FROM
						sync.tbl_dvuh_studiumdaten
					WHERE
						prestudent_id = prestudent.prestudent_id
						AND studiensemester_kurzbz = status.studiensemester_kurzbz
						AND storniert = FALSE
				)
				AND NOT EXISTS ( -- No issue, preventing study data from being sent, occured
					SELECT 1
					FROM
						system.tbl_fehler
					JOIN
						system.tbl_issue USING (fehlercode)
					WHERE
						fehlertyp_kurzbz = 'error'
						AND verarbeitetamum IS NULL
						AND app IN ('core', 'dvuh')
						AND person_id = prestudent.person_id";

		// exclude the issue checked for resolution
		if (isset($issue_id))
		{
			$qry .= " AND issue_id <> ?";
			$params[] = $issue_id;
		}

		$qry .= ")";

		if (isset($status_kurzbz) && !isEmptyArray($status_kurzbz))
		{
			$qry .= " AND status.status_kurzbz IN ?";
			$params[] = $status_kurzbz;
		}

		if (isset($prestudent_id))
		{
			$qry .= " AND prestudent.prestudent_id = ?";
			$params[] = $prestudent_id;
		}

		if (isset($exkludierte_studiengang_kz) && !isEmptyArray($exkludierte_studiengang_kz))
		{
			$qry .= " AND stg.studiengang_kz NOT IN ?";
			$params[] = $exkludierte_studiengang_kz;
		}

		$qry .= ' ORDER BY
					prestudent_id, studiensemester_kurzbz, status.datum DESC, status.insertamum DESC, status.ext_id DESC';

		return $this->_db->execReadOnlyQuery($qry, $params);
	}
}
