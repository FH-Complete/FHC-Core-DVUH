/**
 * javascript file for handling bpkdetails functionality
 */

$(document).ready(function()
	{
		let person_id = $('#hiddenpersonid').val();

		// edit and save bpk field
		$("#editBpk").click(
			function()
			{
				let bpk = $("#bpkFieldValue").text();

				$("#bpkField").html(
					'<input type="text" class="form-control inline-inputfield" id="bpkInputField" value="'+bpk+'">' +
					'&nbsp;<i class="fa fa-check text-success" id="confirmBpkEdit">'
				);

				$("#confirmBpkEdit").click(
					function()
					{
						BpkDetails.saveBpk(person_id, $("#bpkInputField").val());
					}
				)
			}
		);

		// init checking of bpk combinations
		$("#startBpkCheck").click(
			function()
			{
				BpkDetails.checkBpkCombinations(person_id);
			}
		)

		// link for displaying all name combinations
		$("#showAllCombinations").click(
			function()
			{
				BpkDetails.getAllNameCombinations(person_id);
			}
		)
	}
);

var BpkDetails = {
	checkBpkCombinations: function(person_id)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/checkBpkCombinations',
			{"person_id": person_id},
			{
				successCallback: function(data)
				{
					if (FHC_AjaxClient.isError(data))
					{
						FHC_DialogLib.alertError("Fehler bei bPK Anfrage");
					}
					else if (FHC_AjaxClient.hasData(data))
					{
						let bpkData = FHC_AjaxClient.getData(data);

						$("#bpkBoxes").empty();
						for (let i = 0; i < bpkData.length; i++)
						{
							let bpkResponseData = bpkData[i]['responseData'];
							//let bpkRequestData = bpkData[i]['requestData'];
							BpkDetails._printBpkBox(bpkResponseData.bpk, bpkData[i], person_id, i);
						}
					}
					else
						FHC_DialogLib.alertInfo("Keine bPK gefunden");
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Fehler bei bPK Anfrage");
				}
			}
		);
	},
	getAllNameCombinations(person_id)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/getAllNameCombinations',
			{"person_id": person_id},
			{
				successCallback: function(data)
				{
					if (FHC_AjaxClient.isError(data))
					{
						FHC_DialogLib.alertError("Fehler bei Anfrage");
					}
					else if (FHC_AjaxClient.hasData(data))
					{
						let combinationsData = FHC_AjaxClient.getData(data);

						$("#bpkBoxes").empty();
						//let combinationsHtml = '';
						let combinationsHtml = '<div class="table-responsive">' +
													'<table class="table table-condensed table-bordered">';

						for (let i = 0; i < combinationsData.length; i++)
						{
							if ((i) % 3 === 0)
								combinationsHtml += '<tr>';
							combinationsHtml +=				'<td>';

							let first = true;
							for (let nameProp in combinationsData[i])
							{
								if (!first)
									combinationsHtml += ',&nbsp;';

								combinationsHtml += '<strong>'+nameProp+':</strong>&nbsp;'+combinationsData[i][nameProp];
								first = false;
							}

							combinationsHtml +=				'</td>';

							if ((i + 1) % 3 === 0)
								combinationsHtml += '</tr>';
						}

						combinationsHtml += 	'</table>' +
											'</div>';

						$("#bpkBoxes").html(combinationsHtml);
					}
					else
						FHC_DialogLib.alertInfo("Keine Kombinationen gefunden");
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Fehler bei Anfrage");
				}
			}
		);
	},
	saveBpk: function(person_id, bpk)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/saveBpk',
			{
				"person_id": person_id,
				"bpk": bpk
			},
			{
				successCallback: function(data)
				{
					if (FHC_AjaxClient.isError(data))
					{
						FHC_DialogLib.alertError("Fehler beim bPK Speichern: " + FHC_AjaxClient.getError(data));
					}

					if (FHC_AjaxClient.hasData(data))
					{
						window.location.reload();
					}
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("Fehler beim Speichern des bPK!");
				}
			}
		);
	},
	_printBpkBox: function(bpk, bpkData, person_id, idx)
	{
		let responseData = bpkData.responseData;
		let requestData = bpkData.requestData;
		let responsePersonData = responseData.personData;

		let numberPersonsFound = responseData.numberPersonsFound;
		let heading = 'keine Bpk gefunden';

		if (numberPersonsFound > 1)
			heading = 'Mehrere Personentreffer';
		else if(numberPersonsFound === 1 && bpk != null)
			heading = bpk;

		let boxhtml = '<div class="panel panel-default">' +
							'<div class="panel-heading text-center">' + heading + '</div>' +
								'<div class="panel-body">'+
									'<div class="row">';

		let showPersonResults = responsePersonData.length > 1;
		if (showPersonResults)
		{
			boxhtml += '<div class="col-lg-6">';

			for (let i = 0; i < responsePersonData.length; i++)
			{
				let persData = responsePersonData[i];

				boxhtml += '<div class="table-responsive">' +
								'<table class="table table-condensed table-bordered">' +
									'<colgroup>' +
										'<col style="width: 30%;">' +
										'<col style="width: 70%;">' +
									'</colgroup>' +
									'<thead>' +
										'<tr>' +
											'<th colspan="2" class="text-center">Gefunden:</th>' +
										'</tr>' +
									'</thead>' +
									'<tbody>';

				for (let fieldname in persData)
				{
					boxhtml += '<tr>' +
									'<td>' + fieldname + '</td>' +
									'<td>' + persData[fieldname] + '</td>' +
								'</tr>';
				}

				boxhtml += 			'</tbody>' +
								'</table>' +
							'</div>';
			}
			boxhtml += '</div>'; // first column end
		}

		let width = showPersonResults ? 6 : 12;
		boxhtml += '<div class="col-lg-'+width+'">' +
						'<div class="table-responsive">' +
							'<table class="table table-condensed table-bordered">' +
								'<thead>' +
									'<tr>' +
										'<th class="text-center">Für folgende Anfragen:</th>' +
									'</tr>' +
								'</thead>' +
								'<tbody>';

		for (let idx in requestData)
		{
			let requestObj = requestData[idx];
			let requestStr = '';

			for (let reqProp in requestObj)
			{
				requestStr += '<strong>'+reqProp+':</strong>&nbsp;'+requestObj[reqProp]+' | ';
			}

			boxhtml += '<tr><td>';
			boxhtml += requestStr;
			boxhtml += '</td></tr>';
		}

		boxhtml +=			'</tbody>' +
						'</table>' +
					'</div>' + // second column end
				'</div>'; // row end


		if (bpk != null)
		{
			boxhtml += '<div class="row">' +
							'<div class="col-lg-12 text-center">' +
								'<button class="btn btn-default" id="saveBpk_'+idx+'">bPK übernehmen</button>' +
							'</div>';
						'</div>';
		}

		boxhtml += 			'</div>' + // panel body
						'</div>'; // panel

		$("#bpkBoxes").append(boxhtml);

		if (bpk != null)
		{
			$("#saveBpk_" + idx).click(
				function()
				{
					BpkDetails.saveBpk(person_id, bpk);
				}
			)
		}
	}
};
