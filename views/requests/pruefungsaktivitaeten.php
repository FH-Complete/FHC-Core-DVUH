<?xml version="1.0" encoding="UTF-8"?>
<pruefungsaktivitaetenanfrage xmlns="http://www.brz.gv.at/datenverbund-unis">
	<studiumpruefungen>
		<?php if (isset($studiumpruefungen)):?>
		<?php foreach ($studiumpruefungen as $studiumpruefung): ?>
		<studiumpruefung>
			<studiengang><?php echo $studiumpruefung['studiengang'] ?></studiengang>
			<studierendenkey>
				<matrikelnummer><?php echo $studiumpruefung['matrikelnummer'] ?></matrikelnummer>
				<be><?php echo $be ?></be>
				<semester><?php echo $studiumpruefung['studiensemester'] ?></semester>
			</studierendenkey>
			<?php
			$ectsArExists = isset($studiumpruefung['ects']->ects_angerechnet) && $studiumpruefung['ects']->ects_angerechnet >= 0;
			$ectsErExists = isset($studiumpruefung['ects']->ects_erworben) && $studiumpruefung['ects']->ects_erworben >= 0;
			?>
			<?php if ($ectsArExists || $ectsErExists):?>
				<pruefungen>
					<pruefung>
						<?php if ($ectsErExists):?>
							<ects bezug="erworben"><?php echo $studiumpruefung['ects']->ects_erworben ?></ects>
						<?php endif; ?>
						<?php if ($ectsArExists):?>
							<ects bezug="angerechnet"><?php echo $studiumpruefung['ects']->ects_angerechnet ?></ects>
						<?php endif; ?>
						<fach>1</fach>
						<semesterstunden>0</semesterstunden>
						<semesterstundenpositiv>0</semesterstundenpositiv>
						<semesterzahl>1</semesterzahl>
					</pruefung>
				</pruefungen>
			<?php endif; ?>
		</studiumpruefung>
		<?php endforeach; ?>
		<?php endif; ?>
	</studiumpruefungen>
	<uuid><?php echo $uuid ?></uuid>
</pruefungsaktivitaetenanfrage>