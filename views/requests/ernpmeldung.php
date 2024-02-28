<?xml version="1.0" encoding="UTF-8"?>
<ErnpMeldungAnfrage xmlns="http://www.brz.gv.at/datenverbund-unis">
<?php if (isset($ernpmeldung)): ?>
	<?php if (isset($ernpmeldung['adresse'])): ?>
	<adresse>
		<hausnummer>1</hausnummer>
		<ort><?php echo $ernpmeldung['adresse']['ort']; ?></ort>
		<plz><?php echo $ernpmeldung['adresse']['plz']; ?></plz>
		<staat><?php echo $ernpmeldung['adresse']['staat']; ?></staat>
		<strasse><?php echo $ernpmeldung['adresse']['strasse']; ?></strasse>
	</adresse>
	<?php endif; ?>
	<be><?php echo $ernpmeldung['be']; ?></be>
	<gebdat><?php echo $ernpmeldung['gebdat']; ?></gebdat>
	<geburtsland><?php echo $ernpmeldung['geburtsland']; ?></geburtsland>
	<geschlecht><?php echo $ernpmeldung['geschlecht']; ?></geschlecht>
	<?php if (isset($ernpmeldung['idDokument'])): ?>
	<idDokument>
		<ausgabedatum><?php echo $ernpmeldung['idDokument']['ausgabedatum']; ?></ausgabedatum>
		<ausstellBehoerde><?php echo $ernpmeldung['idDokument']['ausstellBehoerde']; ?></ausstellBehoerde>
		<ausstellland><?php echo $ernpmeldung['idDokument']['ausstellland']; ?></ausstellland>
		<dokumentnr><?php echo $ernpmeldung['idDokument']['dokumentnr']; ?></dokumentnr>
		<dokumenttyp><?php echo $ernpmeldung['idDokument']['dokumenttyp']; ?></dokumenttyp>
	</idDokument>
	<?php endif; ?>
	<?php
		if (isset($ernpmeldung['matrikelnummer']) && $ernpmeldung['matrikelnummer'] != '')
		{
			echo "\t\t\t<matrikelnummer>".$ernpmeldung['matrikelnummer']."</matrikelnummer>";
		}
	?>
	<nachname><?php echo $ernpmeldung['nachname']; ?></nachname>
	<staatsangehoerigkeit><?php echo $ernpmeldung['staatsangehoerigkeit']; ?></staatsangehoerigkeit>
	<uuid><?php echo $uuid; ?></uuid>
	<vorname><?php echo $ernpmeldung['vorname']; ?></vorname>
	<?php endif; ?>
</ErnpMeldungAnfrage>
