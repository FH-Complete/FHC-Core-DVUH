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
	if(isset($lehrgang))
	{
		echo '
			<lehrgang>';
		if(isset($lehrgang['beedingungsdatum']))
			echo '<beendigungsdatum>'.$lehrgang['beedingungsdatum'].'</beendigungsdatum>';
		echo '
				<lehrgangsnr>'.$lehrgang['lehrgangsnr'].'</lehrgangsnr>
				<perskz>'.$lehrgang['perskz'].'</perskz>
				<studstatuscode>'.$lehrgang['studstatuscode'].'</studstatuscode>';
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

		echo '<zulassungsdatum>'.$lehrgang['zulassungsdatum'].'</zulassungsdatum>
			</lehrgang>';
	}
	// Studiengang
	if(isset($studiengang))
	{
		echo '
		<studiengang disloziert="'.$studiengang['disloziert'].'">
			<ausbildungssemester>'.$studiengang['ausbildungssemester']."</ausbildungssemester>\n";

		if(isset($studiengang['beendigungsdatum']) && $studiengang['beendigungsdatum']!='')
		{
			echo "\t\t\t<beendigungsdatum>".$studiengang['beendigungsdatum']."</beendigungsdatum>\n";
		}

		if(isset($studiengang['berufstaetigkeitcode']) && $studiengang['berufstaetigkeitcode']!='')
		{
			echo "\t\t\t<berufstaetigkeitcode>".$studiengang['berufstaetigkeitcode']."</berufstaetigkeitcode>\n";
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
			echo '
			<mobilitaet>
				<aufenthaltfoerderungcode>'.$studiengang['mobilitaet']['aufenthaltfoerderungcode']."</aufenthaltfoerderungcode>\n";

			if(isset($studiengang['mobilitaet']['bis']) && $studiengang['mobilitaet']['bis']!='')
				echo "\t\t\t\t<bis>".$studiengang['mobilitaet']['bis']."</bis>\n";

			if(isset($studiengang['mobilitaet']['ectsangerechnet']) && $studiengang['mobilitaet']['ectsangerechnet'] != '')
				echo "\t\t\t\t<ectsangerechnet>".$studiengang['mobilitaet']['ectsangerechnet']."</ectsangerechnet>\n";

			if(isset($studiengang['mobilitaet']['ectserworben']) && $studiengang['mobilitaet']['ectserworben'] != '')
				echo "\t\t\t\t<ectserworben>".$studiengang['mobilitaet']['ectserworben']."</ectserworben>\n";

			echo "\t\t\t\t<programm>".$studiengang['mobilitaet']['programm']."</programm>\n";
			echo "\t\t\t\t<staat>".$studiengang['mobilitaet']['staat']."</staat>\n";
			echo "\t\t\t\t<von>".$studiengang['mobilitaet']['von']."</von>\n";
			echo "\t\t\t\t<zweck>".$studiengang['mobilitaet']['zweck']."</zweck>\n";
			echo "\t\t\t</mobilitaet>\n";
		}
		echo '
			<orgformcode>'.$studiengang['orgformcode'].'</orgformcode>
			<perskz>'.$studiengang['perskz'].'</perskz>
			<standortcode>'.$studiengang['standortcode'].'</standortcode>
			<stgkz>'.$studiengang['stgkz'].'</stgkz>
			<studstatuscode>'.$studiengang['studstatuscode'].'</studstatuscode>';
		if(isset($studiengang['vonnachperskz']))
		{
			echo '
			<vornachperskz>'.$studiengang['vornachperskz'].'</vornachperskz>';
		}

		if(isset($studiengang['zugangsberechtigung']))
		{
			echo '
			<zugangsberechtigung>
				<datum>'.$studiengang['zugangsberechtigung']['datum'].'</datum>
				<staat>'.$studiengang['zugangsberechtigung']['staat'].'</staat>
				<voraussetzung>'.$studiengang['zugangsberechtigung']['voraussetzung'].'</voraussetzung>
			</zugangsberechtigung>
			';
		}

		if(isset($studiengang['zugangsberechtigungMA']))
		{
			echo '
			<zugangsberechtigungMA>
				<datum>'.$studiengang['zugangsberechtigungMA']['datum'].'</datum>
				<staat>'.$studiengang['zugangsberechtigungMA']['staat'].'</staat>
				<voraussetzung>'.$studiengang['zugangsberechtigungMA']['voraussetzung'].'</voraussetzung>
			</zugangsberechtigungMA>
			';
		}
		echo '
			<zulassungsdatum>'.$studiengang['zulassungsdatum'].'</zulassungsdatum>
		</studiengang>';
	}
?>

	</studien>
</studienanfrage>
