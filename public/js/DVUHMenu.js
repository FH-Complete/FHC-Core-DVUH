/**
 * javascript file for displaying DVUH actions menu and executing DVUH calls
 */

$(document).ready(function()
	{
		$("#toggleMenu").click(
			function()
			{
				var visible = $("#menuContainer").is(":visible");
				DVUHMenu._toggleMenu(visible);
			}
		);

		$(".dvuhMenu li").click(
			function()
			{
				var id = $(this).prop('id');
				DVUHMenu.printForm(id);
			}
		);

		DVUHMenu._setScrollToTop();

		var hash = window.location.hash.substr(1);

		// get hash params from url and show appropriate form (if coming from external site)
		var result = hash.split('&').reduce(function (res, item) {
			var parts = item.split('=');
			res[parts[0]] = parts[1];
			return res;
		}, {});

		if (result.page.length > 0)
		{
			DVUHMenu.printForm(result.page, result);
		}
	}
);

var DVUHMenu = {
	printForm: function(action, params)
	{
		var html = '';
		var method = '';
		var writePreviewButton = false;

		switch(action)
		{
			case 'getMatrikelnummer':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'matrikelnummerPruefen')+'</h4>';
				html += DVUHMenu._getPreviewInputfieldHtml('matrnrDatenVorausfuellen');
				html += DVUHMenu._getTextfieldHtml('bpk', 'BPK', '', 30)
				+ DVUHMenu._getTextfieldHtml('svnr', 'SVNR', '', 10)
				+ DVUHMenu._getTextfieldHtml('ekz', FHC_PhrasesLib.t('dvuh', 'ersatzkennzeichen'), '', 10)
				+ DVUHMenu._getTextfieldHtml('vorname', FHC_PhrasesLib.t('dvuh', 'vorname'), '', 64)
				+ DVUHMenu._getTextfieldHtml('nachname', FHC_PhrasesLib.t('dvuh', 'nachname'), '', 255)
				+ DVUHMenu._getTextfieldHtml('geburtsdatum', FHC_PhrasesLib.t('dvuh', 'geburtsdatum'), FHC_PhrasesLib.t('dvuh', 'datumFormatHinweis'), 10);
				method = 'get';
				if (typeof params !== 'undefined' && params.hasOwnProperty('person_id'))
					DVUHMenu.getPersonPrefillData(params.person_id, 'matrnrDatenVorausfuellen');
				break;
			case 'getMatrikelnummerReservierungen':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'reservierungenAnzeigen')+'</h4>';
				html += DVUHMenu._getStudienjahrRow();
				method = 'get';
				break;
			case 'getStammdaten':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'stammdatenAbfragen')+'</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
				+ DVUHMenu._getSemesterRow()
				method = 'get';
				break;
			case 'getKontostaende':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'kontostandAbfragen')+'</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('seit', FHC_PhrasesLib.t('dvuh', 'datenaenderungSeit'), FHC_PhrasesLib.t('dvuh', 'datumFormatHinweis')+', optional', 10)
				method = 'get';
				break;
			case 'getStudium':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'studiumsdatenAbfragen')+'</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
/*					+ DVUHMenu._getTextfieldHtml('studienkennung', 'Studienkennung', 'optional; Studiengesetz (1 Zeichen, typischerweise \'U\',\'H\' oder \'L\') ' +
						'+ 2. BE-Kennung 1 (2 Zeichen) + SKZ 1 (Kopfcode oder Studienkennzahl, 3 Ziffern) + SKZ 2 (z.B. Lehramt Fach 1, 3 Ziffern; optional) ' +
						'+ SKZ 3 (z.B. Lehramt Fach 2, 3 Ziffern; optional) + BE-Kennung 2 (2 Zeichen; optional).', 14)*/
				method = 'get';
				break;
			case 'getFullstudent':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'detaillierteStudiendatenAbfragen')+'</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getTextfieldHtml('semester', 'Studiensemester', 'optional, '+FHC_PhrasesLib.t('dvuh', 'semesterHinweis'), 5)
				method = 'get';
				break;
			case 'getBpk':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'bpkManuellErmitteln')+'</h4>';
				html += DVUHMenu._getPreviewInputfieldHtml('bpkDatenVorausfuellen', 'bpkDatenVorausfuellenVoll');
				html += DVUHMenu._getTextfieldHtml('vorname', FHC_PhrasesLib.t('dvuh', 'vorname'), '', 64)
					+ DVUHMenu._getTextfieldHtml('nachname', FHC_PhrasesLib.t('dvuh', 'nachname'), '', 255)
					+ DVUHMenu._getTextfieldHtml('geburtsdatum', FHC_PhrasesLib.t('dvuh', 'geburtsdatum'), FHC_PhrasesLib.t('dvuh', 'datumFormatHinweis'), 10)
					+ DVUHMenu._getTextfieldHtml('geschlecht', FHC_PhrasesLib.t('dvuh', 'geschlecht'), 'M/W/X, optional', 1)
					+ DVUHMenu._getTextfieldHtml('strasse', FHC_PhrasesLib.t('dvuh', 'strasse'), FHC_PhrasesLib.t('dvuh', 'heimatadresseOhneHausnrHinweis')+', optional', 255)
					+ DVUHMenu._getTextfieldHtml('plz', FHC_PhrasesLib.t('dvuh', 'plz'), 'optional', 15)
					+ DVUHMenu._getTextfieldHtml('geburtsland', FHC_PhrasesLib.t('dvuh', 'geburtsland'), 'optional', 15)
					+ DVUHMenu._getTextfieldHtml('akadgrad', FHC_PhrasesLib.t('dvuh', 'akadGradPre'), FHC_PhrasesLib.t('dvuh', 'vorNamen')+', optional', 255)
					+ DVUHMenu._getTextfieldHtml('akadnach', FHC_PhrasesLib.t('dvuh', 'akadGradPost'), FHC_PhrasesLib.t('dvuh', 'nachNamen')+', optional', 255)
					+ DVUHMenu._getTextfieldHtml('alternativname', FHC_PhrasesLib.t('dvuh', 'alternativname'), 'optional, '+FHC_PhrasesLib.t('dvuh', 'nachnameVorNamenswechsel'), 255)
				method = 'get';
				if (typeof params !== 'undefined' && params.hasOwnProperty('person_id'))
					DVUHMenu.getPersonPrefillData(params.person_id, 'bpkDatenVorausfuellen');
				break;
			case 'getBpkByPersonId':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'bpkErmitteln')+'</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID');
				method = 'get';
				break;
			case 'getPruefungsaktivitaeten':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'pruefungsaktivitaetenAbfragen')+'</h4>';
				html += DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('matrikelnummer', FHC_PhrasesLib.t('dvuh', 'matrikelnummer'), 'optional', 8)
				method = 'get';
				break;
			case 'reserveMatrikelnummer':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'matrikelnummerReservieren')+'</h4>';
				html += DVUHMenu._getStudienjahrRow();
				method = 'post';
				break;
			case 'postMatrikelkorrektur':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'matrikelnummerKorrigieren')+'</h4>';
				html += DVUHMenu._getTextfieldHtml('matrikelnummer', FHC_PhrasesLib.t('dvuh', 'neueMatrikelnummer'), '', 8)
					+ DVUHMenu._getTextfieldHtml('matrikelalt', FHC_PhrasesLib.t('dvuh', 'alteMatrikelnummer'), '', 8)
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				break;
			case 'postMasterData':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'stammdatenMelden')+'</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postPayment':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'zahlungseingangMelden')+'</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postStudium':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'studiumsdatenMelden')+'</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('prestudent_id', 'PrestudentID', 'optional')
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postErnpmeldung':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'ernpMeldungDurchführen')+'</h4>';
				html += '<b>'+FHC_PhrasesLib.t('dvuh', 'ernpWarnungErsterTeil')+'<br />'+FHC_PhrasesLib.t('dvuh', 'ernpWarnungZweiterTeil')+'</b><br /><br />';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getTextfieldHtml('ausgabedatum', FHC_PhrasesLib.t('dvuh', 'ausgabedatum'), FHC_PhrasesLib.t('dvuh', 'datumFormatHinweis'), 10)
					+ DVUHMenu._getTextfieldHtml('ausstellBehoerde', FHC_PhrasesLib.t('dvuh', 'ausstellbehoerde'), '', 40)
					+ DVUHMenu._getTextfieldHtml('ausstellland', FHC_PhrasesLib.t('dvuh', 'ausstellland'), FHC_PhrasesLib.t('dvuh', 'ausstellandHinweis'), 3)
					+ DVUHMenu._getTextfieldHtml('dokumentnr', FHC_PhrasesLib.t('dvuh', 'dokumentnr'), FHC_PhrasesLib.t('dvuh', 'anzahlStellen'), 255)
					+ DVUHMenu._getDropdownHtml('dokumenttyp', FHC_PhrasesLib.t('dvuh', 'dokumenttyp'), {'REISEP': FHC_PhrasesLib.t('dvuh', 'reisepass'), 'PERSAUSW': FHC_PhrasesLib.t('dvuh', 'personalausweis')}, 'REISEP')
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postEkzanfordern':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'ekzAnfordern')+'</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getTextfieldHtml('forcierungskey', 'Forcierungskey', 'Optional, '+FHC_PhrasesLib.t('dvuh', 'forcierungskeyBeschreibung'), 255) // TODO which length?
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postPruefungsaktivitaeten':
				html = '<h4>'+FHC_PhrasesLib.t('dvuh', 'pruefungsaktivitaetenMelden')+'</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				writePreviewButton = true;
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
			$("#dvuhDatenvorschauButton").html('<button class="btn btn-default" id="datenVorschau">'+FHC_PhrasesLib.t('dvuh', 'zuSendendeDatenAnzeigen')+'</button>');

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
		$("#dvuhAbsendenButton").html('<button class="btn btn-default" id="dvuhAbsenden">'+FHC_PhrasesLib.t('dvuh', 'absenden')+'</button>');

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
					DVUHMenu._writeResult(FHC_PhrasesLib.t('dvuh', 'fehlerBeimAusfuellen'), 'dvuhOutput', 'error');
				}
			}
		);
	},
	sendForm: function(action, method, preview)
	{
		var url = FHC_JS_DATA_STORAGE_OBJECT.called_path + '/'+action;
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
			DVUHMenu._writeResult(FHC_PhrasesLib.t('dvuh', 'fehlerBeimAufruf') + " " + action, boxid, 'error');
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
	_getMatrikelnummerRow: function()
	{
		return DVUHMenu._getTextfieldHtml('matrikelnummer', FHC_PhrasesLib.t('dvuh', 'matrikelnummer'), '', 8)
	},
	_getStudienjahrRow: function()
	{
		return DVUHMenu._getTextfieldHtml('studienjahr', FHC_PhrasesLib.t('dvuh', 'studienjahr'), FHC_PhrasesLib.t('dvuh', 'studienjahrBeschreibung'), 4);
	},
	_getSemesterRow: function()
	{
		return DVUHMenu._getTextfieldHtml('semester', FHC_PhrasesLib.t('dvuh', 'studiensemester'), FHC_PhrasesLib.t('dvuh', 'studiensemesterBeschreibung'), 5)
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
				var selected = selectedValue === value ? ' selected' : '';
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
									FHC_PhrasesLib.t('dvuh', 'vorausfuellen') +
								'</button>' +
							'</span>' +
						'</div>' +
					'</div>';

		if (typeof secondButtonId !== 'undefined')
		{
			html += '<div class="col-lg-5">'+
						'<button class="btn btn-default" type="button" id="'+secondButtonId+'">' +
							FHC_PhrasesLib.t('dvuh', 'vorausfuellenInklOptional') +
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
	_writeResult: function(text, boxid, type)
	{
		var colorClass = '';
		var intro = FHC_PhrasesLib.t('dvuh', 'abfrageAusgefuehrt')+':';
		var textToWrite = "";
		var isError = false;

		if (type == 'error')
		{
			colorClass = ' class="text-danger"';
			intro = FHC_PhrasesLib.t('dvuh', 'fehlerAufgetreten')+':';
			isError = true;
			textToWrite = text;
		}
		else
		{

			if (text.infos)
			{
				for (var i = 0; i < text.infos.length; i++)
				{
					textToWrite += "<span class='text-success'>";
					textToWrite += text.infos[i];
					textToWrite += "</span><br />";
				}
			}

			if (text.warnings)
			{
				for (var i = 0; i < text.warnings.length; i++)
				{
					if (!FHC_AjaxClient.isError(text.warnings[i]))
						continue;

					textToWrite += "<span class='text-warning'>";
					textToWrite += FHC_AjaxClient.getError(text.warnings[i]);
					textToWrite += "</span><br />";
				}
			}

			var result = null
			if (text.result)
			{
				result = text.result;
			}
			else if (typeof text == 'string')
			{
				result = text;
			}

			if (jQuery.isArray(result))
			{
				for (var i = 0; i < result.length; i++)
				{
					textToWrite += "<b>"+FHC_PhrasesLib.t('dvuh', 'anfrage')+" " + (i + 1) + "</b>:<br />";

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
						'<div class="panel-title text-center">'+FHC_PhrasesLib.t('dvuh', 'anfrage')+'</div>'+
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
			$("#toggleMenuText").text(FHC_PhrasesLib.t('dvuh', 'menueAufklappen'));
			$("#toggleMenuCaret").removeClass().addClass("fa fa-caret-right")
		}
		else
		{
			$("#menuContainer").show();
			$("#toggleMenuText").text(FHC_PhrasesLib.t('dvuh', 'menueZuklappen'));
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

		var parseErrorHtml = "<span class='text-danger'>"+FHC_PhrasesLib.t('dvuh', 'fehlerParseXmlString')+"</span>";

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
