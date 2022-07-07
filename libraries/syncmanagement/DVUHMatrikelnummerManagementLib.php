<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncmanagement/DVUHManagementLib.php';

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying data in DVUH, and updating data in FHC accordingly.
 */
class DVUHMatrikelnummerManagementLib extends DVUHManagementLib
{
	// Statuscodes returned when checking Matrikelnr, resulting actions are array keys
	private $_matrnr_statuscodes = array(
		'assignNew' => array('1', '5'),
		'saveExisting' => array('3'),
		'error' => array('2', '4', '6')
	);

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/FHCManagementLib');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/syncmanagement/DVUHMasterDataManagementLib');

		// load models
		$this->_ci->load->model('organisation/Studiensemester_model', 'StudiensemesterModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelpruefung_model', 'MatrikelpruefungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelreservierung_model', 'MatrikelreservierungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Stammdaten_model', 'StammdatenModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/Matrikelmeldung_model', 'MatrikelmeldungModel');
		$this->_ci->load->model('extensions/FHC-Core-DVUH/synctables/DVUHMatrikelnummerreservierung_model', 'DVUHMatrikelnummerreservierungModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Checks if student has a Matrikelnummer assigned in DVUH.
	 * If yes, existing Matrnr is saved in FHC.
	 * If no, new Matrnr is requested and saved with active=false in FHC.
	 * Sends Stammdatenmeldung (see sendAndUpdateMatrikelnummer).
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz executed for a certain semester
	 * @return object error or success with infos
	 */
	public function requestMatrikelnummer($person_id, $studiensemester_kurzbz)
	{
		$result = null;
		$infos = array();
		$warnings = array();

		// reset matrikelnr to NULL if it is an old, unused, non-active Matrikelnr so a new, current one can be assigned
		$resetMatrikelnummerRes = $this->_ci->fhcmanagementlib->resetInactiveMatrikelnummer($person_id, $studiensemester_kurzbz);

		if (isError($resetMatrikelnummerRes))
			return $resetMatrikelnummerRes;

		if (hasData($resetMatrikelnummerRes))
			$infos[] = "Alte, inaktive Matrikelnummer gelöscht für Person $person_id";

		// request Matrikelnr only for persons with prestudent in given Semester and no matrikelnr
		$personResult = $this->_dbModel->execReadOnlyQuery(
			"SELECT tbl_person.*
				FROM public.tbl_person
				JOIN public.tbl_prestudent USING (person_id)
				JOIN public.tbl_prestudentstatus USING (prestudent_id)
				WHERE person_id = ?
				AND studiensemester_kurzbz = ?
				AND tbl_person.matr_nr IS NULL
				LIMIT 1",
			array(
				$person_id, $studiensemester_kurzbz
			)
		);

		if (hasData($personResult))
		{
			$person = getData($personResult)[0];

			$matrPruefungResult = $this->_ci->MatrikelpruefungModel->get(
				!isEmptyString($person->bpk) ? $person->bpk : null,
				$person->ersatzkennzeichen,
				$person->gebdatum,
				null, // matrikelnummer
				$person->nachname,
				!isEmptyString($person->svnr) ? $person->svnr : null,
				$person->vorname
			);

			if (isError($matrPruefungResult))
			{
				return $matrPruefungResult;
			}
			elseif (hasData($matrPruefungResult))
			{
				$parsedObj = $this->_ci->xmlreaderlib->parseXmlDvuh(
					getData($matrPruefungResult),
					array('statuscode', 'statusmeldung', 'matrikelnummer')
				);

				if (isError($parsedObj))
					return $parsedObj;

				if (hasData($parsedObj))
				{
					$parsedObj = getData($parsedObj);
					$statuscode = count($parsedObj->statuscode) > 0 ? $parsedObj->statuscode[0] : '';
					$statusmeldung = count($parsedObj->statusmeldung) > 0 ? $parsedObj->statusmeldung[0] : '';
					$matrikelnummer = count($parsedObj->matrikelnummer) > 0 ? $parsedObj->matrikelnummer[0] : '';

					/**
					 *
					 * Code 1: Keine Studierendendaten gefunden, neue Matrikelnummer mit matrikelreservierung.xml aus eigenen Kontigent anfordern.
					 *
					 * Code 2: Matrikelnummer gesperrt keine Alternative, BRZ verständigen
					 *
					 * Code 3: Matrikelnummer im Status vergeben gefunden, Matrikelnummer übernehmen
					 *
					 * Code 4: Zur Matrikelnummer liegt eine aktive Meldung im aktuellen Semester vor, Matrikelnummer in Evidenz halten
					 *
					 * Code 5: Zur Matrikelnummer liegt ausschließlich eine Meldung in einen vergangenen Semester vor, es kam daher nie zur Zulassung.
					 * Eine neue Matrikelnummer aus dem eigenen Kontigent kann vergeben werden.
					 *
					 * Code 6: Mehr als eine Matrikelnummer wurde gefunden. Der Datenverbund kann keine eindeutige Matrikelnummer feststellen.
					 */

					if (in_array($statuscode, $this->_matrnr_statuscodes['assignNew'])) // no existing Matrikelnr - new one must be assigned
					{
						$this->_ci->StudiensemesterModel->addSelect('studienjahr_kurzbz');
						$studienjahrResult = $this->_ci->StudiensemesterModel->load($studiensemester_kurzbz);

						if (hasData($studienjahrResult))
						{
							$sj = getData($studienjahrResult)[0];
							$sj = substr($sj->studienjahr_kurzbz, 0, 4);
							$reservedMatrnrStr = null;

							// check if there is a free Matrikelnummer in FHC reserved
							$this->_ci->DVUHMatrikelnummerreservierungModel->addOrder('insertamum, matrikelnummer');
							$this->_ci->DVUHMatrikelnummerreservierungModel->addLimit(1);
							$fhcMatrikelnummerreservierung = $this->_ci->DVUHMatrikelnummerreservierungModel->load(array('jahr' => $sj));

							if (isError($fhcMatrikelnummerreservierung))
								return $fhcMatrikelnummerreservierung;

							if (hasData($fhcMatrikelnummerreservierung))
							{
								$reservedMatrnrStr = getData($fhcMatrikelnummerreservierung)[0]->matrikelnummer;
							}
							else
							{
								// reserve new Matrikelnummer in DVUH
								$anzahlReservierungen = 1;

								$matrReservResult = $this->_ci->MatrikelreservierungModel->post($this->_be, $sj, $anzahlReservierungen);

								if (hasData($matrReservResult))
								{
									$reservedMatrnr = $this->_ci->xmlreaderlib->parseXMLDvuh(getData($matrReservResult), array('matrikelnummer'));

									if (isError($reservedMatrnr))
										return $reservedMatrnr;

									if (hasData($reservedMatrnr))
									{
										$reservedMatrnr = getData($reservedMatrnr);
										$reservedMatrnrStr = $reservedMatrnr->matrikelnummer[0];

										// save matrnr in (intermediary) FHC table
										$fhcAddMatrikelnummerreservierung =
											$this->_ci->DVUHMatrikelnummerreservierungModel->addMatrikelnummerreservierung($reservedMatrnrStr, $sj);

										if (isError($fhcAddMatrikelnummerreservierung))
											return $fhcAddMatrikelnummerreservierung;
									}
									else
										return error("Es konnte keine Matrikelnummer reserviert werden");
								}
								else
									return error("Fehler beim Reservieren der Matrikelnummer");
							}

							if (isEmptyString($reservedMatrnrStr))
								return error("Keine Matrikelnummer zum Zuweisen gefunden");

							// send Matrikelnummer to DVUH and update in FHC person
							$sendUpdateMatrRes = $this->_sendAndUpdateMatrikelnummer(
								$person_id,
								$studiensemester_kurzbz,
								$reservedMatrnrStr,
								false, // Matrikelnr inactive
								$infos
							);

							if (isError($sendUpdateMatrRes))
								return $sendUpdateMatrRes;

							if (hasData($sendUpdateMatrRes))
							{
								// if successfully assigned Matrikelnummer, delete it in FHC Matrikelnummerreservierung table
								$fhcAddMatrikelnummerreservierung = $this->_ci->DVUHMatrikelnummerreservierungModel->delete(
									array(
										$reservedMatrnrStr,
										$sj
									)
								);

								if (isError($fhcAddMatrikelnummerreservierung))
									return $fhcAddMatrikelnummerreservierung;

								$updateMatrnrObj = getData($sendUpdateMatrRes);

								// merge infos from save matrnr result
								$infos = array_merge($updateMatrnrObj['infos'], $infos);
								$warnings = $updateMatrnrObj['warnings'];
							}
							else
								return error("Fehler bei Matrikelnummeraktualisierung");

							$result = $this->getResponseArr(null, $infos, $warnings);
						}
						else
						{
							return error("Studienjahr nicht gefunden");
						}
					}
					elseif (in_array($statuscode, $this->_matrnr_statuscodes['saveExisting'])) // Matrikelnr already existing in DVUH -> save in FHC
					{
						if (is_numeric($matrikelnummer))
						{
							$sendUpdateMatrRes = $this->_sendAndUpdateMatrikelnummer(
								$person_id,
								$studiensemester_kurzbz,
								$matrikelnummer,
								true, // Matrikelnr active
								$infos
							);

							if (isError($sendUpdateMatrRes))
								return $sendUpdateMatrRes;

							if (hasData($sendUpdateMatrRes))
							{
								$updateMatrnrObj = getData($sendUpdateMatrRes);

								// merge infos from save matrnr result
								$infos = array_merge($updateMatrnrObj['infos'], $infos);
								$warnings = $updateMatrnrObj['warnings'];
							}
							$result = $this->getResponseArr(null, $infos, $warnings);
						}
						else
							return error("ungültige Matrikelnummer");
					}
					elseif (in_array($statuscode, $this->_matrnr_statuscodes['error']))
					{
						return createExternalError($statusmeldung, 'MATRNR_STATUS_'.$statuscode);
					}
					else
					{
						if (!isEmptyString($statusmeldung))
						{
							$infos[] = $statusmeldung;
							$result = $this->getResponseArr(null, $infos);
						}
						else
							return error("Unbekannter Matrikelnr-Statuscode");
					}
				}
				else
					return error("Matrikelnummernanfrage konnte nicht geparst werden");
			}
			else
				return error("Matrikelnummernanfrage lieferte keine Daten");
		}
		else
		{
			$infos[] = "Keine valide Person für Matrikelnummernanfrage gefunden";
			$result = $this->getResponseArr(null, $infos);
		}

		return $result;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Saves masterdata with Matrikelnr in DVUH, sets Matrikelnr in FHC.
	 * @param int $person_id
	 * @param string $studiensemester_kurzbz semester for which stammdaten are sent
	 * @param string $matrikelnummer
	 * @param bool $matr_aktiv wether Matrnr is already active (or not yet valid)
	 * @param array $infos for storing info messages
	 * @return object
	 */
	private function _sendAndUpdateMatrikelnummer($person_id, $studiensemester_kurzbz, $matrikelnummer, $matr_aktiv, &$infos)
	{
		$sendMasterDataResult = $this->_ci->DVUHMasterDataManagementLib->sendMasterdata($person_id, $studiensemester_kurzbz, $matrikelnummer);

		if (isError($sendMasterDataResult))
			$result = $sendMasterDataResult;
		elseif (hasData($sendMasterDataResult))
		{
			$sendRes = getData($sendMasterDataResult);
			$updateMatrResult = $this->_ci->fhcmanagementlib->updateMatrikelnummer($person_id, $matrikelnummer, $matr_aktiv);
			$infos[] = "Stammdaten mit Matrikelnr $matrikelnummer erfolgreich für Person Id $person_id gesendet";

			if (!hasData($updateMatrResult))
				$result = error("Fehler beim Updaten der Matrikelnummer");
			else
			{
				$matrnr = getData($updateMatrResult)['matr_nr'];
				$matrnr_aktiv = getData($updateMatrResult)['matr_aktiv'];

				if ($matrnr_aktiv == true)
				{
					$infos[] = "Bestehende Matrikelnr $matrnr der Person Id $person_id zugewiesen";
				}
				elseif ($matrnr_aktiv == false)
				{
					$infos[] = "Neue Matrikelnr $matrnr erfolgreich der Person Id $person_id vorläufig zugewiesen";
				}

				$result = success($sendRes);
			}
		}
		else
			$result = error("Fehler beim Senden der Stammdaten");

		return $result;
	}
}
