<?xml version="1.0" encoding="UTF-8"?>
<matrikelnummernmeldung xmlns="http://www.brz.gv.at/datenverbund-unis">
	<personmeldung xmlns="http://www.brz.gv.at/datenverbund-unis">
		<adresseAusland><?php echo $personmeldung['adresseAusland']; ?></adresseAusland>
		<be><?php echo $personmeldung['be']; ?></be>

<?php if (isset($personmeldung['bpk']) && $personmeldung['bpk'] != ''): ?>
		<bpk><?php echo $personmeldung['bpk'] ?></bpk>
<?php endif; ?>

<?php if (isset($personmeldung['ekz']) && $personmeldung['ekz'] != ''): ?>
		<ekz><?php echo $personmeldung['ekz'] ?></ekz>
<?php endif; ?>

		<gebdat><?php echo $personmeldung['gebdat']; ?></gebdat>
		<geschlecht><?php echo $personmeldung['geschlecht']; ?></geschlecht>

<?php if (isset($personmeldung['matrikelnummer']) && $personmeldung['matrikelnummer'] != ''): ?>
		<matrikelnummer><?php echo $personmeldung['matrikelnummer'] ?></matrikelnummer>
<?php endif; ?>

		<matura><?php echo $personmeldung['matura']; ?></matura>
		<nachname><?php echo $personmeldung['nachname']; ?></nachname>
		<plz><?php echo $personmeldung['plz']; ?></plz>
		<staat><?php echo $personmeldung['staat']; ?></staat>
		<vorname><?php echo $personmeldung['vorname']; ?></vorname>

<?php if (isset($personmeldung['writeonerror']) && $personmeldung['writeonerror'] === 'true'): ?>
		<writeonerror><?php echo $personmeldung['writeonerror'] ?></writeonerror>
<?php endif; ?>

	</personmeldung>
	<uuid><?php echo $uuid; ?></uuid>
</matrikelnummernmeldung>
