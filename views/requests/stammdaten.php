<?xml version="1.0" encoding="UTF-8"?>
<stammdatenanfrage xmlns="http://www.brz.gv.at/datenverbund-unis">
	<uuid><?php echo $uuid; ?></uuid>
	<studierendenkey>
		<matrikelnummer><?php echo $studierendenkey['matrikelnummer']; ?></matrikelnummer>
		<be><?php echo $studierendenkey['be']; ?></be>
		<semester><?php echo $studierendenkey['semester']; ?></semester>
	</studierendenkey>
	<stammdaten>
		<adressen>
<?php
			foreach($studentinfo['adressen'] as $adresse)
			{
				echo "\t\t\t<adresse>\n";

				if(isset($adresse['coname']))
					echo "\t\t\t\t<coname>".$adresse['coname']."</coname>\n";

				echo "\t\t\t\t<ort>".$adresse['ort']."</ort>\n";
				echo "\t\t\t\t<plz>".$adresse['plz']."</plz>\n";
				echo "\t\t\t\t<staat>".$adresse['staat']."</staat>\n";
				echo "\t\t\t\t<strasse>".$adresse['strasse']."</strasse>\n";
				echo "\t\t\t\t<typ>".$adresse['typ']."</typ>\n";
				echo "\t\t\t</adresse>\n";
			}
?>
		</adressen>
<?php
		if(isset($studentinfo['akadgrad']) && $studentinfo['akadgrad']!='')
		{
			echo "\t\t\t<akadgrad>".$studentinfo['akadgrad']."</akadgrad>\n";
		}
		if(isset($studentinfo['akadgradnach']) && $studentinfo['akadgradnach'] != '')
		{
			echo "\t\t\t<akadnach>".$studentinfo['akadgradnach']."</akadnach>\n";
		}
?>
		<beitragstatus><?php echo $studentinfo['beitragstatus']; ?></beitragstatus>
<?php
		if(isset($studentinfo['bpk']) && $studentinfo['bpk'] != '')
		{
			echo "\t\t<bpk>".$studentinfo['bpk']."</bpk>\n";
		}

		if(isset($studentinfo['ekz']) && $studentinfo['ekz'] != '')
		{
			echo "\t\t<ekz>".$studentinfo['ekz']."</ekz>";
		}
?>
		<emailliste>
<?php
			foreach($studentinfo['emailliste'] as $email)
			{
				echo "\t\t\t<email>\n";
				echo "\t\t\t\t<emailadresse>".$email['emailadresse']."</emailadresse>\n";
				echo "\t\t\t\t<emailtyp>".$email['emailtyp']."</emailtyp>\n";
				echo "\t\t\t</email>\n";
			}
?>
		</emailliste>
		<geburtsdatum><?php echo $studentinfo['geburtsdatum']; ?></geburtsdatum>
		<geschlecht><?php echo $studentinfo['geschlecht']; ?></geschlecht>
		<nachname><?php echo $studentinfo['nachname']; ?></nachname>
		<staatsbuergerschaft><?php echo $studentinfo['staatsbuergerschaft']; ?></staatsbuergerschaft>
<?php
		if(isset($studentinfo['svnr']) && $studentinfo['svnr'] != '')
		{
			echo "\t\t<svnr>".$studentinfo['svnr']."</svnr>";
		}
?>
		<vorname><?php echo $studentinfo['vorname']; ?></vorname>
	</stammdaten>
<?php if(isset($vorschreibung)): ?>
	<vorschreibung>
		<oehbeitrag><?php echo $vorschreibung['oehbeitrag']; ?></oehbeitrag>
		<sonderbeitrag><?php echo $vorschreibung['sonderbeitrag']; ?></sonderbeitrag>
		<studienbeitrag><?php echo $vorschreibung['studienbeitrag']; ?></studienbeitrag>
		<studienbeitragnachfrist><?php echo $vorschreibung['studienbeitragnachfrist']; ?></studienbeitragnachfrist>
		<studiengebuehr><?php echo $vorschreibung['studiengebuehr']; ?></studiengebuehr>
		<studiengebuehrnachfrist><?php echo $vorschreibung['studiengebuehrnachfrist']; ?></studiengebuehrnachfrist>
		<valutadatum><?php echo $vorschreibung['valutadatum']; ?></valutadatum>
		<valutadatumnachfrist><?php echo $vorschreibung['valutadatumnachfrist']; ?></valutadatumnachfrist>
	</vorschreibung>
<?php endif ?>
</stammdatenanfrage>
