<?xml version="1.0" encoding="UTF-8"?>
<studienanfrage xmlns="http://www.brz.gv.at/datenverbund-unis">
	<uuid><?php echo $uuid; ?></uuid>
	<studierendenkey>
		<matrikelnummer><?php echo $studierendenkey['matrikelnummer']; ?></matrikelnummer>
		<be><?php echo $studierendenkey['be']; ?></be>
		<semester><?php echo $studierendenkey['semester']; ?></semester>
	</studierendenkey>
	<studien>
<?php
	// Lehrgang
	if (isset($lehrgaenge))
	{
		foreach ($lehrgaenge as $lehrgang):
			echo "\t\t<lehrgang>\n";

			if (isset($lehrgang['beendigungsdatum']))
				echo "\t\t\t<beendigungsdatum>".$lehrgang['beendigungsdatum']."</beendigungsdatum>\n";

			if (isset($lehrgang['mobilitaet']))
			{
				// if not array and has von index, only single mobilitaet, put it in array.
				if (isset($lehrgang['mobilitaet']['von']))
				{
					$lehrgang['mobilitaet'] = array($lehrgang['mobilitaet']);
				}

				foreach ($lehrgang['mobilitaet'] as $mobilitaet)
				{
					echo "\t\t\t\<mobilitaet>";
					if (isset($mobilitaet['aufenthaltfoerderungcode']))
					{
						// if only one aufenthaltfoerderungcode without array, put it in array
						if (!is_array($mobilitaet['aufenthaltfoerderungcode']))
							$mobilitaet['aufenthaltfoerderungcode'] = array($mobilitaet['aufenthaltfoerderungcode']);

						foreach ($mobilitaet['aufenthaltfoerderungcode'] as $foerderungscode)
						{
							echo "\t\t\t\t<aufenthaltfoerderungcode>". $foerderungscode . "</aufenthaltfoerderungcode>\n";
						}
					}

					if (isset($mobilitaet['bis']) && $mobilitaet['bis'] != '')
						echo "\t\t\t\t<bis>" . $mobilitaet['bis'] . "</bis>\n";

					if (isset($mobilitaet['ectsangerechnet']) && is_numeric($mobilitaet['ectsangerechnet']))
						echo "\t\t\t\t<ectsangerechnet>" . $mobilitaet['ectsangerechnet'] . "</ectsangerechnet>\n";

					if (isset($mobilitaet['ectserworben']) && is_numeric($mobilitaet['ectserworben']))
						echo "\t\t\t\t<ectserworben>" . $mobilitaet['ectserworben'] . "</ectserworben>\n";

					echo "\t\t\t\t<programm>" . $mobilitaet['programm'] . "</programm>\n";
					echo "\t\t\t\t<staat>" . $mobilitaet['staat'] . "</staat>\n";
					echo "\t\t\t\t<von>" . $mobilitaet['von'] . "</von>\n";

					if (isset($mobilitaet['herkunftslandcode']))
						echo "\t\t\t\t<herkunftslandcode>" . $mobilitaet['herkunftslandcode'] . "</herkunftslandcode>\n";

					if (isset($mobilitaet['id']))
						echo "\t\t\t\t<id>" . $mobilitaet['id'] . "</id>\n";

					// if only one zweck without array, put it in array
					if (!is_array($mobilitaet['zweck']) && is_numeric($mobilitaet['zweck']))
						$mobilitaet['zweck'] = array($mobilitaet['zweck']);

					foreach ($mobilitaet['zweck'] as $zweck)
					{
						echo "\t\t\t\t<zweck>" . $zweck . "</zweck>\n";
					}
					echo "\t\t\t</mobilitaet>\n";
				}
			}

			if (isset($lehrgang['gemeinsam']))
			{
				echo "\t\t\t<gemeinsam>\n".
						"\t\t\t\t<partnercode>".$lehrgang['gemeinsam']['partnercode']."</partnercode>\n".
						"\t\t\t\t<programmnr>".$lehrgang['gemeinsam']['programmnr']."</programmnr>\n".
						"\t\t\t\t<studstatuscode>".$lehrgang['gemeinsam']['studstatuscode']."</studstatuscode>\n".
						"\t\t\t\t<studtyp>".$lehrgang['gemeinsam']['studtyp']."</studtyp>\n";

				if (isset($lehrgang['gemeinsam']['beendigungsdatum']))
					echo "\t\t\t\t<beendigungsdatum>".$lehrgang['gemeinsam']['beendigungsdatum']."</beendigungsdatum>\n";

				if (isset($lehrgang['gemeinsam']['mobilitaetprogrammcode']))
					echo "\t\t\t\t<mobilitaetprogrammcode>".$lehrgang['gemeinsam']['mobilitaetprogrammcode']."</mobilitaetprogrammcode>\n";

				if (isset($lehrgang['gemeinsam']['studienkennunguni']))
					echo "\t\t\t\t<studienkennunguni>".$lehrgang['gemeinsam']['studienkennunguni']."</studienkennunguni>\n";

				echo "\t\t\t</gemeinsam>\n";
			}

			echo "\t\t\t<lehrgangsnr>".$lehrgang['lehrgangsnr']."</lehrgangsnr>\n";

			if (isset($lehrgang['orgformcode']))
				echo "\t\t\t<orgformcode>".$lehrgang['orgformcode']."</orgformcode>\n";

			echo "\t\t\t<perskz>".$lehrgang['perskz']."</perskz>\n";

			if (isset($lehrgang['meldestatus']) && $lehrgang['meldestatus']!='')
				echo "\t\t\t<meldestatus>".$lehrgang['meldestatus']."</meldestatus>\n";

			if (isset($lehrgang['standortcode']))
				echo "\t\t\t<standortcode>".$lehrgang['standortcode']."</standortcode>\n";

			if (isset($lehrgang['studstatuscode']))
				echo "\t\t\t<studstatuscode>".$lehrgang['studstatuscode']."</studstatuscode>\n";

			if (isset($lehrgang['zugangsberechtigung']))
			{
				echo "\t\t\t<zugangsberechtigung>\n";
				echo "\t\t\t\t<datum>".$lehrgang['zugangsberechtigung']['datum']."</datum>\n";

				if (isset($lehrgang['zugangsberechtigung']['staat']))
					echo "\t\t\t\t<staat>".$lehrgang['zugangsberechtigung']['staat']."</staat>\n";

				echo "\t\t\t\t<voraussetzung>".$lehrgang['zugangsberechtigung']['voraussetzung']."</voraussetzung>\n";
				echo "\t\t\t</zugangsberechtigung>\n";
			}

			if (isset($lehrgang['zugangsberechtigungMA']))
			{
				echo "\t\t\t<zugangsberechtigungMA>\n";
				echo "\t\t\t\t<datum>".$lehrgang['zugangsberechtigungMA']['datum']."</datum>\n";

				if (isset($lehrgang['zugangsberechtigungMA']['staat']))
					echo "\t\t\t\t<staat>".$lehrgang['zugangsberechtigungMA']['staat']."</staat>\n";

				echo "\t\t\t\t<voraussetzung>".$lehrgang['zugangsberechtigungMA']['voraussetzung']."</voraussetzung>\n";
				echo "\t\t\t</zugangsberechtigungMA>\n";
			}

			if (isset($lehrgang['zulassungsdatum']))
			{
				echo "\t\t\t<zulassungsdatum>".$lehrgang['zulassungsdatum']."</zulassungsdatum>\n";
			}

			echo "\t\t</lehrgang>\n";
		endforeach;
	}
	// Studiengang
	if (isset($studiengaenge))
	{
		foreach ($studiengaenge as $studiengang):

			$disloziert = isset($studiengang['disloziert']) ? " disloziert='".$studiengang['disloziert']."'" : "";

			echo "\t\t<studiengang$disloziert>\n";

			if (isset($studiengang['ausbildungssemester']))
				echo "\t\t\t<ausbildungssemester>" . $studiengang['ausbildungssemester'] . "</ausbildungssemester>\n";

			if (isset($studiengang['beendigungsdatum']) && $studiengang['beendigungsdatum']!='')
				echo "\t\t\t<beendigungsdatum>".$studiengang['beendigungsdatum']."</beendigungsdatum>\n";

			if (isset($studiengang['berufstaetigkeit_code']) && is_numeric($studiengang['berufstaetigkeit_code']))
				echo "\t\t\t<berufstaetigkeitcode>".$studiengang['berufstaetigkeit_code']."</berufstaetigkeitcode>\n";

			echo "\t\t\t<bmwfwfoerderrelevant>".$studiengang['bmwfwfoerderrelevant']."</bmwfwfoerderrelevant>\n";
			echo "\t\t\t<dualesstudium>".$studiengang['dualesstudium']."</dualesstudium>\n";

			if (isset($studiengang['gemeinsam']))
			{
				echo "\t\t\t<gemeinsam>\n".
						"\t\t\t\t<ausbildungssemester>".$studiengang['gemeinsam']['ausbildungssemester']."</ausbildungssemester>\n".
						"\t\t\t\t<partnercode>".$studiengang['gemeinsam']['partnercode']."</partnercode>\n".
						"\t\t\t\t<programmnr>".$studiengang['gemeinsam']['programmnr']."</programmnr>\n".
						"\t\t\t\t<studstatuscode>".$studiengang['gemeinsam']['studstatuscode']."</studstatuscode>\n".
						"\t\t\t\t<studtyp>".$studiengang['gemeinsam']['studtyp']."</studtyp>\n";

				if (isset($studiengang['gemeinsam']['beendigungsdatum']))
					echo "\t\t\t\t<beendigungsdatum>".$studiengang['gemeinsam']['beendigungsdatum']."</beendigungsdatum>\n";

				if (isset($studiengang['gemeinsam']['mobilitaetprogrammcode']))
					echo "\t\t\t\t<mobilitaetprogrammcode>".$studiengang['gemeinsam']['mobilitaetprogrammcode']."</mobilitaetprogrammcode>\n";

				if (isset($studiengang['gemeinsam']['studienkennunguni']))
					echo "\t\t\t\t<studienkennunguni>".$studiengang['gemeinsam']['studienkennunguni']."</studienkennunguni>\n";

				echo "\t\t\t</gemeinsam>\n";
			}

			if (isset($studiengang['mobilitaet']))
			{
				// if not array and has von index, only single mobilitaet, put it in array.
				if (isset($studiengang['mobilitaet']['von']))
				{
					$studiengang['mobilitaet'] = array($studiengang['mobilitaet']);
				}

				foreach ($studiengang['mobilitaet'] as $mobilitaet)
				{
					echo "\t\t\t\<mobilitaet>";
					if (isset($mobilitaet['aufenthaltfoerderungcode']))
					{
						// if only one aufenthaltfoerderungcode without array, put it in array
						if (!is_array($mobilitaet['aufenthaltfoerderungcode']))
							$mobilitaet['aufenthaltfoerderungcode'] = array($mobilitaet['aufenthaltfoerderungcode']);

						foreach ($mobilitaet['aufenthaltfoerderungcode'] as $foerderungscode)
						{
							echo "\t\t\t\t<aufenthaltfoerderungcode>". $foerderungscode . "</aufenthaltfoerderungcode>\n";
						}
					}

					if (isset($mobilitaet['bis']) && $mobilitaet['bis'] != '')
						echo "\t\t\t\t<bis>" . $mobilitaet['bis'] . "</bis>\n";

					if (isset($mobilitaet['ectsangerechnet']) && is_numeric($mobilitaet['ectsangerechnet']))
						echo "\t\t\t\t<ectsangerechnet>" . $mobilitaet['ectsangerechnet'] . "</ectsangerechnet>\n";

					if (isset($mobilitaet['ectserworben']) && is_numeric($mobilitaet['ectserworben']))
						echo "\t\t\t\t<ectserworben>" . $mobilitaet['ectserworben'] . "</ectserworben>\n";

					echo "\t\t\t\t<programm>" . $mobilitaet['programm'] . "</programm>\n";
					echo "\t\t\t\t<staat>" . $mobilitaet['staat'] . "</staat>\n";
					echo "\t\t\t\t<von>" . $mobilitaet['von'] . "</von>\n";

					if (isset($mobilitaet['herkunftslandcode']))
						echo "\t\t\t\t<herkunftslandcode>" . $mobilitaet['herkunftslandcode'] . "</herkunftslandcode>\n";

					if (isset($mobilitaet['id']))
						echo "\t\t\t\t<id>" . $mobilitaet['id'] . "</id>\n";

					// if only one zweck without array, put it in array
					if (!is_array($mobilitaet['zweck']) && is_numeric($mobilitaet['zweck']))
						$mobilitaet['zweck'] = array($mobilitaet['zweck']);

					foreach ($mobilitaet['zweck'] as $zweck)
					{
						echo "\t\t\t\t<zweck>" . $zweck . "</zweck>\n";
					}
					echo "\t\t\t</mobilitaet>\n";
				}
			}

			if (isset($studiengang['meldestatus']) && $studiengang['meldestatus']!='')
				echo "\t\t\t<meldestatus>".$studiengang['meldestatus']."</meldestatus>\n";

			if (isset($studiengang['orgformcode']))
				echo "\t\t\t<orgformcode>".$studiengang['orgformcode']."</orgformcode>\n";

			echo "\t\t\t<perskz>".$studiengang['perskz']."</perskz>\n";

			if (isset($studiengang['standortcode']))
				echo "\t\t\t<standortcode>".$studiengang['standortcode']."</standortcode>\n";

			echo "\t\t\t<stgkz>".$studiengang['stgkz']."</stgkz>\n";

			if (isset($studiengang['studstatuscode']))
				echo "\t\t\t<studstatuscode>".$studiengang['studstatuscode']."</studstatuscode>\n";

			if (isset($studiengang['unterbrechungsdatum']))
				echo "\t\t\t<unterbrechungsdatum>".$studiengang['unterbrechungsdatum']."</unterbrechungsdatum>\n";

			if (isset($studiengang['vonnachperskz']))
				echo "\t\t\t<vonnachperskz>".$studiengang['vonnachperskz']."</vonnachperskz>\n";

			if (isset($studiengang['wiedereintrittsdatum']))
				echo "\t\t\t<wiedereintrittsdatum>".$studiengang['wiedereintrittsdatum']."</wiedereintrittsdatum>\n";

			if (isset($studiengang['zugangsberechtigung']))
			{
				echo "\t\t\t<zugangsberechtigung>\n";
				echo "\t\t\t\t<datum>".$studiengang['zugangsberechtigung']['datum']."</datum>\n";

				if (isset($studiengang['zugangsberechtigung']['staat']))
					echo "\t\t\t\t<staat>".$studiengang['zugangsberechtigung']['staat']."</staat>\n";

				echo "\t\t\t\t<voraussetzung>".$studiengang['zugangsberechtigung']['voraussetzung']."</voraussetzung>\n";
				echo "\t\t\t</zugangsberechtigung>\n";
			}

			if (isset($studiengang['zugangsberechtigungMA']))
			{
				echo "\t\t\t<zugangsberechtigungMA>\n";
				echo "\t\t\t\t<datum>".$studiengang['zugangsberechtigungMA']['datum']."</datum>\n";

				if (isset($studiengang['zugangsberechtigungMA']['staat']))
					echo "\t\t\t\t<staat>".$studiengang['zugangsberechtigungMA']['staat']."</staat>\n";

				echo "\t\t\t\t<voraussetzung>".$studiengang['zugangsberechtigungMA']['voraussetzung']."</voraussetzung>\n";
				echo "\t\t\t</zugangsberechtigungMA>\n";
			}

			if (isset($studiengang['zulassungsdatum']))
				echo "\t\t\t<zulassungsdatum>".$studiengang['zulassungsdatum']."</zulassungsdatum>\n";

			echo "\t\t</studiengang>\n";
		endforeach;
	}
?>
	</studien>
</studienanfrage>
