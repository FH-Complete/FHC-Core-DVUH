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
			<?php if (isset($studiumpruefung['ects'])):?>
				<pruefungen>
					<?php if (isset($studiumpruefung['ects']->ects_angerechnet) && $studiumpruefung['ects']->ects_angerechnet > 0):?>
						<pruefung>
							<ects bezug="angerechnet"><?php echo $studiumpruefung['ects']->ects_angerechnet ?></ects>
							<fach>1</fach>
							<semesterstunden>0</semesterstunden>
							<semesterstundenpositiv>0</semesterstundenpositiv>
							<semesterzahl>1</semesterzahl>
						</pruefung>
					<?php endif; ?>
					<?php if (isset($studiumpruefung['ects']->ects_erworben) && $studiumpruefung['ects']->ects_erworben > 0):?>
						<pruefung>
							<ects bezug="erworben"><?php echo $studiumpruefung['ects']->ects_erworben ?></ects>
							<fach>1</fach>
							<semesterstunden>0</semesterstunden>
							<semesterstundenpositiv>0</semesterstundenpositiv>
							<semesterzahl>1</semesterzahl>
						</pruefung>
					<?php endif; ?>
				</pruefungen>
			<?php endif; ?>
		</studiumpruefung>
		<?php endforeach; ?>
		<?php endif; ?>
	</studiumpruefungen>
	<uuid><?php echo $uuid ?></uuid>
</pruefungsaktivitaetenanfrage>