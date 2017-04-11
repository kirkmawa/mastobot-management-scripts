<?php
	$mastodomain = "https://your.mastodon.instance";
	$oauth_client_id = "<SEE MASTODON DOCS TO GET THIS>";
	$oauth_client_secret = "<SEE MASTODON DOCS TO GET THIS>";
	$tsadb = new mysqli ("host", "user", "pass", "database");

	// Get all bots that have had their email address verified.
	$verifiedbots = $tsadb->query ("SELECT * FROM `mastobots` WHERE `verified`=1 AND `access_token`=''");

	while ($bot = $verifiedbots->fetch_assoc()) {
		echo ("Getting oAuth token for " . $bot['un'] . ".\n");
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($curl, CURLOPT_URL, $mastodomain . "/oauth/token");
		curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt ($curl, CURLOPT_MAXREDIRS, 0);
		curl_setopt ($curl, CURLOPT_HEADER, true);

		$postfields = array (
			"grant_type" => "password",
			"scope" => "write",
			"client_id" => $oauth_client_id,
			"client_secret" => $oauth_client_secret,
			"username" => "some.random.account+" . $bot['fips'] . "@gmail.com",
			"password" => $bot['pw']
		);
		$postfields = array_map("urlencode", $postfields);
		$poststring = "";
		foreach ($postfields as $key=>$value) {
			$poststring .= $key . "=" . $value . "&";
			rtrim ($poststring, "&");
		}
		curl_setopt ($curl, CURLOPT_POST, count($postfields));
		curl_setopt ($curl, CURLOPT_POSTFIELDS, $poststring);

		$response = curl_exec ($curl);
		$hdrsraw = substr ($response, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
		$json = substr ($response, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
		preg_match_all ("/^(.+): (.+)$/mU", $hdrsraw, $hdrsproc);
		$rl_rem_loc = array_search ("X-RateLimit-Remaining", $hdrsproc[1]);
		$rl_res_loc = array_search ("X-RateLimit-Reset", $hdrsproc[1]);
		if ($rl_rem_loc && $rl_res_loc) {
			$rl_remaining = rtrim($hdrsproc[2][$rl_rem_loc]);
			$rl_reset_time = strtotime(rtrim($hdrsproc[2][$rl_res_loc])); 
		}
		$response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); 
		if ($response_code < 400) {
			
			$oauth_response = json_decode ($json, true);
			$access_token = $oauth_response['access_token'];
			if ($tsadb->query ("UPDATE `mastobots` SET `access_token`='" . $access_token . "' WHERE `fips` = '" . $bot['fips'] . "'")) {
				echo ("Access token stored.\n");
			}
		} else {
			echo ("Token retrieval failed: " . $response_code . "\n");
		}
		curl_close ($curl);
		if ($rl_rem_loc && $rl_res_loc) {
			echo ("Rate limit remaining: " . $rl_remaining . "\n");
			if ($rl_remaining <= 1) {
				$s2rlreset = $rl_reset_time - time();
				echo ("Sleeping for " . $s2rlreset . " seconds to allow rate limit to cool down.\n");
				sleep ($s2rlreset);
			}
		}
	}
?>
