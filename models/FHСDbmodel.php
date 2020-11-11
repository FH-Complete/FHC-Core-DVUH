<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';

/**
 * interaction with FHC database
 */
class FHCDbModel extends DB_Model
{
	public function __construct()
	{
		parent::__construct();
		/*$this->load->model('person/person_model', 'PersonModel');*/
	}

	public function getStudiumData($person_id, $semester)
	{


		$params = array(
			"uuid" => getUUID(),
			"studierendenkey" => array(
				"matrikelnummer" => '52012345',
				"be" => 'FT',
				"semester" => '2020S'
			)
		);

		/*
		$lehrgang = array(
			'beendigungsdatum' => $beendigungsdatum,
			'lehrgangsnr' => $lehrgangsnr,
			'perskz' => $perskz,
			'studstatuscode' = $studstatuscode,
			'zugangsberechtigung' => array(
				'datum' => $zgv_datum,
				'staat' => $zgv_nation,
				'voraussetzung' => $zgv
			),
			'zugangsberechtigungMA' => array(
				'datum' => $zgvmadatum,
				'staat' => $zgvmanation,
				'voraussetzung' => $zgvma
			),
			'zulassungsdatum' => $zgvdatum
		);

		$params['lehrgang'] = $lehrgang;
		*/
		/*
		$studiengang = array(
			'disloziert' => '?', // Prüfen welchen wert das hat
			'ausbildungssemester' => $ausbildungssemester,
			'beendigungsdatum' => $beendigungsdatum,
			'berufstaetigkeitcode' => $berufstaetigkeitcode,
			'bmwfwfoerderrelevant' => $bmwfwfoerderrelevant,
			'gemeinsam' = array(
				'ausbildungssemester' => $gs_ausbildungssemester,
				'mobilitaetprogrammcode' => $gs_mobilitaetsprogrammcode,
				'partnercode' => $gs_partnercode,
				'programmnr' => $gs_programmnr,
				'studstatuscode' => $gs_studstatuscode,
				'studtyp' => $gs_studtyp
			),
			'mobilitaet' = array(
				'aufenthaltfoerderungcode' => $mo_aufenthaltsfoerderungcode,
				'bis' => $mo_bis,
				'ectsangerechnet' => $mo_ectsangerechnet,
				'ectserworben' => $mo_ectserworben,
				'programm' => $mo_programm,
				'staat' => $mo_staat,
				'von' => $mo_von,
				'zweck' => $mo_zweck
			),
			'orgformcode' => $orgform,
			'perskz' => $perskz,
			'standortcode' => $standortcode,
			'stgkz' => $stgkz,
			'studstatuscode' => $studstatuscode,
			'vornachperskz' => $vornachperskz,
			'zugangsberechtigung' => array(
				'datum' => $zgvdatum,
				'staat' => $zgvnation,
				'voraussetzung' => $zgv
			),
			'zugangsberechtigungMA' => array(
				'datum' => $zgvmadatum,
				'staat' => $zgvmanation,
				'voraussetzung' => $zgvma
			),
			'zulassungsdatum' => $zulassungsdatum
		)*/
		$studiengang = array(
			'disloziert' => 'N', // J,N,j,n
			'ausbildungssemester' => '1',
			//'beendigungsdatum' => '2019-01-01',
			'berufstaetigkeitcode' => '1',
			'bmwfwfoerderrelevant' => 'J',
			'gemeinsam' => array(
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
			),
			'orgformcode' => '1',
			'perskz' => '1920331002',
			'standortcode' => '022',
			'stgkz' => '0050331', // Laut Dokumentation 3stellige ErhKZ + 4stellige STGKz
			'studstatuscode' => '1',
			//'vornachperskz' => '1910331006',
			'zugangsberechtigung' => array(
				'datum' => '1983-01-02',
				'staat' => 'A',
				'voraussetzung' => '04' // Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden??
			),
			'zugangsberechtigungMA' => array(
				'datum' => '1994-12-23',
				'staat' => 'A',
				'voraussetzung' => '03'  // Laut Dokumentation 2 stellig muss daher mit 0 aufgefuellt werden??
			),
			'zulassungsdatum' => '2020-03-14'
		);
		$params['studiengang'] = $studiengang;

		$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/studium', $params, true);
		echo $postData;

		$result = $this->_call('POST', null, $postData);
		echo print_r($result, true);
		return $result;


	}
}
