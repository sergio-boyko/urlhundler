<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
        <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <title>URL handler</title>
  </head>
  <body>
    <div class="py-5 container" style="border-left: solid #f8f9fa;border-right: solid #f8f9fa;">
    	<div class="col-md-12 order-md-1">
	    	<form id="formRedirect" action="#">
		    <div class="form-group">		  	
			  	<textarea class="form-control" name="urls" id="urls" placeholder="Set URL here..." required></textarea>		 	
		 	</div>
		 	<hr class="mb-4">
		 	<div class="form-group" style="margin: 0;text-align: center;">
		 		<button type="submit" name="submit" class="btn btn-primary btn-lg">Submit</button>
		 	</div>
		 	</form>
		</div>
		<div class="py-5 container invisible" id="error">
			<div class="alert alert-danger" role="alert">Check URL</div>
		</div>
		<div class="container invisible" id="resultBlock">
			<table id="dataBlock" class="table table-bordered table-striped">
				  <thead>
				    <tr>
				      <th scope="col">URL</th>
				      <th scope="col">Status Codes</th>
				      <th scope="col">Redirects</th>
				    </tr>
				  </thead>
				  <tbody></tbody>
				</table>
			<hr class="mb-4">
			<div style="margin: 0; text-align: center;">
				<button type="button" class="btn btn-light btn-lg" onclick="onCSV()">Get CSV</button>
			</div>			
		</div>
	</div>
	<script type="text/javascript">
		/**
		@namespace Converts JSON to CSV.
		Compress with: http://jscompress.com/
		*/
		(function (window) {
		    "use strict";
		    /**
		    Default constructor
		    */
		    var _CSV = function (JSONData) {
		        if (typeof JSONData === 'undefined')
		            return;

		        var csvData = typeof JSONData != 'object' ? JSON.parse(settings.JSONData) : JSONData,
		            csvHeaders,
		            csvEncoding = 'data:text/csv;charset=utf-8,',
		            csvOutput = "",
		            csvRows = [],
		            BREAK = '\r\n',
		            DELIMITER = ';',
					FILENAME = "export.csv";

		        // Get and Write the headers
		        csvHeaders = Object.keys(csvData[0]);
		        csvOutput += csvHeaders.join(DELIMITER) + BREAK;

		        for (var i = 0; i < csvData.length; i++) {
		            var rowElements = [];
		            for(var k = 0; k < csvHeaders.length; k++) {
		                rowElements.push(csvData[i][csvHeaders[k]]);
		            } // Write the row array based on the headers
		            csvRows.push(rowElements.join(DELIMITER));
		        }

		        csvOutput += csvRows.join(BREAK);

		        // Initiate Download
		        var a = document.createElement("a");

		        if (navigator.msSaveBlob) { // IE10
		            navigator.msSaveBlob(new Blob([csvOutput], { type: "text/csv" }), FILENAME);
		        } else if ('download' in a) { //html5 A[download]
		            a.href = csvEncoding + encodeURIComponent(csvOutput);
		            a.download = FILENAME;
		            document.body.appendChild(a);
		            setTimeout(function() {
		                a.click();
		                document.body.removeChild(a);
		            }, 66);
		        } else if (document.execCommand) { // Other version of IE
		            var oWin = window.open("about:blank", "_blank");
		            oWin.document.write(csvOutput);
		            oWin.document.close();
		            oWin.document.execCommand('SaveAs', true, FILENAME);
		            oWin.close();
		        } else {
		            alert("Support for your specific browser hasn't been created yet, please check back later.");
		        }
		    };

		    window.CSVExport = _CSV;

		})(window);
	</script>
    <script type="text/javascript"> 
    	/**
    	* Validate and submit a form
    	*/   	
    	$(function () {
    		'use strict';
	    	$( 'form' ).on( 'submit', function ( e ) {
				e.preventDefault();
				onSubmit();				
			});
			// JSON data
			var json_csv = null;
			/**
			* Getting a csv file
			*/
	    	function onCSV() { new CSVExport(json_csv); }
	    	/**
	    	* Submitting a form with AJAX (POST)
	    	*/
			function onSubmit(){			
				$( '#resultBlock' )
							.removeClass( 'visible' )
							.addClass( 'invisible' );

				$( '#error' )
					.removeClass( 'visible' )
					.addClass( 'invisible' );

				if(_validateForm())
					$.post( '/test/urlhandler.php', $( '#formRedirect' ).serialize(), _doResult, "json" );
				else
					_getError();
			}
	    	/**
	    	* Check URL
	    	* @param URL
	    	* @return boolean
	    	*/
			function _isUrl( url ) {
			    var expression = /[-a-zA-Z0-9@:%_\+.~#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&//=]*)?/gi
			    var regexp = new RegExp( expression );
			    return regexp.test(url);
			} 
			/**
			* Validate a form
			*/
			function _validateForm() {
				var arr_urls = $( '#urls' )
								.val()
								.split( '\n' );			
				var arr_urls = arr_urls.filter( function( v ) {
					v.replace( /\r?\n|\r/g, '' );	
					return v.trim() !== '';
				});
				var res = arr_urls.length !== 0 ? true : false;
				$( arr_urls ).each( function ( ind, val ) {	
					if( !_isUrl( val ) )
						res = false;
				});
				return res;
			}
			/**
			* Get en error
			*/
			function _getError() {
				$( '#error' )
					.removeClass( 'invisible' )
					.addClass( 'visible' );
			}
	    	/**
	    	* Get a result
	    	* @param JSON
	    	*/
	    	function _doResult( result ) {
					if ( result.success ) {
						var _html = '';
						json_csv = result.data;					
						$.each( result.data, function ( ind1, val1 ) {
							var _code = '';
							var _badge_color = '';
							//set the code status with an arrows
							for ( var key in val1 ) {
				                if ( key === 'count' ) {
				                	$.each( val1.code, function( ind2, val2 ) {
				                		var arrow = '';

				                		if ( ( ind2 + 1 ) < val1.code.length ) //if an index not the last then set an arrow
				                			arrow = '&#8594;';

				                		if( val2.match(/2\d{2}/g ) !== null)
				                			_badge_color = 'success';
				                		if( val2.match(/3\d{2}/g ) !== null)
				                			_badge_color = 'info';
				                		if( val2.match(/4\d{2}/g ) !== null)
				                			_badge_color = 'warning';
				                		if( val2.match(/5\d{2}/g ) !== null)
				                			_badge_color = 'Danger';

				                		_code += '<span class="badge badge-' + _badge_color + '">' + val2 + '</span>' + arrow;
				                	});
				                }
				            }
				            var _url = $.isArray( val1.url ) ? val1.url[0] : val1.url;
							_html +=    '<tr>' +
									      '<td><a href="' + _url + '">' + _url.substring( 0, 50 ) + '</a></td>' +
									      '<td>' + _code + '</td>' +
									      '<td class="text-right">' + val1.count + '</td>' +
									    '</tr>';

							$( '#dataBlock tbody' ).html( _html );
							$( '#resultBlock' )
								.removeClass( 'invisible' )
								.addClass( 'visible' );
						});
					}
				}
		});
    </script>
  </body>
</html>