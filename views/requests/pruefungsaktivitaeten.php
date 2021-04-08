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
			<?php if (isset($studiumpruefung['pruefungen'])):?>
				<pruefungen>
				<?php foreach ($studiumpruefung['pruefungen'] as $pruefung): ?>
					<pruefung>
						<ects bezug="gesamt"><?php echo $pruefung['ects'] ?></ects>
						<?php
							if (isset($pruefung['ectsGesamt']) && $pruefung['ectsGesamt'] != '')
							{
								echo "\t\t\t\t\ŧ\t<ectsGesamt>".$pruefung['ectsGesamt']."</ectsGesamt>";
							}
						?>
						<fach>1</fach>
						<semesterstunden>0</semesterstunden>
						<semesterstundenpositiv>0</semesterstundenpositiv>
						<semesterzahl>1</semesterzahl>
					</pruefung>
				<?php endforeach; ?>
				</pruefungen>
			<?php endif; ?>
		</studiumpruefung>
		<?php endforeach; ?>
		<?php endif; ?>
	</studiumpruefungen>
	<uuid><?php echo $uuid ?></uuid>
</pruefungsaktivitaetenanfrage>