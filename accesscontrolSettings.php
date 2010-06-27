<?php
	$wgAccessControlDisableMessages = false;			// if set to false, show a line on top of each secured page, which says, which groups are allowed to see this page.
	$wgWikiVersion = 1.8;						// Set this to 1.6 or higher, if you use mediaWiki 1.6.X, this is for compatibility reasons
	$wgAdminCanReadAll = true;					// sysop users can read all restricted pages
	$wgUseMediaWikiGroups = false;					// use the groups from MediaWiki instead of own Usergroup pages
	$wgAccesscontrolDebug = false;					// Debug log on if set to true
	$wgAccesscontrolDebugFile = "/var/www/wiki/config/debug.txt";	// Path to the debug log

	# Language specific settings
	##########################################
	# deutsche version
	##########################################
	// $wgAccessControlGroupPrefix = "Benutzergruppe";
	// $wgAccessControlNoAccessPage = "/testwiki/index.php?title=Kein_Zugriff";
	// $wgGroupLineText ="Diese Seite ist nur f&uuml;r die Gruppe %s zug&auml;nglich!!!";
	// $wgGroupsLineText ="Diese Seite ist nur f&uuml;r die Gruppen %s zug&auml;nglich!!!";

	##########################################
	# english version ;-)
	##########################################
	$wgAccessControlGroupPrefix = "Usergroup"; 					// The prefix for the Usergroup pages
	$wgAccessControlNoAccessPage = "/index.php/No_Access";	 			// To this page will these users redirected who ar not allowed to see the page.
	$wgGroupLineText = "This page is only accessible for group %s !!!";		// The text for the showing on the restricted pages, for one group
	$wgGroupsLineText = "This page is only accessible for the groups %s !!!";	// The text for the showing on the restricted pages, for more than one group

?>
