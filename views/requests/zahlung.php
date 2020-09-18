<?xml version="1.0" encoding="UTF-8"?>
<zahlungsanfrage xmlns="http://www.brz.gv.at/datenverbund-unis">
	<uuid><?php echo $uuid; ?></uuid>
	<studierendenkey>
		<matrikelnummer><?php echo $matrikelnummer; ?></matrikelnummer>
		<be><?php echo $be; ?></be>
		<semester><?php echo $semester; ?></semester>
	</studierendenkey>
	<zahlungsart><?php echo $zahlungsart; ?></zahlungsart>
	<betrag><?php echo $centbetrag; ?></betrag>
	<buchungsdatum><?php echo $buchungsdatum; ?></buchungsdatum>
	<referenznummer><?php echo $referenznummer; ?></referenznummer>
</zahlungsanfrage>
