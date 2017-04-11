<?php
	$mastodomain = "https://your.mastodon.instance";
	$tsadb = new mysqli ("host", "user", "pass", "database");
	// Connect to Gmail
	$gmail_imap = imap_open ("{imap.gmail.com:993/imap/ssl}INBOX", "SomeRandomAccount@gmail.com", "YourPasswordSucksBruh");
	// Get an array of all new messages
	$messages = imap_search($gmail_imap, 'UNSEEN FROM "WhateverEmail@Mastodon.Notifications"', SE_UID);
	if ($messages !== false) {
	foreach ($messages as $newmailuid) {
		$msbody = imap_fetchbody ($gmail_imap, $newmailuid, 1, FT_UID);
		// Find the FIPS ID in the email address
		preg_match ("/some.random.account\\+(\\d+)@gmail.com/", $msbody, $fipsid);
		echo ($fipsid[1] . " - ");
		// Get the confirmation URL
		preg_match ("/https:\\/\\/your.mastodon.instance\\S+/", $msbody, $confurl);
		echo ($confurl[0] . "\n");
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($curl, CURLOPT_URL, $confurl[0]);
		$verify = curl_exec ($curl);
		if ($verify === false) {
			echo ("Email verification failed.\n");
		} else {
			echo ("Email verification succeeded.\n");
			$tsadb->query ("UPDATE `mastobots` SET `verified`=1 WHERE `fips`='" . $fipsid[1] . "'");
		}
		// Mark the unseen message for deletion
		imap_delete ($gmail_imap, $newmailuid, FT_UID);
		
	}
	} else {
		echo ("No unseen messages in this mailbox.\n");
	}
	imap_close ($gmail_imap, CL_EXPUNGE);
?>
