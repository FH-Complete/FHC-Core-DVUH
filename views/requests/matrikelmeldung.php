<?xml version="1.0" encoding="UTF-8"?>
<matrikelnummernmeldung xmlns="http://www.brz.gv.at/datenverbund-unis">
	<personmeldung xmlns="http://www.brz.gv.at/datenverbund-unis">
		<adresseAusland><?php echo $personmeldung['adresseAusland']; ?></adresseAusland>
		<be><?php echo $personmeldung['be']; ?></be>
		<?php
			if (isset($personmeldung['bpk']) && $personmeldung['bpk'] != '')
			{
				echo "\t\t\t<bpk>".$personmeldung['bpk']."</bpk>";
			}
		?>
		<gebdat><?php echo $personmeldung['gebdat']; ?></gebdat>
		<geschlecht><?php echo $personmeldung['geschlecht']; ?></geschlecht>
		<?php
			if (isset($personmeldung['matrikelnummer']) && $personmeldung['matrikelnummer'] != '')
			{
				echo "\t\t\t<matrikelnummer>".$personmeldung['matrikelnummer']."</matrikelnummer>";
			}
		?>
		<matura><?php echo $personmeldung['matura']; ?></matura>
		<nachname><?php echo $personmeldung['nachname']; ?></nachname>
		<plz><?php echo $personmeldung['plz']; ?></plz>
		<staat><?php echo $personmeldung['staat']; ?></staat>
		<svnr><?php echo $personmeldung['svnr']; ?></svnr>
		<vorname><?php echo $personmeldung['vorname']; ?></vorname>
		<?php
			if (isset($personmeldung['writeonerror']) && $personmeldung['writeonerror'] != '')
			{
				echo "\t\t\t<writeonerror>".$personmeldung['writeonerror']."</writeonerror>";
			}
		?>
	</personmeldung>
	<uuid><?php echo $uuid; ?></uuid>
</matrikelnummernmeldung>
