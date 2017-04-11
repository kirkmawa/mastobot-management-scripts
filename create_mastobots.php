<?php
	set_time_limit (0);	
	$mastodomain = "https://your.mastodon.instance";

	// Create Mastobots accounts
	$tsadb = new mysqli ("host", "user", "pass", "database");

	// Get entries from AWIPS table.
	$awipsres = $tsadb->query ("SELECT * FROM `awips`");

	while ($awzone = $awipsres->fetch_assoc()) {
		// Sanitize input to make username-compatible strings
		$awun = strtr ($awzone['countyname'] . $awzone['state'], array("'"=>"", " "=>"", "."=>""));
		echo ("Processing " . $awzone['fips'] . ": " . $awun . "\n");
		// Loop on this. We first need to check to see if there's already an account.
		$mastobotres = $tsadb->query ("SELECT * FROM `mastobots` WHERE `fips` = " . $awzone['fips']);
		// If there's nothing, we need to start that process.
		if ($mastobotres->num_rows < 1) {
			echo ("Creating mastobot account...\n");
			// We need to load the main page of the Mastodon instance
			$curl = curl_init();
			curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt ($curl, CURLOPT_COOKIESESSION, true);
			curl_setopt ($curl, CURLOPT_COOKIEJAR, "mastocookies.txt");
			curl_setopt ($curl, CURLOPT_FAILONERROR, true);
			curl_setopt ($curl, CURLOPT_URL, $mastodomain . "/about");
			$mainpagecontent = curl_exec ($curl);
			curl_close ($curl);

			// We need to find the hidden verification token in the registration form
			$doc = new DOMDocument();
			@$doc->loadHTML($mainpagecontent);
			$xpath = new DOMXpath ($doc);
			$hiddentoken = $xpath->query('//*[@id="new_user"]/input[@name="authenticity_token"]/@value');
			$authenticity_token = $hiddentoken[0]->value;
				
			// Generate a random password
			$bytes = random_bytes(25);
			$genpassword = bin2hex($bytes);

			// Now we can post the form
			$postfields = array(
				"authenticity_token" => $authenticity_token,
				"user[account_attributes][username]" => $awun,
				"user[email]" => "some.random.account+" . $awzone['fips'] . "@gmail.com",
				"user[password]" => $genpassword,
				"user[password_confirmation]" => $genpassword,
				"button" => ""
			);
			$postfields = array_map("urlencode", $postfields);
			$poststring = "";
			foreach ($postfields as $key=>$value) {
				$poststring .= $key . "=" . $value . "&";
				rtrim ($poststring, "&");
			}
			
			$curl = curl_init();
			curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt ($curl, CURLOPT_FAILONERROR, true);
			curl_setopt ($curl, CURLOPT_COOKIEFILE, "mastocookies.txt");
			curl_setopt ($curl, CURLOPT_URL, $mastodomain . "/auth");
			curl_setopt ($curl, CURLOPT_POST, count($postfields));
			curl_setopt ($curl, CURLOPT_REFERER, $mastodomain . "/about");
			curl_setopt ($curl, CURLOPT_POSTFIELDS, $poststring);
			$create_acct = curl_exec($curl);
			if ($create_acct !== false) {
				echo ("Account creation sent.\n");
				$tsadb->query ("INSERT INTO `mastobots` (`fips`, `un`, `pw`) VALUES ('" . $awzone['fips'] . "', '" . $awun . "', '" . $genpassword . "')");
				sleep(3);
			} else {
				echo ("Account creation failed: " . curl_error($curl));
			}
			curl_close ($curl);
			unlink ("mastocookies.txt");
		} else {
			echo ("Mastobot account already exists.\n");
		}
		echo ("\n");
	}

?>
