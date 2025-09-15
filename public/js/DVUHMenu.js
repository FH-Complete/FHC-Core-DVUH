/**
 * javascript file for displaying DVUH actions menu and executing DVUH calls
 */

$(document).ready(function() {

	var callback = function()
	{
		// show / hide menu
		$("#toggleMenu").click(
			function()
			{
				DVUHMenu._toggleMenu($("#menuContainer").is(":visible"));
			}
		);

		// print form when clicking on menu entry
		$(".dvuhMenu li").click(
			function()
			{
				DVUHMenu.printForm($(this).prop('id'));
			}
		);

		// scroll to top button
		DVUHMenu._setScrollToTop();

		DVUHMenu.getDvuhMenuData(
			function()
			{
				// get hash params from url and show appropriate form (if coming from external site)
				var hash = window.location.hash.substr(1);

				var result = hash.split('&').reduce(function(res, item)
				{
					var parts = item.split('=');
					res[parts[0]] = parts[1];
					return res;
				}, {});

				if (result.page && result.page.length > 0)
				{
					DVUHMenu.printForm(result.page, result);
				}
			}
		);
	}

	DVUHMenu.getPermittedActions(callback);
});

var DVUHMenu = {
	fhcData: null,
	printForm: function(action, params)
	{
		var html = '';
		var method = '';
		var writePreviewButton = false;

		switch(action)
		{
			case 'getMatrikelnummer':
				html = '<h4>Matrikelnummer pr&uuml;fen</h4>';
				html += DVUHMenu._getPreviewInputfieldHtml('matrnrDatenVorausfuellen');
				html += DVUHMenu._getTextfieldHtml('bpk', 'BPK', '', 30)
				+ DVUHMenu._getTextfieldHtml('svnr', 'SVNR', '', 10)
				+ DVUHMenu._getTextfieldHtml('ekz', 'Ersatzkennzeichen', '', 10)
				+ DVUHMenu._getTextfieldHtml('vorname', 'Vorname', '', 64)
				+ DVUHMenu._getTextfieldHtml('nachname', 'Nachname', '', 255)
				+ DVUHMenu._getTextfieldHtml('geburtsdatum', 'Geburtsdatum', 'Format: DD.MM.YYYY oder YYYY-MM-DD', 10);
				method = 'get';
				if (typeof params !== 'undefined' && params.hasOwnProperty('person_id'))
					DVUHMenu.getPersonPrefillData(params.person_id, 'matrnrDatenVorausfuellen');
				break;
			case 'getMatrikelnummerReservierungen':
				html = '<h4>Matrikelnummerreservierungen anzeigen</h4>';
				html += DVUHMenu._getStudienjahrRow();
				method = 'get';
				break;
			case 'getStammdaten':
				html = '<h4>Stammdaten und Zahlungsvorschreibung abfragen</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
				+ DVUHMenu._getSemesterRow()
				method = 'get';
				break;
			case 'getKontostaende':
				html = '<h4>Kontostand abfragen</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('seit', 'Dateineingang seit', 'Format: DD.MM.YYYY oder YYYY-MM-DD, optional', 10)
				method = 'get';
				break;
			case 'getStudium':
				html = '<h4>Studiumsdaten abfragen</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('studienkennung', 'Studienkennung', 'optional; Studiengesetz (1 Zeichen, typischerweise \'U\',\'H\' oder \'L\') ' +
						'+ 2. BE-Kennung 1 (2 Zeichen) + SKZ 1 (Kopfcode oder Studienkennzahl, 3 Ziffern) + SKZ 2 (z.B. Lehramt Fach 1, 3 Ziffern; optional) ' +
						'+ SKZ 3 (z.B. Lehramt Fach 2, 3 Ziffern; optional) + BE-Kennung 2 (2 Zeichen; optional).', 14)
				method = 'get';
				break;
			case 'getFullstudent':
				html = '<h4>Detaillierte Studiendaten abfragen</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getTextfieldHtml('semester', 'Studiensemester', 'optional, z.B. SS2016 oder 2016S für Sommer-, WS2016 oder 2016W für Wintersemester 2016', 6)
				method = 'get';
				break;
			case 'getPruefeBpk':
				html = '<h4>bPK ermitteln (manuell)</h4>';
				html += DVUHMenu._getPreviewInputfieldHtml('bpkDatenVorausfuellen', 'bpkDatenVorausfuellenVoll');
				html += DVUHMenu._getTextfieldHtml('vorname', 'Vorname', '', 64)
					+ DVUHMenu._getTextfieldHtml('nachname', 'Nachname', '', 255)
					+ DVUHMenu._getTextfieldHtml('geburtsdatum', 'Geburtsdatum', 'Format: DD.MM.YYYY oder YYYY-MM-DD', 10)
					+ DVUHMenu._getTextfieldHtml('geschlecht', 'Geschlecht', 'M/W/X', 1)
					+ DVUHMenu._getTextfieldHtml('strasse', 'Strasse', 'der Heimatadresse, ohne Hausnummer, optional', 255)
					+ DVUHMenu._getTextfieldHtml('hausnummer', 'Hausnummer', 'der Heimatadresse, optional', 255)
					+ DVUHMenu._getTextfieldHtml('plz', 'PLZ', 'optional', 15)
					+ DVUHMenu._getTextfieldHtml('staat', 'Staat', 'der Heimatadresse, optional', 15)
					+ DVUHMenu._getTextfieldHtml('frueherername', 'Früherer Name', 'optional, Bei Änderungen des Familiennamen ist hier der Name vor dem Wechsel anzugeben', 255)
					+ DVUHMenu._getTextfieldHtml('sonstigername', 'Alternativer Name', 'optional', 255)
				method = 'get';
				if (typeof params !== 'undefined' && params.hasOwnProperty('person_id'))
					DVUHMenu.getPersonPrefillData(params.person_id, 'bpkDatenVorausfuellen');
				break;
			case 'getPruefeBpkByPersonId':
				html = '<h4>bPK ermitteln</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID');
				method = 'get';
				break;
			case 'getPruefungsaktivitaeten':
				html = '<h4>Pr&uuml;fungsaktivit&auml;ten abfragen</h4>';
				html += DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('matrikelnummer', 'Matrikelnummer', 'optional', 8)
				method = 'get';
				break;
			case 'reserveMatrikelnummer':
				html = '<h4>Matrikelnummer reservieren</h4>';
				html += DVUHMenu._getStudienjahrRow();
				method = 'post';
				break;
			case 'postMatrikelnummer':
				html = '<h4>Matrikelnummer melden</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getMatrikelnummerRow(null, 'default: bereits gespeicherte Matrikelnummer (wenn vorhanden)')
					+ DVUHMenu._getCheckboxHtml('writeonerror', 'Meldung Bei Fehler erzwingen', 'optional')
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postMatrikelkorrektur':
				html = '<h4>Matrikelnummer korrigieren</h4>';
				html += DVUHMenu._getTextfieldHtml('matrikelnummer', 'Neue Matrikelnummer', '', 8)
					+ DVUHMenu._getTextfieldHtml('matrikelalt', 'Alte Matrikelnummer', '', 8)
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				break;
			case 'postMasterData':
				html = '<h4>Stammdaten und Matrikelnummer melden</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postPayment':
				html = '<h4>Zahlungseingang melden</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postStudium':
				html = '<h4>Studiumsdaten melden</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('prestudent_id', 'PrestudentID', 'optional')
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postErnpmeldung':

				var person_id = null
				// prefill input values if coming from external site
				if (typeof params !== 'undefined')
				{
					if (params.hasOwnProperty('person_id'))
						person_id = params.person_id;
				}

				// get dropdown values for nations
				var nationsDropdownValues = {};
				for (var idx in DVUHMenu.fhcData.nations)
				{
					var nation = DVUHMenu.fhcData.nations[idx];
					nationsDropdownValues[nation.nation_code] = nation.nation_text+" - "+nation.nation_code;
				}

				html = '<h4>ERnP-Meldung durchführen</h4>';
				html += '<b>HINWEIS: Die Eintragung ins ERnP (Ergänzungsregister für natürliche Personen) sollte nur dann durchgeführt werden, ' +
					'wenn für die Person keine bPK ermittelt werden kann.<br />Beim Punkt "bPK ermitteln" sollte dementsprechend keine bPK zurückgegeben werden. ' +
					'Ist ein aktueller oder früherer Wohnsitz in Österreich vorhanden, ist ein bPK schon vorhanden und die ERnP-Meldung darf nicht durchgeführt werden!</b><br /><br />';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID', null, null, person_id)
					+ DVUHMenu._getTextfieldHtml('ausgabedatum', 'Ausgabedatum', 'Format: DD.MM.YYYY oder YYYY-MM-DD', 10)
					+ DVUHMenu._getTextfieldHtml('ausstellBehoerde', 'Ausstellbehörde', '', 40)
					+ DVUHMenu._getDropdownHtml('ausstellland', 'Ausstellland', nationsDropdownValues, "D", '1-3 Stellen Codex (zb D für Deutschland)')
					+ DVUHMenu._getTextfieldHtml('dokumentnr', 'Dokumentnr', '1 bis 255 Stellen', 255)
					+ DVUHMenu._getDropdownHtml('dokumenttyp', 'Dokumenttyp', {'REISEP': 'Reisepass', 'PERSAUSW': 'Personalausweis'}, 'REISEP')
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postEkzanfordern':
				html = '<h4>Ekz anfordern</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getTextfieldHtml('forcierungskey', 'Forcierungskey', 'Optional, zum Anfordern eines neuen EKZ, ' +
						'wenn bei mehrerern zurückgelieferten Kandidaten keiner der Person entspricht, für die man das EKZ anfordern möchte', 255) // TODO which length?
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postPruefungsaktivitaeten':
				html = '<h4>Prüfungsaktivitäten-Meldung durchführen</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postStudiumStorno':

				// get dropdown values for studiengang
/*				var stgDropdownValues = {};
				for (var idx in DVUHMenu.fhcData.stg)
				{
					var stg = DVUHMenu.fhcData.stg[idx];
					stgDropdownValues[stg.studiengang_kz] = stg.studiengang_kz + " ("+stg.studiengang_text+")";
				}*/

				var semester = null
				var prestudent_id = null

				// prefill input values if coming from external site
				if (typeof params !== 'undefined')
				{
					if (params.hasOwnProperty('studiensemester_kurzbz')) // convert sutdiensemester to FHC before the prefill
						semester = params.studiensemester_kurzbz.substring(2, 7) + params.studiensemester_kurzbz.substring(0, 1);
					if (params.hasOwnProperty('prestudent_id'))
						prestudent_id = params.prestudent_id;
				}

				html = '<h4>Studiumsdaten stornieren</h4>'; // TODO phrases
				html += DVUHMenu._getTextfieldHtml('prestudent_id', 'PrestudentID', null, null, prestudent_id)
					+ DVUHMenu._getSemesterRow(semester)
				method = 'post';
				writePreviewButton = true;
				break;
			case 'deletePruefungsaktivitaeten':
				html = '<h4>Prüfungsaktivitäten löschen</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('prestudent_id', 'PrestudentID', 'optional')
				method = 'post';
				break;
		}

		// reset Gui
		DVUHMenu._clearGui();

		// form
		$("#dvuhForm").html(html);

		// prefill from Person Id
		var buttonIds = ['matrnrDatenVorausfuellen', 'bpkDatenVorausfuellen', 'bpkDatenVorausfuellenVoll'];

		for (var i = 0; i < buttonIds.length; i++)
		{
			var buttonId = buttonIds[i];
			if ($("#"+buttonId).length)
			{
				$("#"+buttonId).click(
					function()
					{
						var person_id = $("#person_id").val();

						DVUHMenu.getPersonPrefillData(person_id, $(this).attr("id"));
					}
				);
			}
		}

		// data preview
		if (writePreviewButton)
		{
			$("#dvuhDatenvorschauButton").html('<button class="btn btn-default" id="datenVorschau">Zu sendende Daten anzeigen</button>');

			$("#datenVorschau").click(
				function()
				{
					var preview = true;
					DVUHMenu._writePreviewBox();
					DVUHMenu.sendForm(action, method, preview);
				}
			);
		}

		// actual data send
		$("#dvuhAbsendenButton").html('<button class="btn btn-default" id="dvuhAbsenden">Absenden</button>');

		$("#dvuhAbsenden").click(
			function()
			{
				DVUHMenu._writeSyncoutputBox();
				DVUHMenu.sendForm(action, method);
			}
		);
	},
	sendForm: function(action, method, preview)
	{
		var url = FHC_JS_DATA_STORAGE_OBJECT.called_path + '/' + action;
		var formData = DVUHMenu._getFormData();
		var boxid = 'dvuhOutput';

		if (preview)
		{
			formData.preview = preview;
			boxid = 'dvuhPreviewOutput';
		}

		var successCallback = function(data, textStatus, jqXHR)
		{
			if (FHC_AjaxClient.isSuccess(data))
			{
				if (FHC_AjaxClient.hasData(data))
				{
					DVUHMenu._writeResult(FHC_AjaxClient.getData(data), boxid);
				}
			}
			else
			{
				DVUHMenu._writeError(FHC_AjaxClient.getError(data), boxid);
			}
		}

		var errorCallback = function(jqXHR, textStatus, errorThrown)
		{
			DVUHMenu._writeError("Error when calling " + action, boxid, 'error');
		}

		if (method == 'get')
		{
			FHC_AjaxClient.ajaxCallGet(
				url,
				formData,
				{
					successCallback: successCallback,
					errorCallback: errorCallback
				}
			);
		}
		else if (method == 'post')
		{
			FHC_AjaxClient.ajaxCallPost(
				url,
				formData,
				{
					successCallback: successCallback,
					errorCallback: errorCallback
				}
			);
		}
	},

	/* ajax calls */
	getPermittedActions: function(callback)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/getPermittedActions',
			null,
			{
				successCallback: function(data)
				{
					// TODO phrases
					// if success
					if (FHC_AjaxClient.isSuccess(data))
					{
						// save the permissions
						DVUHMenu.permissions = FHC_AjaxClient.getData(data);
						// show only menu entries for which user has permission
						DVUHMenu._hideNonPermittedMenuActions();
						// execute callback for setting remaining GUI functionality
						callback();
					}
					else
						FHC_DialogLib.alertError("Fehler bei Holen der Berechtigungen");
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Fehler bei Holen der Berechtigungen");
				}
			}
		);
	},
	getDvuhMenuData: function(callback)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/getDvuhMenuData',
			null,
			{
				successCallback: function(data)
				{
					// TODO phrases
					if (FHC_AjaxClient.hasData(data))
					{
						DVUHMenu.fhcData = FHC_AjaxClient.getData(data);
						callback();
					}
					else
						FHC_DialogLib.alertError("Fehler bei Holen der fhcomplete Daten");
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Fehler bei Holen der fhcomplete Daten");
				}
			}
		);
	},
	// get person data for prefill of input fields
	getPersonPrefillData: function(person_id, buttonId)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/getPersonPrefillData',
			{"person_id": person_id},
			{
				successCallback: function(data)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						var prefillData = FHC_AjaxClient.getData(data);

						$("#person_id").val(person_id);

						$("#vorname").val(prefillData.vorname);
						$("#nachname").val(prefillData.nachname);
						$("#geburtsdatum").val(prefillData.gebdatum);
						if ($("#geschlecht")) $("#geschlecht").val(prefillData.geschlecht);

						if (buttonId == 'matrnrDatenVorausfuellen')
						{
							$("#bpk").val(prefillData.bpk);
							$("#svnr").val(prefillData.svnr);
							$("#ekz").val(prefillData.ersatzkennzeichen);
						}
						else if (buttonId == 'bpkDatenVorausfuellen')
						{
							$("#geburtsland").val('');
							$("#strasse").val('');
							$("#plz").val('');
							$("#akadgrad").val('');
							$("#akadnach").val('');
						}
						else if (buttonId == 'bpkDatenVorausfuellenVoll')
						{
							$("#geburtsland").val(prefillData.geburtsland);
							$("#strasse").val(prefillData.strasse);
							$("#plz").val(prefillData.plz);
							$("#akadgrad").val(prefillData.akadgrad);
							$("#akadnach").val(prefillData.akadnach);
						}
					}
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					DVUHMenu._writeError("Fehler beim Vorausfüllen", 'dvuhOutput');
				}
			}
		);
	},
	/* additional "private" methods */
	_hideNonPermittedMenuActions: function()
	{
		// hide all entries
		$("ul.dvuhMenu li").hide();
		$("#menuContainer .panelcolumn,.menucolumn").hide();

		// show only those with permissions
		for (var i = 0; i < DVUHMenu.permissions.length; i++) {
			var permission = DVUHMenu.permissions[i];
			if ($("#"+permission).length) {
				$("#"+permission).show();
				$("#"+permission).parents('.panelcolumn').first().show();
				$("#"+permission).parents('.menucolumn').first().show();
			}
		}
	},
	_getMatrikelnummerRow: function(value, hint)
	{
		if (!hint)
			hint = '';

		return DVUHMenu._getTextfieldHtml('matrikelnummer', 'Matrikelnummer', hint, 8, value)
	},
	_getStudienjahrRow: function()
	{
		return DVUHMenu._getTextfieldHtml('studienjahr', 'Studienjahr', 'zB 2016 (für WS2016 und SS2017)', 4);
	},
	_getSemesterRow: function(value)
	{
		// prefill Studiensemester value
		if (!value && DVUHMenu.fhcData.current_studiensemester) var value = DVUHMenu.fhcData.current_studiensemester;

		return DVUHMenu._getTextfieldHtml(
			'semester',
			'Studiensemester',
			'z.B. SS2016 oder 2016S für Sommer-, WS2016 oder 2016W für Wintersemester 2016',
			6,
			value
		)
	},
	_getTextfieldHtml: function(name, title, hint, maxlength, value)
	{
		if (!hint)
			hint = '';

		if (!maxlength)
			maxlength = 15;

		return '<div class="form-group">' +
					'<label class="col-lg-2 control-label" for="'+name+'">'+title+'</label>'+
					'<div class="col-lg-5">'+
						'<input class="form-control" id="'+name+'" name="'+name+'" type="text" size="30" maxlength="'+maxlength+'"'+
							(value ? ' value="'+value+'"' : '') +
						'>'+
					'</div>'+
					'<label class="col-lg-5 control-label form-hint" for="'+name+'">'+hint+'</label>'+
				'</div>';
	},
	_getCheckboxHtml: function(name, title, hint, value)
	{
		if (!hint)
			hint = '';

		return '<div class="form-group">' +
					'<label class="col-lg-2 control-label" for="'+name+'">'+title+'</label>'+
					'<div class="col-lg-5">'+
						'<input id="'+name+'" name="'+name+'" type="checkbox"'+
							(value ? ' value="'+value+'"' : '') +
						'>'+
					'</div>'+
					'<label class="col-lg-5 control-label form-hint" for="'+name+'">'+hint+'</label>'+
				'</div>';
	},
	_getDropdownHtml: function(name, title, values, selectedValue, hint, optional)
	{
		if (!hint)
			hint = '';

		var html = '<div class="form-group">' +
					'<label class="col-lg-2 control-label" for="'+name+'">'+title+'</label>' +
					'<div class="col-lg-5">'+
					'<select class="form-control" name="'+name+'">';

		if (optional === true)
			html += '<option value="">--- Keine Auswahl ---</option>'; // TODO phrases

		$.each(values, function(idx, value)
			{
				var selected = selectedValue === idx ? ' selected' : '';
				html += '<option value="'+idx+'"'+selected+'>'+value+'</option>';
			}
		)

		html += '</select>' +
			'</div>' +
			'<label class="col-lg-5 control-label form-hint" for="'+name+'">'+hint+'</label>'+
			'</div>';

		return html;
	},
	_getPreviewInputfieldHtml(buttonId, secondButtonId)
	{
		var html = '<div class="form-group">' +
					'<label class="col-lg-2 control-label" for="person_id">PersonID</label>'+
					'<div class="col-lg-5">'+
						'<div class="form-group input-group prefill-input-group">' +
							'<input type="text" class="form-control" id="person_id">' +
							'<span class="input-group-btn">' +
								'<button class="btn btn-default" type="button" id="'+buttonId+'">' +
									'Vorausfüllen' +
								'</button>' +
							'</span>' +
						'</div>' +
					'</div>';

		if (typeof secondButtonId !== 'undefined')
		{
			html += '<div class="col-lg-5">'+
						'<button class="btn btn-default" type="button" id="'+secondButtonId+'">' +
							'Vorausfüllen (inkl. optional)' +
						'</button>' +
					'</div>';
		}

		html += '</div>';

		return html;
	},
	_getFormData: function()
	{
		var data = $("#dvuhForm").serializeArray();
		var result = {data: {}};

		for (var obj in data)
		{
			if (data[obj].value !== '')
				result.data[data[obj].name] = data[obj].value;
		}

		return result;
	},
	_writeError: function(resultToWrite, boxid)
	{
		var intro = "Fehler aufgetreten:";
		var contentToWrite = '';

		// if error, display error text
		if (typeof resultToWrite === 'string')
			contentToWrite += resultToWrite;
		else if (typeof resultToWrite === 'object' && resultToWrite.issue_fehlertext)
			contentToWrite += resultToWrite.issue_fehlertext
		else if ($.isArray(resultToWrite))
		{

			for (idx in resultToWrite)
			{
				var err = resultToWrite[idx];

				if (typeof err === 'string')
					contentToWrite += err;
				else if (typeof err === 'object' && err.issue_fehlertext)
					contentToWrite += err.issue_fehlertext;
				else
					contentToWrite += 'Unbekannter Fehler';

				contentToWrite += '</ br>';
			}
		}

		var spanid = boxid+"Span";
		var htmlToWrite = '<b>'+intro+'</b><br /><span class="text-danger" id="'+spanid+'">'+contentToWrite+'</span>';

		// hide menu to avoid scroll down
		DVUHMenu._toggleMenu(true);

		// write the results
		$("#"+boxid).html(htmlToWrite);
	},
	_writeResult: function(resultToWrite, boxid)
	{
		var colorClass = '';
		var intro = 'Abfrage ausgeführt, Antwort:';
		var textToWrite = "";

		// display infos
		if (resultToWrite.infos)
		{
			for (var i = 0; i < resultToWrite.infos.length; i++)
			{
				textToWrite += "<span class='text-success'>";
				textToWrite += resultToWrite.infos[i];
				textToWrite += "</span><br />";
			}
		}

		// display warnings
		if (resultToWrite.warnings)
		{
			for (var i = 0; i < resultToWrite.warnings.length; i++)
			{
				if (!FHC_AjaxClient.isError(resultToWrite.warnings[i]))
					continue;

				var warning = resultToWrite.warnings[i];

				textToWrite += "<span class='text-warning'>";
				if (typeof resultToWrite.warnings[i] === 'string')
					textToWrite += resultToWrite.warnings[i];
				else if (resultToWrite.warnings[i].issue_fehlertext)
					textToWrite += resultToWrite.warnings[i].issue_fehlertext;
				textToWrite += "</span><br />";
			}
		}

		// print the result
		var result = null
		if (resultToWrite.result)
		{
			result = resultToWrite.result;
		}
		else if (typeof resultToWrite == 'string')
		{
			result = resultToWrite;
		}

		// if multiple requests, display all requests
		if (jQuery.isArray(result))
		{
			for (var i = 0; i < result.length; i++)
			{
				textToWrite += "<b>Anfrage " + (i + 1) + "</b>:<br />";

				// display error if error for a request returned
				if (FHC_AjaxClient.isError(result[i]))
					textToWrite += FHC_AjaxClient.getError(result[i]);
				else // print xml result if no error
					textToWrite += DVUHMenu._printXmlTree(FHC_AjaxClient.getData(result[i]));

				textToWrite += "<br />";
			}
		}
		else // if result is no array, print the xml tree with result data
		{
			textToWrite += DVUHMenu._printXmlTree(result);
		}

		var spanid = boxid+"Span";
		var span = '<b>'+intro+'</b><br /><span'+colorClass+' id="'+spanid+'"></span>';

		// hide menu to avoid scroll down
		DVUHMenu._toggleMenu(true);

		// write the results
		$("#"+boxid).html(span);

		$("#"+spanid).html(textToWrite);
	},
	_writeSyncoutputBox: function()
	{
		if (!$("#dvuhOutputColumn").length)
		{
			// add output panel
			var columns = $("#dvuhPreviewContainer").length ? 6 : 12;
			$("#dvuhOutputContainer").append(
				'<div class="col-lg-'+columns+'" id="dvuhOutputColumn">'+
					'<div class="well well-sm wellminheight">'+
						'<div class="panel-title text-center">Output</div>'+
						'<div id="dvuhOutput" class="panel panel-body">'+
						'</div>'+
					'</div>'+
				'</div>'
			);
		}
	},
	_writePreviewBox: function()
	{
		$("#dvuhOutput").empty();

		if (!$("#dvuhPreviewContainer").length)
		{
			$("#dvuhOutputColumn").removeClass("col-lg-12").addClass("col-lg-6");

			// add preview output panel
			$("#dvuhOutputContainer").prepend(
				'<div class="col-lg-6" id="dvuhPreviewContainer">'+
					'<div class="well well-sm wellminheight">'+
						'<div class="panel-title text-center">Datenvorschau</div>'+
						'<div id="dvuhPreviewOutput" class="panel panel-body">'+
						'</div>'+
					'</div>'+
				'</div>'
			)

			DVUHMenu._writeSyncoutputBox();
		}
	},
	_toggleMenu: function(visible)
	{
		if (visible)
		{
			$("#menuContainer").hide();
			$("#toggleMenuText").text("Menü aufklappen");
			$("#toggleMenuCaret").removeClass().addClass("fa fa-caret-right")
		}
		else
		{
			$("#menuContainer").show();
			$("#toggleMenuText").text("Menü zuklappen");
			$("#toggleMenuCaret").removeClass().addClass("fa fa-caret-down")
		}
	},
	_clearGui: function()
	{
		$("#dvuhDatenvorschauButton").empty();
		$("#dvuhOutputContainer").empty();
	},
	_printXmlTree: function(xmlString)
	{
		if (typeof xmlString !== 'string')
			return '';

		var parseErrorHtml = "<span class='text-danger'>error when parsing xml string</span>";

		try
		{
			var xmlDoc = jQuery.parseXML(xmlString);
		}
		catch(e)
		{
			return parseErrorHtml;
		}

		if (!xmlDoc)
			return parseErrorHtml;

		var xml = $(xmlDoc.documentElement);

		var xmlResultNodeString = {xmlString: ''};

		DVUHMenu._printXmlNode(xml[0], xmlResultNodeString);

		return xmlResultNodeString.xmlString;
	},
	_printXmlNode(xmlNode, xmlResultNodeString, level = 0)
	{
		var margin = 18 * level;

		// get attributes
		var attrstr = '';
		if (xmlNode.attributes.length)
		{
			for (var ai = 0; ai < xmlNode.attributes.length; ai++)
			{
				var attr = xmlNode.attributes[ai];
				attrstr += ' '+attr.nodeName+'="'+attr.nodeValue+'"';
			}
		}

		// opening tag
		xmlResultNodeString.xmlString += '<span style="margin-left: '+margin+'px">&lt;' + xmlNode.nodeName + attrstr+'&gt;</span><br />';

		if (xmlNode.children.length)
		{
			++level;
			for (var i = 0; i < xmlNode.children.length; i++)
			{
				this._printXmlNode(xmlNode.children[i], xmlResultNodeString, level);
			}
		}
		else
		{
			var textmargin = margin + 10;
			xmlResultNodeString.xmlString += '<span style="margin-left: '+textmargin+'px">' + xmlNode.textContent + '</span><br />';
		}
		// closing tag
		xmlResultNodeString.xmlString += '<span style="margin-left: '+margin+'px">&lt;/' + xmlNode.nodeName + '&gt;</span><br />';
	},
	_setScrollToTop()
	{
		if ($(document).scrollTop() > 20)
			$("#scrollToTop").show();

		//scroll to top button
		$(window).scroll(function()
			{
				if ($(document).scrollTop() > 20)
					$("#scrollToTop").show();
				else
					$("#scrollToTop").hide();
			}
		);

		$("#scrollToTop").click(function()
			{
				$('html,body').animate({scrollTop: 0}, 250, 'linear');
			}
		)
	}
};
