/**
 * javascript file for showing DVUH feeds
 */

$(document).ready(function()
	{
		var defaultErstelltSeit = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
		var day = FeedOverview._pad(defaultErstelltSeit.getDate());
		var month = FeedOverview._pad(defaultErstelltSeit.getMonth()+1);
		var year = defaultErstelltSeit.getFullYear();
		defaultErstelltSeit = year + '-' + month + '-' + day;

		$("#erstelltSeit").datepicker({
			"dateFormat": "yy-mm-dd"
		}).val(defaultErstelltSeit);

		$("#showfeeds").click(
			function()
			{
				var erstelltSeit = $("#erstelltSeit").val();
				var matrikelnummer = $("#matrikelnummer").val();
				FeedOverview.getFeedEntries(erstelltSeit, matrikelnummer);
			}
		);
	}
);

var FeedOverview = {

	getFeedEntries: function(erstelltSeit, matrikelnummer)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/getFeedEntries',
			{"erstelltSeit": erstelltSeit, "matrikelnummer": matrikelnummer},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.isSuccess(data))
					{
						if (FHC_AjaxClient.hasData(data))
						{
							FeedOverview._printFeedEntries(FHC_AjaxClient.getData(data));
						}
						else
							$("#feedlist").html('No new feeds.');
					}
					else
					{
						FHC_DialogLib.alertError("Error occured: " + FHC_AjaxClient.getError(data));
					}
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Error when getting feed entries");
				}
			}
		);
	},
	_printFeedEntries: function(feedentries)
	{
		var feedentrstr = '';

		for (var i = 0; i < feedentries.length; i++)
		{
			var entry = feedentries[i];

			var props = ["id", "author", "published", "updated", "content"]

			feedentrstr +=
				'<div class="panel panel-default">' +
					'<div class="panel-heading">' +
						entry.title +
					'</div>' +
					'<div class="panel-body">' +
						'<div class="table-responsive">' +
							'<table class="table table-bordered table-condensed">';

			for (var j = 0; j < props.length; j++)
			{
				feedentrstr += FeedOverview._getFeedEntryStr(props[j], entry[props[j]]);
			}

			feedentrstr +=
							'</table>' +
						'</div>' +
					'</div>' +
				'</div>';
		}

		$("#feedlist").html(feedentrstr);

	},
	_getFeedEntryStr: function(name, value)
	{
		return "<tr><td>" + name + "</td><td>" + value + "</td></tr>";
	},
	_pad: function(number)
	{
		return ('00' + number).substr(-2, 2);
	}
};
