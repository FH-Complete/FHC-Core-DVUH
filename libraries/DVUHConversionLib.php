<?php

/**
 * Library for conversions/transformations of dvuh and fhcomplete data.
 */
class DVUHConversionLib
{
	private $_ci;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');

		// load models
		$this->_ci->load->model('organisation/Studiengang_model', 'StudiengangModel');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Converts semester in DVUH format to FHC format
	 * @param string $semester
	 * @return string semester in FHC format
	 */
	public function convertSemesterToFHC($semester)
	{
		if (!preg_match("/^\d{4}(S|W)$/", $semester))
			return $semester;

		return mb_substr($semester, -1).'S'.mb_substr($semester, 0, 4);
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

		return mb_substr($semester, 2, strlen($semester) - 2).mb_substr($semester, 0, 1);
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
		if (!is_numeric($erhalter_kz))
			return $erhalter_kz;

		return str_pad($erhalter_kz, DVUHCheckingLib::DVUH_ERHALTER_LENGTH, '0', STR_PAD_LEFT);
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

		if (!is_numeric($ausserordentlich_prefix))
			return $studiengang_kz;

		return $ausserordentlich_prefix.$erhalter_kz;
	}

	/**
	 * Gets Studiengangskennzahl for reporting to DVUH.
	 * @param int $studiengang_kz the inofficial identifier
	 * @param int $erhalter_kz number of Erhalter institution
	 * @param bool $isAusserordentlich wether student to report is ausserordentlich
	 * @return object
	 */
	public function getMeldeStudiengangKz($studiengang_kz, $erhalter_kz, $isAusserordentlich = false)
	{
		$dvuh_erhalter_kz = $this->convertErhalterkennzahlToDVUH($erhalter_kz);

		// if ausserordentlich, special studiengang kz
		if ($isAusserordentlich === true)
		{
			return success(
				$dvuh_erhalter_kz
				.$this->convertStudiengangskennzahlToDVUHAusserordentlich($studiengang_kz, $dvuh_erhalter_kz)
			);
		}

		// load stg to get melde_studiengang_kz
		$this->_ci->StudiengangModel->addSelect('melde_studiengang_kz, lgartcode');
		$studiengangRes = $this->_ci->StudiengangModel->load($studiengang_kz);

		if (hasData($studiengangRes))
		{
			$studiengangData = getData($studiengangRes)[0];
			$melde_studiengang_kz = $studiengangData->melde_studiengang_kz;

			// if lgartcode exists, studiengang is lehrgang - add erhalter_kz
			if (!is_numeric($studiengangData->lgartcode))
			{
				$dvuh_erhalter_kz = $this->convertErhalterkennzahlToDVUH($erhalter_kz);
				$melde_studiengang_kz = $dvuh_erhalter_kz.$melde_studiengang_kz;
			}

			// check studiengang kz for validity
			if (!$this->_ci->dvuhcheckinglib->checkStudiengangkz($melde_studiengang_kz))
			{
				return createError( // TODO phrase?
					"Ungültige Meldestudiengangskennzahl für Studiengang $studiengang_kz,"
					." gültiges Format: (3 Stellen für Erhalter wenn Lehrgang) [4 Stellen Studiengang]",
					'ungueltigeMeldeStudiengangskennzahl',
					array($studiengang_kz),
					array('studiengang_kz' => $studiengang_kz)
				);
			}

			// return converted melde studiengang kz
			return success($melde_studiengang_kz);
		}
		else
			return error("Keinen Studiengang gefunden"); // TODO phrases
	}
}
