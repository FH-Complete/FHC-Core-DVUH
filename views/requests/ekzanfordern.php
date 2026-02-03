<?xml version="1.0" encoding="UTF-8"?>
<ekzUseCase xmlns="http://www.brz.gv.at/datenverbund-unis">
	<ekzAnforderung>
		<ekzBasis>
			<adresse>
				<ort><?php echo $ekzbasisdaten['adresse']['ort'] ?></ort>
				<plz><?php echo $ekzbasisdaten['adresse']['plz'] ?></plz>
				<staat><?php echo $ekzbasisdaten['adresse']['staat'] ?></staat>
				<strasse><?php echo $ekzbasisdaten['adresse']['strasse'] ?></strasse>
			</adresse>
			<gebDatum><?php echo $ekzbasisdaten['geburtsdatum'] ?></gebDatum>
			<geschlecht><?php echo $ekzbasisdaten['geschlecht'] ?></geschlecht>
			<nachname><?php echo $ekzbasisdaten['nachname'] ?></nachname>
		<?php if (isset($ekzbasisdaten['orgKey'])): ?>
			<orgKey><?php echo $ekzbasisdaten['orgKey'] ?></orgKey>
		<?php endif; ?>
		<?php if (isset($ekzbasisdaten['requestTimestamp'])): ?>
			<requestTimestamp><?php echo $ekzbasisdaten['requestTimestamp'] ?></requestTimestamp>
		<?php endif; ?>
			<vorname><?php echo $ekzbasisdaten['vorname'] ?></vorname>
		</ekzBasis>
	<?php if (isset($forcierungskey)):?>
		<forcierungskey><?php echo $forcierungskey ?></forcierungskey>
	<?php endif; ?>
	</ekzAnforderung>
</ekzUseCase>
