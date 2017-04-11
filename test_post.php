<?php

	$mastodomain = "https://your.mastodon.instance";
	$tsadb = new mysqli ("host", "user", "pass", "database");

	$acctres = $tsadb->query ("SELECT * FROM `mastobots` WHERE `un` = 'SomeRandomUsername'");
	$acct = $acctres->fetch_assoc();

	$postfields = array( 
		"status" => "Test unlisted post from API",
		"visibility" => "unlisted"
	);

	$hthdr = array(
		"Authorization: Bearer " . $acct['access_token']
	);

	$postfields = array_map ("urlencode", $postfields);
	$poststring = "";
	foreach ($postfields as $key=>$value) {
		$poststring .= $key . "=" . $value . "&";
		rtrim ($poststring, "&");
	}

	$curl = curl_init();
	curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($curl, CURLOPT_URL, $mastodomain . "/api/v1/statuses");
	curl_setopt ($curl, CURLOPT_FAILONERROR, true);
	curl_setopt ($curl, CURLOPT_POST, count($postfields));
	curl_setopt ($curl, CURLOPT_POSTFIELDS, $poststring);
	curl_setopt ($curl, CURLOPT_HTTPHEADER, $hthdr);
	$status = curl_exec ($curl);
	if ($status === false) {
		echo ("cURL error: " . curl_error($curl) . "\n");
	}
	var_dump ($status);
	curl_close ($curl)
?>
