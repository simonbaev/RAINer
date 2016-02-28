<?php
	#-- Setup output MIME format as JSON
	header('Content-type: application/json');
	#-- Input sanitizing
	if(empty($_POST['sid']) || empty($_POST['pin'])) {
		echo json_encode(['status' => 100, 'message' => "Incomplete request"]);
		exit();
	}
	#-- Read data from HTML form
	$sid = htmlspecialchars($_POST['sid'], ENT_QUOTES, 'UTF-8');
	$pin = htmlspecialchars($_POST['pin'], ENT_QUOTES, 'UTF-8');
	#-- Cookie file
	$cookie_dir = "/tmp/rainer/".$sid."/".$_SERVER['REMOTE_ADDR']."/";
	$cookie_file = $cookie_dir.substr(md5(microtime()),0,5);
	if (!is_dir($cookie_dir)) {
		mkdir($cookie_dir, 0770, true);
	}
	#-- Login request
	$url_base = "https://gsw.gabest.usg.edu";
	#-- Enter login credentials
	$url = $url_base."/pls/B420/twbkwbis.P_ValLogin";
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_COOKIE, "TESTID=set");
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "sid=$sid&PIN=$pin");
	$content = curl_exec($ch);
	//-- Identify and follow META tag redirection
	$doc = new DOMDocument();
	@$doc->loadHTML($content);
	$xpath = new DOMXpath($doc);
	if($xpath->query('//meta[contains(@http-equiv,"refresh")]')->length == 0) {
		echo json_encode(['status' => 1, 'message' => "Unknown credentials"]);
		curl_close($ch);
		unlink($cookie_file);
		exit();
	}
	#-- Open E-Mail info page and extract registered e-mail(s)
	$url = $url_base."/pls/B420/bwgkogad.P_SelectEmalView";;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$content = curl_exec($ch);
	$doc->loadHTML($content);
	$xpath = new DOMXpath($doc);
	//-- Initialize e-mails array
	$emails = array();
	$validEmailFound = false;
	//-- Check if FACSTAFF email is defined
	$email = trim($xpath->query('//td[contains(.,"@gsw.edu")]/text()')->item(0)->nodeValue);
	if(strlen($email) > 0) {
		$emails['employee'] = $email;
		$validEmailFound = true;
	}
	//-- Check if RADAR email is defined
	$email = trim($xpath->query('//td[contains(.,"@radar.gsw.edu")]/text()')->item(0)->nodeValue);
	if(strlen($email) > 0) {
		$emails['student'] = $email;
		$validEmailFound = true;
	}
	if($validEmailFound === false){
		echo json_encode(['status' => 2, 'message' => "E-mail address not found"]);
		curl_close($ch);
		unset($ch);
		exit();
	}

	#-- Explore Term select page to identify Name
	if(array_key_exists('employee', $emails)) {
		$url = $url_base."/pls/B420/bwlkostm.P_FacSelTerm";
	}
	else if(array_key_exists('student', $emails)) {
		$url = $url_base."/pls/B420/bwskflib.P_SelDefTerm";
	}
	else {
		echo json_encode(['status' => 3, 'message' => "Cannot identify name"]);
		curl_close($ch);
		unset($ch);
		exit();
	}
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$content = curl_exec($ch);
	$doc->loadHTML($content);
	$xpath = new DOMXpath($doc);
	$name = preg_split('/\s+/',trim($xpath->query('//div[@class="staticheaders"]/text()')->item(0)->nodeValue),2)[1];

	#-- Explore Academic records
	$transcripts = array();
	if(array_key_exists('student', $emails)) {
		$url = $url_base . "/pls/B420/bwwktrns.P_DispStuTrans";
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$content = curl_exec($ch);
		$doc->loadHTML($content);
		$xpath = new DOMXpath($doc);
		$entries = $xpath->query('//table[@class = "datadisplaytable"]//th[contains(.,"Transcript Totals")]');
		foreach($entries as $entry) {
			$title = trim(preg_split('/:/',$entry->nodeValue,2)[1]);
			//-- GPA
			$gpa_tr = $entry->parentNode->nextSibling->nextSibling;
			$gpa_td = iterator_to_array($xpath->query('td/p/text()', $gpa_tr));
			$gpa_array = array_combine(
				['hours','points','GPA'],
				array_slice(
					array_map(
						function($v){
							return trim($v->nodeValue);
						},
						$gpa_td
					),
					3,
					3
				)
			);
			//-- Standing
			$terms_table = $entry->parentNode->parentNode->previousSibling;
			$terms_rows = iterator_to_array($xpath->query('tr/th[@colspan=5]/span', $terms_table));
			$terms_array = array_map(
				function($v){
					$temp = preg_split('/\s+/',trim($v->nodeValue));
					return $temp[0].' '.$temp[2];
				},
				$terms_rows
			);
			$academic_standing = array_combine(
				$terms_array,
				array_map(
					function($v){
						return trim($v->parentNode->parentNode->nextSibling->lastChild->previousSibling->nodeValue);
					},
					$terms_rows
				)
			);
			$additional_standing = array_combine(
				$terms_array,
				array_map(
					function($v){
						return trim($v->parentNode->parentNode->nextSibling->nextSibling->lastChild->previousSibling->nodeValue,"\t\n\r\0\x20\x0B\xC2\xA0");
					},
					$terms_rows
				)
			);
			$last_standing = [
				'term' => end($terms_array),
				'academic_standing' => $academic_standing[end($terms_array)],
				'additional_standing' => $additional_standing[end($terms_array)]
			];
			$transcript = ['type' => $title, 'summary' => $gpa_array, 'last_semester' => $last_standing];
			array_push($transcripts, $transcript);
		}
	}

	#-- Output
	$result = ['status' => 0, 'e-mails' => $emails, 'name' => $name];
	if(count($transcripts) > 0) {
		$result['transcripts'] = $transcripts;
	}
	echo json_encode($result);
	#-- Clean up
	curl_close($ch);
	unset($ch);
?>

