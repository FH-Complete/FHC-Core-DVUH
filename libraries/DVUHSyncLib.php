<?php


class DVUHSyncLib
{
	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

	}

	public function getStudiumData($person_id, $semester)
	{
		$this->_ci->load->model('person/Person_model', 'PersonModel');

		$person = $this->_ci->PersonModel->load($person_id);

		if (hasData($person))
		{
			$persondata = getData($person);
			$persondata = $persondata[0];

			$semester = $this->_convertSemesterToFHC($semester);

			$semester_arr = array(1,2,3,4,5,6,7,8,50,60);

			$matrikelnummer = $persondata->matr_nr;
			$matrikelnummer = $persondata->matr_nr;



		}


		$params = array(
			"uuid" => getUUID(),
			"studierendenkey" => array(
				"matrikelnummer" => '00848224',
				"be" => 'FT',
				"semester" => '2020W'
			)
		);
		$studiengang = array(
			'disloziert' => 'N', // J,N,j,n
			'ausbildungssemester' => '1',
			//'beendigungsdatum' => '2019-01-01',
			'berufstaetigkeitcode' => '1',
			'bmwfwfoerderrelevant' => 'J',
			/*			'gemeinsam' => array(
							'ausbildungssemester' => '1',
							'mobilitaetprogrammcode' => '0',
							'partnercode' => '1',
							'programmnr' => '1',
							'studstatuscode' => '1',
							'studtyp' => 'I'
						),
						'mobilitaet' => array(
							'aufenthaltfoerderungcode' => '1',
							'bis' => '2020-06-01',
							'ectsangerechnet' => '25',
							'ectserworben' => '30',
							'programm' => '1',
							'staat' => 'GB',
							'von' => '2020-01-01',
							'zweck' => '1'
						),*/
			'orgformcode' => '1',
			'perskz' => '1810254019',
			'standortcode' => '022',
			'stgkz' => '0050331', // Laut Dokumentation 3stellige ErhKZ + 4stellige STGKz
			'studstatuscode' => '0',
			//'vornachperskz' => '1910331006',
			'zugangsberechtigung' => array(
				'datum' => '1983-01-01',
				'staat' => 'A',
				'voraussetzung' => '04' // Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden??
			),
			/*			'zugangsberechtigungMA' => array(
							'datum' => '1994-12-23',
							'staat' => 'A',
							'voraussetzung' => '03'  // Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden??
						),*/
			'zulassungsdatum' => '2020-03-14'
		);

	}

}