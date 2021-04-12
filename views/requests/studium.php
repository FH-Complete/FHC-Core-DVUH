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
	if(isset($lehrgaenge))
	{
		foreach ($lehrgaenge as $lehrgang):
			echo '
				<lehrgang>';
			if(isset($lehrgang['beendigungsdatum']))
				echo '<beendigungsdatum>'.$lehrgang['beendigungsdatum'].'</beendigungsdatum>';
			echo '
					<lehrgangsnr>'.$lehrgang['lehrgangsnr'].'</lehrgangsnr>
					<perskz>'.$lehrgang['perskz'].'</perskz>
					<standortcode>'.$lehrgang['standortcode'].'</standortcode>';

			if (isset($lehrgang['studstatuscode']))
				echo '<studstatuscode>'.$lehrgang['studstatuscode'].'</studstatuscode>';

			if(isset($lehrgang['zugangsberechtigung']))
			{
				echo '
					<zugangsberechtigung>
						<datum>'.$lehrgang['zugangsberechtigung']['datum'].'</datum>
						<staat>'.$lehrgang['zugangsberechtigung']['staat'].'</staat>
						<voraussetzung>'.$lehrgang['zugangsberechtigung']['voraussetzung'].'</voraussetzung>
					</zugangsberechtigung>';
			}
			if(isset($lehrgang['zugangsberechtigungMA']))
			{
				echo '
					<zugangsberechtigungMA>
						<datum>'.$lehrgang['zugangsberechtigungMA']['datum'].'</datum>
						<staat>'.$lehrgang['zugangsberechtigungMA']['staat'].'</staat>
						<voraussetzung>'.$lehrgang['zugangsberechtigungMA']['voraussetzung'].'</voraussetzung>
					</zugangsberechtigungMA>';
			}

			if(isset($lehrgang['zulassungsdatum']))
			{
				echo '<zulassungsdatum>'.$lehrgang['zulassungsdatum'].'</zulassungsdatum>';
			}

			echo '</lehrgang>';
		endforeach;
	}
	// Studiengang
	if(isset($studiengaenge))
	{
		foreach ($studiengaenge as $studiengang):
		echo '
		<studiengang disloziert="'.$studiengang['disloziert'].'">';

		if (isset($studiengang['ausbildungssemester']))
		{
			echo '\t\t\t<ausbildungssemester>' . $studiengang['ausbildungssemester'] . '</ausbildungssemester>\n';
		}

		if(isset($studiengang['beendigungsdatum']) && $studiengang['beendigungsdatum']!='')
		{
			echo "\t\t\t<beendigungsdatum>".$studiengang['beendigungsdatum']."</beendigungsdatum>\n";
		}

		if(isset($studiengang['berufstaetigkeit_code']) && $studiengang['berufstaetigkeit_code']!='')
		{
			echo "\t\t\t<berufstaetigkeitcode>".$studiengang['berufstaetigkeit_code']."</berufstaetigkeitcode>\n";
		}

		echo "\t\t\t<bmwfwfoerderrelevant>".$studiengang['bmwfwfoerderrelevant']."</bmwfwfoerderrelevant>\n";

		if(isset($studiengang['gemeinsam']))
		{
			echo '
			<gemeinsam>
				<ausbildungssemester>'.$studiengang['gemeinsam']['ausbildungssemester'].'</ausbildungssemester>
				<mobilitaetprogrammcode>'.$studiengang['gemeinsam']['mobilitaetprogrammcode'].'</mobilitaetprogrammcode>
				<partnercode>'.$studiengang['gemeinsam']['partnercode'].'</partnercode>
				<programmnr>'.$studiengang['gemeinsam']['programmnr'].'</programmnr>
				<studstatuscode>'.$studiengang['gemeinsam']['studstatuscode'].'</studstatuscode>
				<studtyp>'.$studiengang['gemeinsam']['studtyp'].'</studtyp>
			</gemeinsam>
			';
		}

		if(isset($studiengang['mobilitaet']))
		{
			foreach ($studiengang['mobilitaet'] as $mobilitaet)
			{
				echo '
			<mobilitaet>';
				if (isset($mobilitaet['aufenthaltfoerderungcode']))
				{
					foreach ($mobilitaet['aufenthaltfoerderungcode'] as $foerderungscode)
					{
						echo '<aufenthaltfoerderungcode>' . $foerderungscode . "</aufenthaltfoerderungcode>\n";
					}
				}

				if (isset($mobilitaet['bis']) && $mobilitaet['bis'] != '')
					echo "\t\t\t\t<bis>" . $mobilitaet['bis'] . "</bis>\n";

				if (isset($mobilitaet['ectsangerechnet']) && $mobilitaet['ectsangerechnet'] != '')
					echo "\t\t\t\t<ectsangerechnet>" . $mobilitaet['ectsangerechnet'] . "</ectsangerechnet>\n";

				if (isset($mobilitaet['ectserworben']) && $mobilitaet['ectserworben'] != '')
					echo "\t\t\t\t<ectserworben>" . $mobilitaet['ectserworben'] . "</ectserworben>\n";

				echo "\t\t\t\t<programm>" . $mobilitaet['programm'] . "</programm>\n";
				echo "\t\t\t\t<staat>" . $mobilitaet['staat'] . "</staat>\n";
				echo "\t\t\t\t<von>" . $mobilitaet['von'] . "</von>\n";

				foreach ($mobilitaet['zweck'] as $zweck)
				{
					echo "\t\t\t\t<zweck>" . $zweck . "</zweck>\n";
				}
				echo "\t\t\t</mobilitaet>\n";
			}
		}
		echo '
			<orgformcode>'.$studiengang['orgformcode'].'</orgformcode>
			<perskz>'.$studiengang['perskz'].'</perskz>';

		if (isset($studiengang['standortcode']))
			echo '<standortcode>'.$studiengang['standortcode'].'</standortcode>';

		echo'
			<stgkz>'.$studiengang['stgkz'].'</stgkz>';

		if (isset($studiengang['studstatuscode']))
			echo '<studstatuscode>'.$studiengang['studstatuscode'].'</studstatuscode>';

		if(isset($studiengang['vornachperskz']))
		{
			echo '
			<vornachperskz>'.$studiengang['vornachperskz'].'</vornachperskz>';
		}

		if(isset($studiengang['zugangsberechtigung']))
		{
			echo '
			<zugangsberechtigung>';

			if (isset($studiengang['zugangsberechtigung']['datum']))
				echo '<datum>'.$studiengang['zugangsberechtigung']['datum'].'</datum>';

			if (isset($studiengang['zugangsberechtigung']['staat']))
				echo '<staat>'.$studiengang['zugangsberechtigung']['staat'].'</staat>';

			echo '
				<voraussetzung>'.$studiengang['zugangsberechtigung']['voraussetzung'].'</voraussetzung>
			</zugangsberechtigung>';
		}

		if(isset($studiengang['zugangsberechtigungMA']))
		{
			echo '
			<zugangsberechtigungMA>
				<datum>'.$studiengang['zugangsberechtigungMA']['datum'].'</datum>';

			if (isset($studiengang['zugangsberechtigungMA']['staat']))
				echo '<staat>'.$studiengang['zugangsberechtigungMA']['staat'].'</staat>';

			echo '
				<staat>'.$studiengang['zugangsberechtigungMA']['staat'].'</staat>
				<voraussetzung>'.$studiengang['zugangsberechtigungMA']['voraussetzung'].'</voraussetzung>
			</zugangsberechtigungMA>
			';
		}

		if(isset($studiengang['zulassungsdatum']))
		{
			echo '<zulassungsdatum>'.$studiengang['zulassungsdatum'].'</zulassungsdatum>';
		}

		echo '</studiengang>';
		endforeach;
	}
?>
	</studien>
</studienanfrage>
