/**
 * javascript file for showing Oehbeitragsliste
 */

$(document).ready(function()
	{
		var defaultDateFrom = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
		var day = RohdatenOehBeitrag._pad(defaultDateFrom.getDate());
		var month = RohdatenOehBeitrag._pad(defaultDateFrom.getMonth()+1);
		var year = defaultDateFrom.getFullYear();
		defaultDateFrom = year + '-' + month + '-' + day;

		$("#dateFrom").datepicker({
			"dateFormat": "yy-mm-dd"
		}).val(defaultDateFrom);

		$("#dateTo").datepicker({
			"dateFormat": "yy-mm-dd"
		}).val(defaultDateFrom);

		$("#showOehbeitraege").click(
			function()
			{
				var dateFrom = $("#dateFrom").val();
				var dateTo = $("#dateTo").val();
				RohdatenOehBeitrag.showRohdatenOehbeitrag(dateFrom, dateTo);
			}
		);

		$("#downloadOehbeitraege").click(
			function()
			{
				var dateFrom = $("#dateFrom").val();
				var dateTo = $("#dateTo").val();
				if (typeof FHC_JS_DATA_STORAGE_OBJECT !== "undefined")
				{
					var method_call = 'downloadRohdatenOehbeitrag?dateFrom='+encodeURIComponent(dateFrom)+'&dateTo='+encodeURIComponent(dateTo);
					window.location = FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + "/" + FHC_JS_DATA_STORAGE_OBJECT.called_path+"/"+method_call;
				}
				else
					FHC_DialogLib.alertError("FHC_JS_DATA_STORAGE_OBJECT not defined");
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
					console.log(data);
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Error when getting Oehbeiträge");
				}
			}
		);
	},
	_pad: function(number)
	{
		return ('00' + number).substr(-2, 2);
	}
};
