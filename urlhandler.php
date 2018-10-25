<?php
/**
* @author Sergio
* @link https://www.weblancer.net/users/qsef/
*/
if( ! isset($_POST['urls'])) exit('Bad request');

require 'URLRedirect.php';

ini_set('max_execution_time', 300);

preg_match_all('/([\S]+)/ism', $_REQUEST['urls'], $matches);

$json_result = array(
	'success' => true,
	'data'    => array(),
	'error'   => null
);

$urlRedirect = new URLRedirect();
$urlRedirect->isMultiple(true);

$count = $arr = null;

foreach ($matches[0] as $url) {
	$res = $urlRedirect->resolveURL($url);

	if($res->didErrorOccur()) {
		$arr = array(
			'url'   => $url, //string
			'code'  => array('Error'),
			'count' => 0
		);
	} else {
		foreach ($res->getHTTPStatusCodes() as $key => $value) {
			if(preg_match('/2\d{2}|4\d{2}|5\d{2}/', $value)) {
				$count = $key; //200|400|500
				break;
			}
		}
		$arr = array(
			'url'   => $res->getAllURL(), //array
			'code'  => $res->getHTTPStatusCodes(), //array
			'count' => $count //int
		);
		if ($res->isLooped()) 
			array_push($arr['code'], 'Looped');
	}	
	array_push($json_result['data'], $arr);
}
echo json_encode($json_result);
/*
	[
		"success": "true",
		"data": {
			"url": "http://some.site",
			"code": {
				"1": "301",
				"2": "301",
				"3": "200"
			},
			"count": "2"
		},
		"error": "null"
	]
*/