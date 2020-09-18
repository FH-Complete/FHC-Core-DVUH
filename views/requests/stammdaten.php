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
			foreach($adressen as $adresse)
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
		if(isset($akadgrad) && $akadgrad!='')
		{
			echo "\t\t\t<akadgrad>".$akadgrad."</akadgrad>\n";
		}
		if(isset($akadnach) && $akadnach != '')
		{
			echo "\t\t\t<akadnach>".$akadgradnach."</akadnach>\n";
		}
?>
		<beitragstatus><?php echo $beitragsstatus; ?></beitragstatus>
<?php
		if(isset($svnr) && $svnr != '')
		{
			echo "\t\t<bpk>".$bpk."</bpk>\n";
		}

		if(isset($svnr) && $svnr != '')
		{
			echo "\t\t<ekz>".$ekz."</ekz>";
		}
?>
		<emailliste>
<?php
			foreach($emailliste as $email)
			{
				echo "\t\t\t<email>\n";
				echo "\t\t\t\t<emailadresse>".$email['emailadresse']."</emailadresse>\n";
				echo "\t\t\t\t<emailtyp>".$email['emailtyp']."</emailtyp>\n";
				echo "\t\t\t</email>\n";
			}
?>
		</emailliste>
		<geburtsdatum><?php echo $geburtsdatum; ?></geburtsdatum>
		<geschlecht><?php echo $geschlecht; ?></geschlecht>
		<nachname><?php echo $nachname; ?></nachname>
		<staatsbuergerschaft><?php echo $staatsbuergerschaft; ?></staatsbuergerschaft>
<?php
		if(isset($svnr) && $svnr != '')
		{
			echo "\t\t<svnr>".$svnr."</svnr>";
		}
?>
		<vorname><?php echo $vorname; ?></vorname>
	</stammdaten>
	<vorschreibung>
		<oehbeitrag><?php echo $oehbeitrag; ?></oehbeitrag>
		<sonderbeitrag><?php echo $sonderbeitrag; ?></sonderbeitrag>
		<studienbeitrag><?php echo $studienbeitrag; ?></studienbeitrag>
		<studienbeitragnachfrist><?php echo $studienbeitragnachfrist; ?></studienbeitragnachfrist>
		<studiengebuehr><?php echo $studiengebuehr; ?></studiengebuehr>
		<studiengebuehrnachfrist><?php echo $studiengebuehrnachfrist; ?></studiengebuehrnachfrist>
		<valutadatum><?php echo $valutadatum; ?></valutadatum>
		<valutadatumnachfrist><?php echo $valutadatumnachfrist; ?></valutadatumnachfrist>
	</vorschreibung>
</stammdatenanfrage>
