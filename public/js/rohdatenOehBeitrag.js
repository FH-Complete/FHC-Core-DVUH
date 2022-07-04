/**
 * javascript file for showing Oehbeitragsliste
 */

$(document).ready(function()
	{
		// get default values for from and to dates
		var defaultDateFrom = DVUHLib.getPastDate(7);
		var defaultDateTo = DVUHLib.getPastDate(0);

		// set datepickers
		$("#dateFrom").datepicker({
			"dateFormat": "dd.mm.yy"
		}).val(defaultDateFrom);

		$("#dateTo").datepicker({
			"dateFormat": "dd.mm.yy"
		}).val(defaultDateTo);

		// set event for showing data on site
		$("#showOehbeitraege").click(
			function()
			{
				var dateFrom = $("#dateFrom").val();
				var dateTo = $("#dateTo").val();
				RohdatenOehBeitrag.showRohdatenOehbeitrag(dateFrom, dateTo);
			}
		);

		// download Oehbeitrag file in window
		$("#downloadOehbeitraege").click(
			function()
			{
				var dateFrom = $("#dateFrom").val();
				var dateTo = $("#dateTo").val();
				var method_call = 'downloadRohdatenOehbeitrag?dateFrom='+encodeURIComponent(dateFrom)+'&dateTo='+encodeURIComponent(dateTo);
				if (typeof FHC_JS_DATA_STORAGE_OBJECT !== "undefined")
					window.location = FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + "/" + FHC_JS_DATA_STORAGE_OBJECT.called_path+"/"+method_call;
				else
					window.location = './RohdatenOehBeitrag/'+method_call;
			}
		);
	}
);

var RohdatenOehBeitrag = {
	showRohdatenOehbeitrag: function(dateFrom, dateTo)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/showRohdatenOehbeitrag',
			{"dateFrom": dateFrom, "dateTo": dateTo},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.isError(data))
						$("#oehbeitragsliste").html('<br /><span class="text-danger">'+FHC_AjaxClient.getError(data)+'</span>');
					else if (FHC_AjaxClient.hasData(data))
					{
						// on success, show Oehbeitragsliste in a table
						var oehbeitragText = FHC_AjaxClient.getData(data);

						var tblString = "<table class='table table-bordered table-condensed' id='oehbeitragslisteTable'>";
						var lines = oehbeitragText.split('\n');

						// table header
						var headerVals = lines[0].split(';');
						var maxValsNumber = headerVals.length;

						tblString += "<thead>";
						tblString += "<tr>";
						for (var h = 0; h < maxValsNumber; h++)
						{
							tblString += "<th>"+headerVals[h]+"</th>";
						}
						tblString += "</tr>";
						tblString += "</thead>";

						tblString += "<tbody>";
						// table Body, -1 for last empty line
						for (var i = 1; i < lines.length - 1; i++)
						{
							tblString += "<tr>";
							var values = lines[i].split(';');
							for (var j = 0; j < maxValsNumber; j++)
							{
								tblString += "<td>"+values[j]+"</td>";
							}
							tblString += "</tr>";
						}
						tblString += "</tbody>";
						tblString += "</table>";

						$("#oehbeitragsliste").html(tblString);
						Tablesort.addTablesorter(
							// tablesorter: add filter and zebra widgets show filters beggining with 2 entries
							"oehbeitragslisteTable", [], ["filter", "zebra"], 2
						)
					}
					else
						$("#oehbeitragsliste").html("<br />Keine Daten vorhanden");

				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Error when getting Oehbeiträge");
				}
			}
		);
	}
};
