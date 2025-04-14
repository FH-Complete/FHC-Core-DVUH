<?php

/**
 * Library for conversions/transformations of uhstat data.
 */
class UHSTATConversionLib
{
	private $_ci;

	CONST NATIONENGRUPPE_EU = 'eu';

	// mappings for UHSTAT Aufenthaltsart codes
	private $_aufenthaltsartMobilitaetsprogrammMapping = array(
		1 => array(7, 45, 46),
		3 => array(202)
	);
	// default Aufenhaltsart if no mapping assigned
	const DEFAULT_AUFENHALTSART = 2;

	// defines order of bisio zweck codes (0 is first position in sent string)
	private $_aufenthaltszweckBisiozweckMapping = array(
		0 => array(1, 3), // (Fach)Studium
		1 => array(4), // Diplom-/Masterarbeit bzw. Dissertation
		2 => array(5), // Sprachkurse
		3 => array(2, 3), // Praktikum/Praxis
		4 => array(6) // Lehrtätigkeit
	);

	// defines order of foerderung codes (0 is first position in sent string)
	private $_aufenthaltsfoerderungMapping = array(
		0 => 1, // EU-Förderung
		1 => 2, // Beihilfe von Bund, Land, Gemeinde
		2 => 3, // Förderung durch Universität/Hochschule
		3 => 4 // andere Förderung
	);

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/UHSTATSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Converts Mobilitäten of one prestudent to UHSTAT2 format.
	 * @param $mobilitaeten of one prestudent
	 * @return array
	 */
	public function convertToUHSTAT2($mobilitaeten)
	{
		$uhstat2Data = array();

		// set institution data from config
		$uhstat2Data['institutionTyp'] = $this->_ci->config->item('fhc_uhstat_institutionen_typ');
		$uhstat2Data['institution'] = $this->_ci->config->item('fhc_uhstat_institutionen_code');

		// fill Auslandsaufenthalte
		$uhstat2Data['auslandsaufenthalte'] = array();

		$counter = 0;
		$counterUnter2Wochen = 0;
		$counterUnter2WochenEu = 0;
		$counterUnter1Monat = 0;
		$counterUnter1MonatEu = 0;
		$counterAb1Monat = 0;

		foreach ($mobilitaeten as $mobilitaet)
		{
			if ($counter == 0)
			{
				// Abschlussdatum:
				// Extract year
				$uhstat2Data['studienendeJahr'] = date("Y", strtotime($mobilitaet->abschlussdatum));
				// Extract month
				$uhstat2Data['studienendeMonat'] = date("m", strtotime($mobilitaet->abschlussdatum));
			}

			$dauerMonate = $this->_monthsBetweenDates($mobilitaet->von, $mobilitaet->bis);

			$counter++;
			if ($mobilitaet->aufenthaltsdauer_tage < 14)
			{
				$counterUnter2Wochen++;
				if ($mobilitaet->nationengruppe_kurzbz == self::NATIONENGRUPPE_EU) $counterUnter2WochenEu++;
			}
			elseif ($dauerMonate < 1)
			{
				$counterUnter1Monat++;
				if ($mobilitaet->nationengruppe_kurzbz == self::NATIONENGRUPPE_EU) $counterUnter1MonatEu++;
			}
			elseif ($dauerMonate >= 1)
			{
				$counterAb1Monat++;
			}

			$aufenthalt = array();
			$aufenthalt['staat'] = $mobilitaet->nation_code;
			$aufenthalt['dauer'] = str_pad($dauerMonate, 2, '0', STR_PAD_LEFT);
			// TODO: round up or down? only whole ects are accepted
			$aufenthalt['ectsErw'] = str_pad(floor($mobilitaet->ects_erworben), 3, '0', STR_PAD_LEFT);
			$aufenthalt['ectsAnger'] = str_pad(floor($mobilitaet->ects_angerechnet), 3, '0', STR_PAD_LEFT);
			$aufenthalt['art'] = $this->_getAufenthaltsartFromMobilitaetsprogramm($mobilitaet->mobilitaetsprogramm_code);
			$aufenthalt['zweck'] = $this->_getAufenthaltszweckFromZweckCodes(explode(',', $mobilitaet->zwecke));
			$aufenthalt['foerd'] = $this->_getAuslandsaufenthaltFoerderungFromAufenthaltfoerderung(explode(',', $mobilitaet->foerderungen));

			$uhstat2Data['auslandsaufenthalte']['aufenthalt'.$counter] = $aufenthalt;
		}

		// set counter variables
		$uhstat2Data['auslandsaufenthalte']['unter2Wochen_anzahl'] = str_pad($counterUnter2Wochen, 2, '0', STR_PAD_LEFT);
		$uhstat2Data['auslandsaufenthalte']['unter2Wochen_mehrheitlInEu'] = $this->_getMehrheitlich($counterUnter2WochenEu, $counterUnter2Wochen);
		$uhstat2Data['auslandsaufenthalte']['unter1Monat_anzahl'] = str_pad($counterUnter1Monat, 2, '0', STR_PAD_LEFT);
		$uhstat2Data['auslandsaufenthalte']['unter1Monat_mehrheitlInEu'] = $this->_getMehrheitlich($counterUnter1MonatEu, $counterUnter1Monat);
		$uhstat2Data['auslandsaufenthalte']['ab1Monat_anzahl'] = str_pad($counterAb1Monat, 2, '0', STR_PAD_LEFT);

		return $uhstat2Data;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Get UHSTAT Aufenthaltsart code from Mobilitätsprogramm using mapping.
	 * @param int $mobilitaetsprogramm
	 * @return int
	 */
	private function _getAufenthaltsartFromMobilitaetsprogramm($mobilitaetsprogramm)
	{
		foreach ($this->_aufenthaltsartMobilitaetsprogrammMapping as $aufenthaltsart => $mobilitaetsprogramme)
		{
			if (in_array($mobilitaetsprogramm, $mobilitaetsprogramme)) return $aufenthaltsart;
		}
		return self::DEFAULT_AUFENHALTSART;
	}

	/**
	 * Get UHSTAT Aufenthaltszweck string from Zweck codes
	 * @param array $zweckCodes
	 * @return UHSTAT string code with ones and zeros
	 */
	private function _getAufenthaltszweckFromZweckCodes($zweckCodes)
	{
		$aufenhaltszweckArr = array();

		foreach ($this->_aufenthaltszweckBisiozweckMapping as $position => $bisio_zweck_arr)
		{
			// if there is a corresponding bisio zweck code, set "1" on correct position
			foreach ($bisio_zweck_arr as $bisio_zweck)
			{
				if (in_array($bisio_zweck, $zweckCodes)) $aufenhaltszweckArr[$position] = '1';
			}

			// no zweck code for this position
			if (!isset($aufenhaltszweckArr[$position])) $aufenhaltszweckArr[$position] = '0';

		}

		return implode('', $aufenhaltszweckArr);
	}

	/**
	 * Get UHSTAT AuslandsaufenthaltsFörderung string from Aufenthaltsfoerderungen
	 * @param array $aufenthaltsfoerderungen
	 * @return UHSTAT string code with ones and zeros
	 */
	private function _getAuslandsaufenthaltFoerderungFromAufenthaltfoerderung($aufenthaltsfoerderungen)
	{
		$foerderungen = array();

		foreach ($this->_aufenthaltsfoerderungMapping as $position => $foerderung)
		{
			// if there is a corresponding bisio zweck code, set "1" on correct position
			if (in_array($foerderung, $aufenthaltsfoerderungen)) $foerderungen[$position] = '1';

			// no zweck code for this position
			if (!isset($foerderungen[$position])) $foerderungen[$position] = '0';

		}

		return implode('', $foerderungen);
	}

	/**
	 * Get number of months between two dates
	 * @param $date1
	 * @param $date2
	 * @return int
	 */
	private function _monthsBetweenDates($date1, $date2)
	{
		$ts1 = strtotime($date1);
		$ts2 = strtotime($date2);

		$year1 = date('Y', $ts1);
		$year2 = date('Y', $ts2);

		$month1 = date('m', $ts1);
		$month2 = date('m', $ts2);

		return (($year2 - $year1) * 12) + ($month2 - $month1);
		//return (($year2 - $year1) * 12) + ($month2 - $month1) + 1;
	}

	/**
	 * Get majority code. 0 - not applicable, 1 - majority, 2 - no majority
	 * @param int $anzahl - number of cases
	 * @param int $maxAnzahl - number of all cases
	 * @return number indicating majority
	 */
	private function _getMehrheitlich($anzahl, $maxAnzahl)
	{
		if (is_integer($anzahl) && is_integer($maxAnzahl) && $maxAnzahl > 0)
		{
			if ($anzahl / $maxAnzahl >= 0.5) return '1';
			return '2';
		}
		return '0';
	}
}
