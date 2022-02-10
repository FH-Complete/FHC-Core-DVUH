/**
 * javascript file for displaying DVUH actions menu and executing DVUH calls
 */

$(document).ready(function()
	{
		// show / hide menu
		$("#toggleMenu").click(
			function()
			{
				var visible = $("#menuContainer").is(":visible");
				DVUHMenu._toggleMenu(visible);
			}
		);

		// print form when clicking on menu entry
		$(".dvuhMenu li").click(
			function()
			{
				var id = $(this).prop('id');
				DVUHMenu.printFormWithFhcData(id);
			}
		);

		// scroll to top button
		DVUHMenu._setScrollToTop();

		// get hash params from url and show appropriate form (if coming from external site)
		var hash = window.location.hash.substr(1);

		var result = hash.split('&').reduce(function (res, item) {
			var parts = item.split('=');
			res[parts[0]] = parts[1];
			return res;
		}, {});

		if (result.page && result.page.length > 0)
		{
			DVUHMenu.printFormWithFhcData(result.page, result);
		}
	}
);

var DVUHMenu = {
	fhcData: null,
	actionsRequireFhcData: ['postErnpmeldung'],
	printFormWithFhcData: function(action, params)
	{
		if ($.inArray(action, DVUHMenu.actionsRequireFhcData) !== -1)
		{
			DVUHMenu.getDvuhMenuData(
				function()
				{
					DVUHMenu.printForm(action, params);
				}
			);
		}
		else
		{
			DVUHMenu.printForm(action, params);
		}
	},
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
					+ DVUHMenu._getTextfieldHtml('semester', 'Studiensemester', 'optional, z.B. 2016S für Sommer-, 2016W für Wintersemester 2016', 5)
				method = 'get';
				break;
			case 'getBpk':
				html = '<h4>bPK ermitteln (manuell)</h4>';
				html += DVUHMenu._getPreviewInputfieldHtml('bpkDatenVorausfuellen', 'bpkDatenVorausfuellenVoll');
				html += DVUHMenu._getTextfieldHtml('vorname', 'Vorname', '', 64)
					+ DVUHMenu._getTextfieldHtml('nachname', 'Nachname', '', 255)
					+ DVUHMenu._getTextfieldHtml('geburtsdatum', 'Geburtsdatum', 'Format: DD.MM.YYYY oder YYYY-MM-DD', 10)
					+ DVUHMenu._getTextfieldHtml('geschlecht', 'Geschlecht', 'M/W/X, optional', 1)
					+ DVUHMenu._getTextfieldHtml('strasse', 'Strasse', 'der Heimatadresse, ohne Hausnummer, optional', 255)
					+ DVUHMenu._getTextfieldHtml('plz', 'PLZ', 'optional', 15)
					+ DVUHMenu._getTextfieldHtml('geburtsland', 'Geburtsland', 'optional', 15)
					+ DVUHMenu._getTextfieldHtml('akadgrad', 'Akademischer Grad Pre', 'vor dem Namen, optional', 255)
					+ DVUHMenu._getTextfieldHtml('akadnach', 'Akademischer Grad Post', 'nach dem Namen, optional', 255)
					+ DVUHMenu._getTextfieldHtml('alternativname', 'Alternativname', 'optional, Nachname vor Namenswechsel', 255)
				method = 'get';
				if (typeof params !== 'undefined' && params.hasOwnProperty('person_id'))
					DVUHMenu.getPersonPrefillData(params.person_id, 'bpkDatenVorausfuellen');
				break;
			case 'getBpkByPersonId':
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
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')/*DVUHMenu._getMatrikelnummerRow()*/
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

				var nationsDropdownValues = {};
				for (var idx in DVUHMenu.fhcData.nations)
				{
					var nation = DVUHMenu.fhcData.nations[idx];
					nationsDropdownValues[nation.nation_code] = nation.nation_text+" - "+nation.nation_code;
				}

				html = '<h4>ERnP-Meldung durchführen</h4>';
				html += '<b>HINWEIS: Die Eintragung ins ERnP (Ergänzungsregister für natürliche Personen) sollte nur dann durchgeführt werden, ' +
					'wenn für die Person keine bPK ermittelt werden kann.<br />Beim Punkt "bPK ermitteln" sollte dementsprechend keine bPK zurückgegeben werden. ' +
					'Ist ein aktueller oder früherer Wohnsitz in Österreich vorhanden, ist sicher ein bPK vorhanden.</b><br /><br />';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
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
			case 'deletePruefungsaktivitaeten':
				html = '<h4>Prüfungsaktivitäten löschen</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
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

						if (buttonId == 'matrnrDatenVorausfuellen')
						{
							$("#bpk").val(prefillData.bpk);
							$("#svnr").val(prefillData.svnr);
							$("#ekz").val(prefillData.ersatzkennzeichen);
						}
						else if (buttonId == 'bpkDatenVorausfuellen')
						{
							$("#geschlecht").val('');
							$("#geburtsland").val('');
							$("#strasse").val('');
							$("#plz").val('');
							$("#akadgrad").val('');
							$("#akadnach").val('');
						}
						else if (buttonId == 'bpkDatenVorausfuellenVoll')
						{
							$("#geschlecht").val(prefillData.geschlecht);
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
					DVUHMenu._writeResult("Fehler beim Vorausfüllen", 'dvuhOutput', 'error');
				}
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
				DVUHMenu._writeResult(FHC_AjaxClient.getError(data), boxid, 'error');
			}
		}

		var errorCallback = function(jqXHR, textStatus, errorThrown)
		{
			DVUHMenu._writeResult("Error when calling " + action, boxid, 'error');
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

	/* additional "private" methods */
	_getMatrikelnummerRow: function()
	{
		return DVUHMenu._getTextfieldHtml('matrikelnummer', 'Matrikelnummer', '', 8)
	},
	_getStudienjahrRow: function()
	{
		return DVUHMenu._getTextfieldHtml('studienjahr', 'Studienjahr', 'zB 2016 (für WS2016 und SS2017)', 4);
	},
	_getSemesterRow: function()
	{
		return DVUHMenu._getTextfieldHtml('semester', 'Studiensemester', 'z.B. 2016S für Sommer-, 2016W für Wintersemester 2016', 5)
	},
	_getTextfieldHtml: function(name, title, hint, maxlength)
	{
		if (!hint)
			hint = '';

		if (!maxlength)
			maxlength = 15;

		return '<div class="form-group">' +
					'<label class="col-lg-2 control-label" for="'+name+'">'+title+'</label>'+
					'<div class="col-lg-5">'+
						'<input class="form-control" id="'+name+'" name="'+name+'" type="text" size="30" maxlength="'+maxlength+'">'+
					'</div>'+
					'<label class="col-lg-5 control-label form-hint" for="'+name+'">'+hint+'</label>'+
				'</div>';
	},
	_getDropdownHtml: function(name, title, values, selectedValue, hint)
	{
		if (!hint)
			hint = '';

		var html = '<div class="form-group">' +
					'<label class="col-lg-2 control-label" for="'+name+'">'+title+'</label>' +
					'<div class="col-lg-5">'+
					'<select class="form-control" name="'+name+'">';

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
	_writeResult: function(resultToWrite, boxid, type)
	{
		var colorClass = '';
		var intro = 'Abfrage ausgeführt, Antwort:';
		var textToWrite = "";
		var isError = false;

		if (type == 'error')
		{
			colorClass = ' class="text-danger"';
			intro = 'Fehler aufgetreten, Antwort:';
			isError = true;
			textToWrite = resultToWrite;
		}
		else
		{
			if (resultToWrite.infos)
			{
				for (var i = 0; i < resultToWrite.infos.length; i++)
				{
					textToWrite += "<span class='text-success'>";
					textToWrite += resultToWrite.infos[i];
					textToWrite += "</span><br />";
				}
			}

			if (resultToWrite.warnings)
			{
				for (var i = 0; i < resultToWrite.warnings.length; i++)
				{
					if (!FHC_AjaxClient.isError(resultToWrite.warnings[i]))
						continue;

					textToWrite += "<span class='text-warning'>";
					textToWrite += FHC_AjaxClient.getError(resultToWrite.warnings[i]);
					textToWrite += "</span><br />";
				}
			}

			var result = null
			if (resultToWrite.result)
			{
				result = resultToWrite.result;
			}
			else if (typeof resultToWrite == 'string')
			{
				result = resultToWrite;
			}

			if (jQuery.isArray(result))
			{
				for (var i = 0; i < result.length; i++)
				{
					textToWrite += "<b>Anfrage " + (i + 1) + "</b>:<br />";

					if (FHC_AjaxClient.isError(result[i]))
						textToWrite += FHC_AjaxClient.getError(result[i]);
					else
						textToWrite += DVUHMenu._printXmlTree(FHC_AjaxClient.getData(result[i]));

					textToWrite += "<br />";
				}
			}
			else
			{
				textToWrite += DVUHMenu._printXmlTree(result);
			}
		}

		var spanid = boxid+"Span";
		var span = '<b>'+intro+'</b><br /><span'+colorClass+' id="'+spanid+'"></span>';

		// hide menu to avoid scroll down
		DVUHMenu._toggleMenu(true);

		// write the results
		$("#"+boxid).html(span);

		if (isError)
			$("#"+spanid).text(textToWrite);
		else
			$("#"+spanid).html(textToWrite);
	},
	_writeSyncoutputBox: function()
	{
		if (!$("#dvuhOutputColumn").length)
		{
			var columns = $("#dvuhPreviewContainer").length ? 6 : 12;
			$("#dvuhOutputContainer").append(
				'<div class="col-lg-'+columns+'" id="dvuhOutputColumn">'+
					'<div class="well well-sm wellminheight">'+
						'<div class="panel-title text-center">Syncoutput</div>'+
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
