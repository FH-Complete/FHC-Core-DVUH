/**
 * javascript file for displaying DVUH actions menu and executing DVUH calls
 */

$(document).ready(function()
	{
		$("#dvuhMenu li").click(
			function()
			{
				var id = $(this).prop('id');
				DVUHMenu._printForm(id);
			}
		);
	}
);

var DVUHMenu = {
	_printForm: function(action)
	{
		var html = '';
		var method = '';
		var writePreviewButton = false;

		switch(action)
		{
			case 'getMatrikelnummer':
				html = '<h4>Matrikelnummer pr&uuml;fen</h4>';
				html += DVUHMenu._getTextfieldHtml('bpk', 'BPK')
				+ DVUHMenu._getTextfieldHtml('svnr', 'SVNR', '', 10)
				+ DVUHMenu._getTextfieldHtml('ekz', 'Ersatzkennzeichen', '', 10)
				+ DVUHMenu._getTextfieldHtml('vorname', 'Vorname', '', 64)
				+ DVUHMenu._getTextfieldHtml('nachname', 'Nachname', '', 255)
				+ DVUHMenu._getTextfieldHtml('geburtsdatum', 'Geburtsdatum', 'Format: YYYY-MM-DD', 10);
				method = 'get';
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
				html = '<h4>Konstostand abfragen</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('seit', 'Dateineingang seit', 'Format: YYYY-MM-DD, optional', 10)
				method = 'get';
				break;
			case 'getStudium':
				html = '<h4>Studiumsdaten abfragen</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('studienkennung', 'Studienkennung', 'Studiengesetz (1 Zeichen, typischerweise \'U\',\'H\' oder \'L\') ' +
						'+ 2. BE-Kennung 1 (2 Zeichen) + SKZ 1 (Kopfcode oder Studienkennzahl, 3 Ziffern) + SKZ 2 (z.B. Lehramt Fach 1, 3 Ziffern; optional) ' +
						'+ SKZ 3 (z.B. Lehramt Fach 2, 3 Ziffern; optional) + BE-Kennung 2 (2 Zeichen; optional).', 14)
				method = 'get';
				break;
			case 'getFullstudent':
				html = '<h4>Detaillierte Studiendaten abfragen</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
				method = 'get';
				break;
			case 'getBpk':
				html = '<h4>BPK ermitteln</h4>';
				html += DVUHMenu._getTextfieldHtml('vorname', 'Vorname', '', 64)
					+ DVUHMenu._getTextfieldHtml('nachname', 'Nachname', '', 255)
					+ DVUHMenu._getTextfieldHtml('geburtsdatum', 'Geburtsdatum', 'Format: YYYY-MM-DD', 10)
					+ DVUHMenu._getTextfieldHtml('geschlecht', 'Geschlecht', 'M/W/X, optional', 1)
					+ DVUHMenu._getTextfieldHtml('strasse', 'Strasse', 'ohne Hausnummer, optional', 255)
					+ DVUHMenu._getTextfieldHtml('plz', 'PLZ', 'optional', 15)
					+ DVUHMenu._getTextfieldHtml('geburtsland', 'Geburtsland', 'optional', 15)
					+ DVUHMenu._getTextfieldHtml('akadgrad', 'Akademischer Grad Pre', 'vor dem Namen, optional', 255)
					+ DVUHMenu._getTextfieldHtml('akadnach', 'Akademischer Grad Post', 'nach dem Namen, optional', 255)
				method = 'get';
				break;
			case 'reserveMatrikelnummer':
				html = '<h4>Matrikelnummer reservieren</h4>';
				html += DVUHMenu._getStudienjahrRow();
				method = 'post';
				break;
			case 'correctMatrikelnummer':
				html = '<h4>Matrikelnummer korrigieren</h4>';
				html += DVUHMenu._getTextfieldHtml('matrikelnummer', 'Neue Matrikelnummer', '', 8)
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('matrikelalt', 'Alte Matrikelnummer', '', 8)
				method = 'post';
				break;
			case 'postStammdaten':
				html = '<h4>Stammdaten und Matrikelnummer melden (ohne Vorschreibung)</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
				method = 'post';
				writePreviewButton = true;
				break;
			case 'postStammdatenVorschreibung':
				html = '<h4>Stammdaten und Matrikelnummer melden (mit Vorschreibung)</h4>';
				html += DVUHMenu._getTextfieldHtml('person_id', 'PersonID')
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('oehbeitrag', 'ÖH-Beitrag', 'In Cent', 4)
					+ DVUHMenu._getTextfieldHtml('studiengebuehr', 'Studiengebühr', 'In Cent', 10)
					+ DVUHMenu._getTextfieldHtml('valutadatum', 'Valutadatum', 'Format: YYYY-MM-DD', 10)
					+ DVUHMenu._getTextfieldHtml('valutadatumnachfrist', 'Valutadatum Nachfrist', 'Format: YYYY-MM-DD', 10)
				method = 'post';
				action = 'postStammdaten';
				writePreviewButton = true;
				break;
			case 'postZahlung':
				html = '<h4>Zahlungseingang melden</h4>';
				html += DVUHMenu._getMatrikelnummerRow()
					+ DVUHMenu._getSemesterRow()
					+ DVUHMenu._getTextfieldHtml('buchungsdatum', 'Buchungsdatum', 'Format: YYYY-MM-DD', 10)
					+ DVUHMenu._getTextfieldHtml('referenznummer', 'Referenznummer', 'numerisch', 60)
					+ DVUHMenu._getTextfieldHtml('zahlungsart', 'Zahlungsart', '1 - Bankomatzahlung, 2 - Quickzahlung', 1)
					+ DVUHMenu._getTextfieldHtml('centbetrag', 'Centbetrag', 'In Cent', 10)
				method = 'post';
				break;
			case 'postStudium':
				html = '<h4>Studiumsdaten melden</h4>';
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
					'<label class="col-lg-5 control-label" style="text-align: left;" for="'+name+'">'+hint+'</label>'+
				'</div>';
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
		var intro = 'Abfrage ausgeführt, Antwort:';
		var textToWrite = text;
		var isError = false;


		if (type == 'error')
		{
			colorClass = ' class="text-danger"';
			intro = 'Fehler aufgetreten, Antwort:';
			isError = true;
		}
		else
			textToWrite = DVUHMenu._printXmlTree(text);

		var spanid = boxid+"Span";
		var span = '<b>'+intro+'</b><br /><span'+colorClass+' id="'+spanid+'"></span>';

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
	_clearGui: function()
	{
		$("#dvuhDatenvorschauButton").empty();
		$("#dvuhOutputContainer").empty();
	},
	_printXmlTree: function(xmlString)
	{
		var xmlDoc = jQuery.parseXML(xmlString);
		var xml = $( xmlDoc.documentElement );

		var xmlResultNodeString = {xmlString: ''};

		DVUHMenu._printXmlNode(xml[0], xmlResultNodeString);

		return xmlResultNodeString.xmlString;
	},
	_printXmlNode(xmlNode, xmlResultNodeString, level = 0)
	{
		var margin = 10 * level;
		xmlResultNodeString.xmlString += '<span style="margin-left: '+margin+'px">&lt;' + xmlNode.nodeName + '&gt;</span><br />';
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
			xmlResultNodeString.xmlString += '<span style="margin-left: ' + textmargin + 'px">' + xmlNode.textContent + '</span><br />';
		}
		xmlResultNodeString.xmlString += '<span style="margin-left: '+margin+'px">&lt;/' + xmlNode.nodeName + '&gt;</span><br />';
	}
};
