/**
 * javascript file for gathering functions used by multiple DVUH JS files.
 */

var DVUHLib = {
	getPastDate: function(daysInPast)
	{
		var pastDate = new Date(Date.now() - daysInPast * 24 * 60 * 60 * 1000);
		var day = DVUHLib._pad(pastDate.getDate());
		var month = DVUHLib._pad(pastDate.getMonth()+1);
		var year = pastDate.getFullYear();
		return day + '.' + month + '.' + year;
	},
	_pad: function(number)
	{
		return ('00' + number).substr(-2, 2);
	}
};
