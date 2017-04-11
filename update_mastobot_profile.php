<?php
	// Update Mastobot profiles
	$mastodomain = "https://your.mastodon.instance";
	$tsadb = new mysqli ("host", "user", "pass", "database");

	// Load assets
	$avatar = base64_encode(file_get_contents("some-roughly-square-image.png"));

	// Get all bot accounts that haven't had their profile updated
	$profupdres = $tsadb->query ("SELECT * FROM `mastobots` WHERE `profile_updated`=0 AND `access_token`<>''");
	
	while ($profupd = $profupdres->fetch_assoc()) {
		$awipsres = $tsadb->query ("SELECT * FROM `awips` WHERE `fips` = '" . $profupd['fips'] . "'");
		if ($awipsres->num_rows > 0) {
			$awips = $awipsres->fetch_assoc();
			$special_sla = array (
				"LA" => " Parish",
				"AK" => " Borough",
				"PR" => " Municipality"
			);
			if (array_key_exists($awips['state'], $special_sla)) {
				$zonetype = $special_sla[$awips['state']];
			} else {
				$zonetype = " County";
			}
			if (stripos($awips['countyname'], "city") !== false) {
				$zonetype = "";
			}
		}
		
		if (strlen ($profupd['cnameoverride']) > 5) {
			$location_name = $profupd['cnameoverride'];
		} else {
			$location_name = $awips['countyname'] . $zonetype . ", " . $awips['state'];
		}
		
		
		echo ("Updating profile for " . $profupd['un'] . "\n");
		$postfields = array(
			"avatar" => "data:image/png;base64," . $avatar,
			"note" => "Weather alerts for " . $location_name . ". Disclaimer: https://wx4.me/ds"
		);
		$hthdr = array(
			"Authorization: Bearer " . $profupd['access_token'],
		);
		$postfields = array_map ("urlencode", $postfields);
		$poststring = "";
		foreach ($postfields as $key=>$value) {
			$poststring .= $key . "=" . $value . "&";
			rtrim ($poststring, "&");
		}
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($curl, CURLOPT_URL, $mastodomain . "/api/v1/accounts/update_credentials");
		//curl_setopt ($curl, CURLOPT_FAILONERROR, true);
		curl_setopt ($curl, CURLOPT_POST, count($postfields));
		curl_setopt ($curl, CURLOPT_POSTFIELDS, $poststring);
		curl_setopt ($curl, CURLOPT_HTTPHEADER, $hthdr);
		curl_setopt ($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
		curl_setopt ($curl, CURLOPT_HEADER, true);
		$status = curl_exec ($curl);
		$hdrsraw = substr ($status, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
		preg_match_all ("/^(.+): (.+)$/mU", $hdrsraw, $hdrsproc);
		$rl_rem_loc = array_search ("X-RateLimit-Remaining", $hdrsproc[1]);
		//var_dump ($rl_rem_loc);
		//echo ("rl_rem_loc: " . $rl_rem_loc . "\n");
		$rl_res_loc = array_search ("X-RateLimit-Reset", $hdrsproc[1]);
		//var_dump ($rl_res_loc);
		//echo ("rl_res_loc: " . $rl_res_loc . "\n");
		//var_dump ($hdrsproc);
		$rl_remaining = rtrim($hdrsproc[2][$rl_rem_loc]);
		$rl_reset_time = strtotime(rtrim($hdrsproc[2][$rl_res_loc]));
		$response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($response_code >= 400) {
			echo ("Error: " . $response_code . "\n");
			if ($response_code == "401") {
				echo ("Got unauthorized error. Marking account as not having a token\n");
				$tsadb->query ("UPDATE `mastobots` SET `access_token` = '' WHERE `fips`='" . $profupd['fips'] . "'");
			}
		} else {
			echo ("Successfully updated profile.\n");
			$tsadb->query ("UPDATE `mastobots` SET `profile_updated`=1 WHERE `fips`='" . $profupd['fips'] . "'");
		}
		echo ("Rate limit remaining: " . $rl_remaining . "\n");
		if ($rl_remaining <= 1) {
			$s2rlreset = $rl_reset_time - time();
			echo ("Sleeping for " . $s2rlreset . " seconds to allow rate limit to cool down.\n");
			sleep ($s2rlreset);
		}
		//var_dump ($status);
		curl_close ($curl);
	}
?>
