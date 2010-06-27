<?php
	// MediaWiki extension that enables group access restriction on a page-by-page
	// basis
	// contributed by Martin Mueller (http://blog.pagansoft.de)
	// based on accesscontrol.php by Josh Greenberg

	// This is version 0.7
	// It's tested on MediaWiki 1.8.2

	// INSTALLATION:
	//
	// Step 1:
	// add the following lines to the bottom of your LocalSettings.php:
	// require_once("extensions/accesscontrolSettings.php");
	// include("extensions/accesscontrol.php");
	//
	// Step 2:
	// check (and maybe edit) the settings in accesscontrolSettings.php:
	//
	// $wgAccessControlDisableMessages = false; // if false, show a Line on Top of each secured Page, which says, which Groups are allowed to see this page.
	// $wgAccessControlGroupPrefix = "Usergroup"; // The Prefix for the Usergroup-Pages
	// $wgAccessControlNoAccessPage = "/index.php/No_Access"; // To this Page will these users redirected who ar not allowed to see the page.
	// $wgWikiVersion = 1.6; // Set this to 1.7, if you use mediaWiki 1.7 or greater, this is for compatibility reasons
	// $wgUseMediaWikiGroups = false; // use the groups from MediaWiki instead of own Usergroup pages
	// $wgAdminCanReadAll = true; // sysop users can read all restricted pages
	// $wgGroupLineText = "This page is only accessible for group %s !!!"; // The text for the showing on the restricted pages, for one group
	// $wgGroupsLineText = "This page is only accessible for group %s !!!"; // The text for the showing on the restricted pages, for more than one group
	// $wgAccesscontrolDebug = true; // Debug log on
	// $wgAccesscontrolDebugFile = "/var/www/wiki/config/debug.txt"; // Path to the debug log
	//
	// Step 3:
	// Create a Wiki-Page with the Name Usergroup:Groupname and add the Users in the Group in a Bulletlist
	// Example: You want the Group "IT-Department" with the Users "John Doe" and "Jane Doe" in it:
	// Create the Wiki-Article: "Usergroup:IT-Department" and put the following in it's text:
	// *John Doe
	// *Jane Doe
	//
	// There is a second possibility for using groups. You can set the variable $wgUseMediaWikiGroups to true and use the internal groups from MediaWiki.
	// Then you can use the special page "Special:Userrights" for Useradministration and you don't have to create the Usergroup pages.

	// Step 4: Create a No-Access Page with the Name No_Access and Write some Text in it (i.e. "Access to this page is denied for you!"

	// That's it for the installation. To restrict access on a page-by-page basis to specific usergroups, just include the names of the allowed usergroups within an tag (separated by double commas) in the body of that page. Thus, if you wanted to restrict access to the people with usergroups "Administrators", "IT-Department" and "Sales", you would use the following syntax:
	// <accesscontrol>Administrators,,IT-Department,,Sales</accesscontrol>
	//
	// If you want to protect the page, so the users of a group can read but not edit, you have to append "(ro)" to the group name.
	//
	// Example:
	// <accesscontrol>Administrators,,IT-Department(ro),,Sales(ro)</accesscontrol>
	//
	// In this example all users from the groups "Administrators", "IT-Department" and "Sales" can read the page but only the users from
	// "Administrators" can edit it.
	//
	// Attention for the german users: if you set $wgUseMediaWikiGroups to true then you have to use the english names for the groups i.E.,
	// instead of writing <accesscontrol>Bürokraten</accesscontrol> you have to write <accesscontrol>bureaucrats</accesscontrol>!!!
	//
	// Achtung deutsche Benutzer: Wenn Ihr $wgUseMediaWikiGroups auf "true" setzt, müsst Ihr als Gruppennamen die englischen Namen der Gruppen benutzen, z.B.
	// anstatt <accesscontrol>Bürokraten</accesscontrol> muss <accesscontrol>bureaucrats</accesscontrol> benutzt werden!!!

	############################################################################################################
	# Hooks
	############################################################################################################

	// Add the hook function call to an array defined earlier in the wiki //code execution.
	$wgExtensionFunctions[] = "wfAccessControlExtension";

	// Hook the conTolEditAccess function in the edit action
	$wgHooks['AlternateEdit'][] = 'controlEditAccess';

	//Hook the userCan function for bypassing the cache (bad bad hackaround)
	$wgHooks['userCan'][] = 'hookUserCan';

	// Hook the controlUserGroupPageAccess in the Article to allow access to the "Usergroup:..." pages only to the sysop
	$wgHooks['ArticleAfterFetchContent'][] = 'controlUserGroupPageAccess';

	############################################################################################################
	# functions
	############################################################################################################

	// handles the debug output to a debug file
	function debugme($input)
	{
		global $wgAccesscontrolDebug;
		global $wgAccesscontrolDebugFile;

		if ($wgAccesscontrolDebug)
		{
			$f = fopen($wgAccesscontrolDebugFile, "a+");
			fputs($f, $input."\r\n");
			fclose($f);
		}
	}

	//This is the hook function. It adds the tag to the wiki parser and
	//tells it what callback function to use.
	function wfAccessControlExtension()
	{
		global $wgParser;
		$wgParser->setHook( "accesscontrol", "doControlUserAccess" );
	}

	// called by the callback function for the accesscontrol-tag
	function doControlUserAccess( $input, $argv, &$parser )
	{
		global $wgUseMediaWikiGroups;

		debugme("doControlUserAccess_1: start accesscontrol extension with input: ".$input);
		if ($wgUseMediaWikiGroups)
			return  controlMediaWikiUserAccess( $input, true );
		else
			return  controlUserAccess( $input, true );
	}

	// Generate the Group Title and set readOnly to true, if the GroupName ends with "(ro)"
	function makeGroupTitle($groupEntry, &$readOnly = false)
	{
		global $wgAccessControlGroupPrefix;
		global $wgUseMediaWikiGroups;

		// some first initialisations
		if (trim($wgAccessControlGroupPrefix)=="") $wgAccessControlGroupPrefix="Usergroup";

		if ($wgUseMediaWikiGroups)
			$groupTitle = trim($groupEntry);
		else
			$groupTitle = $wgAccessControlGroupPrefix.":".trim($groupEntry);

		if (strpos($groupTitle,"(ro)") === false)
		{
			$readOnly = false;
		}
		else
		{
			debugme("makeGroupTitle_1: ReadOnly Access for group ".$groupTitle);
			$groupTitle = str_replace("(ro)","",$groupTitle);
			$readOnly = true;
		}

		return($groupTitle);
	}

	// make the textual group list for the message on the top
	function getGroupsToDisplay($groupAccess)
	{
		global $wgUser;
		global $wgAccessControlGroupPrefix;
		// some first initialisations
		if (trim($wgAccessControlGroupPrefix)=="") $wgAccessControlGroupPrefix="Usergroup";

		// the first group has no komma befor it
		$first = true;
		// holds the Text for allowed Groups
		$allowedGroups = "";
		$groupsToDisplay = Array();

		foreach ($groupAccess as $groupEntry)
		{
			$groupTitle = makeGroupTitle($groupEntry);
			debugme("getGroupsToDisplay_1: Extracted Group Title: ".$groupTitle);

			if ($first)
				$allowedGroups = "[[".$groupTitle."|".trim($groupEntry)."]]";
			else
				$allowedGroups .= ", [[".$groupTitle."|".trim($groupEntry)."]]";

			$groupsToDisplay[] = $allowedGroups;
		}

		return $groupsToDisplay;
	}

	// extracts the AllowedUsers from the GroupPages
	function getAllowedUsersFromGroupPages($groupAccess, $useReadOnlyFlag)
	{
		global $wgWikiVersion;

		// holds later the allowed users
		$allowedAccess = Array();

		foreach ($groupAccess as $groupEntry)
		{
			$readOnly = false;

			// get full Pagetitle for the group
			$groupTitle = makeGroupTitle($groupEntry, $readOnly);

			// get the allowed users from the group
			// this part is used, if the Version of MediaWiki is greater or equal than 1.7
			if ($wgWikiVersion>=1.7)
			{
				// create new title as static helper
				$Title = new Title();

				// create title in namespace 0 (default) from groupTitle
				$gt = $Title->makeTitle(0, $groupTitle);
				// create Article and get the content
				$groupPage = new Article( $gt, 0 );
				$allowedUsers=$groupPage->fetchContent(0);
				$Title = null;
			}
			else
			{
				// old mimic for mediaWiki 1.6 and below
				$groupPage = new Article( $groupTitle, 0 );
				$allowedUsers=$groupPage->getPreloadedText($groupTitle);
			}
			$groupPage = NULL;

			debugme("getAllowedUsersFromGroups_1: allowedUsers: ".$allowedUsers);

			// Create array of users with permission to access this page
			$usersAccess = explode("*", $allowedUsers);

			if (($useReadOnlyFlag == true) && ($readOnly == true))
			{
				// Do nothing
			}
			else
			{
				// Trim leading whitespaces from usernames and make them lowercase
				foreach ($usersAccess as $userEntry)
				{
					if (trim($userEntry) != "") $allowedAccess[] = strtolower(trim($userEntry));
				}
			}
		}

		return $allowedAccess;
	}

	// make Redirection to NoAccess page
	function doRedirect()
	{
		global $wgOut;
		global $wgAccessControlNoAccessPage;

		// some first initialisations
		if (trim($wgAccessControlNoAccessPage)=="") $wgAccessControlNoAccessPage="/index.php/No_Access";

		// make direct redirect, if $wgOut isn't already set (bypassing the cache), bad hack
		if ((is_object( $wgOut )) && (is_a( $wgOut, 'StubObject' )))
		{
			header("Location: ".$wgAccessControlNoAccessPage);
			exit;
		}
		else
		{
			// redirect to the no-access-page if current user doesn't match the
			// accesscontrol list
			$wgOut->redirect($wgAccessControlNoAccessPage);
		}
	}

	// The callback function for user access
	function controlUserAccess( $input, $showGroupText, $useReadOnlyFlag = false )
	{
		// Grab currently logged in user
		global $wgUser;

		// whether to show or to show not the little message on the Site
		global $wgAccessControlDisableMessages;
		// the page where we redirect if access was denied
		global $wgAdminCanReadAll;

		global $wgArticlePath;

		// get allowed Groups from Tag
		$groupAccess = explode(",,", $input);

		$groupsToDisplay = getGroupsToDisplay($groupAccess);

		$allowedAccess = getAllowedUsersFromGroupPages($groupAccess, $useReadOnlyFlag);

		debugme("controlUserAccess_0: the following users have access: ".implode($allowedAccess," / "));

		debugme("controlUserAccess_1: testing for access for user ".$wgUser->getName());
		// if user is NOT in Array of allowed Users...
		if (!in_array(strtolower(trim($wgUser->getName())), $allowedAccess))
		{
			debugme("controlUserAccess_2: user ".$wgUser->getName()." not in group(s) ".str_replace("(ro)","",implode($groupsToDisplay,"/")).", testing for sysop rights");
			// if user in sysop-Group and admins may see restricted pages allow access nevertheless
			if ((in_array("sysop", $wgUser->mGroups)) && ($wgAdminCanReadAll == true))
			{
				debugme("controlUserAccess_3: user ".$wgUser->getName()." is sysop and wgAdminCanReadAll is true, so access will be granted");
				// Allow Access (and show Text if configured)
				if ($showGroupText)
				{
					return( displayGroups($groupsToDisplay) );
				}
				else
				{
					return true;
				}
			}
			else
			{
				debugme("user controlUserAccess_4: ".$wgUser->getName()." is not sysop or wgAdminCanReadAll is not true, so access is not allowed");
				doRedirect();
				return false;
			}
		}
		else
		{
			debugme("controlUserAccess_6: access granted, user ".$wgUser->getName()." is in group(s) ".implode($groupsToDisplay,"/"));
			// Allow Access (and show Text if configured)
			if ($showGroupText)
			{
				return( displayGroups($groupsToDisplay) );
			}
			else
			{
				return true;
			}
		}
	}

	// The callback function for user access if $wgUseMediaWikiGroups is set
	function controlMediaWikiUserAccess( $input, $showGroupText, $useReadOnlyFlag = false )
	{
		global $wgUser;
		global $wgAdminCanReadAll;

		// get allowed Groups from Tag
		$groupAccess = explode(",,", $input);
		$readOnly = false;

		require_once("includes/User.php");

		debugme("controlMediaWikiUserAccess_1: ".$wgUser->getName());

		$groupsToDisplay = getGroupsToDisplay($groupAccess);

		if(in_array("sysop", $wgUser->mGroups) && $wgAdminCanReadAll)
		{
			debugme("controlMediaWikiUserAccess_2: user in sysop group, so access is granted");

			if ($showGroupText)
			{
				return( displayGroups($groupsToDisplay) );
			}
			else
			{
				return true;
			}
		}

		foreach($groupAccess as $groupEntry)
		{
			$groupTitle = makeGroupTitle($groupEntry, $readOnly);
			debugme("controlMediaWikiUserAccess_3: Group = " . $groupTitle);

			if (($useReadOnlyFlag == true) && ($readOnly == true))
			{
				debugme("controlMediaWikiUserAccess_4: group '".$groupTitle."', is readonly so access is not granted");
			}
			else
			{
				if(in_array($groupTitle, $wgUser->mGroups))
				{
					debugme("controlMediaWikiUserAccess_5: user in group '".$groupTitle."', so access is granted");
					if ($showGroupText)
					{
						return( displayGroups($groupsToDisplay) );
					}
					else
					{
						return true;
					}
				}
			}
		}

		debugme("controlMediaWikiUserAccess_6: user access denied");
		doRedirect();
		return false;
	}

	// shows the text, which says for which group(s) the page is restricted
	function displayGroups($allowedGroups)
	{
		global $wgAccessControlDisableMessages;
		global $wgOut;
		global $wgGroupLineText;
		global $wgGroupsLineText;

		if (is_array($allowedGroups))
		{
			debugme("displayGroups_1: allowedGroups is an array");
			$displayGroups = str_replace("(ro)"," (ro)",implode($allowedGroups, " / "));
			$groupCount = count($allowedGroups);
		}
		else
		{
			if (trim($allowedGroups)=="")
				$groupCount = 0;
			else
				$groupCount = 1;

			debugme("displayGroups_2: allowedGroups is not an array");
			$displayGroups = str_replace("(ro)","",$allowedGroups);
		}

		if (($groupCount>0) && (!$wgAccessControlDisableMessages))
		{
			debugme("displayGroups_3: print message");
			// output the little Message, if $wgAccessControlDisableMessages is not set
			$style = "<font style=\"font-size:8pt\">";
			$style_end = "</font>";

			if ( $groupCount == 1 )
				return( $wgOut->parse($style.sprintf($wgGroupLineText, $displayGroups).$style_end) );
			else
				return( $wgOut->parse($style.sprintf($wgGroupsLineText, $displayGroups).$style_end) );
		}
		else
		{
			return("");
		}
	}

	// check if user is anonymous
	function anonymousUser($wgUser)
	{
		return $wgUser->isIP($wgUser->getName());
	}

	// hook function for the userCan event
	function hookUserCan( &$title, &$wgUser, $action, &$result )
	{
		global $wgAccessControlNoAccessPage;
		global $wgOut;
		global $wgUseMediaWikiGroups;

		$allowAccess = true;

		if ($action=='read')
		{
			$article = new Article( $title, 0 );
			// $article->loadPageData( "fromdb" );
			$content = $article->getContent();
			debugme("hookUserCan_1: -- Begin content --");
			debugme("hookUserCan_2: ".$content);
			debugme("hookUserCan_3: -- End content --");

			$starttag = "<accesscontrol>";
			$endtag = "</accesscontrol>";

			// get start position of the content of the tag
			$start = strpos( $content, $starttag );
			if ($start === false)
				$start = -1;
			else
				$start += strlen($starttag);

			// get end position of the content of the tag
			$end = strpos( $content, $endtag );
			if ($end === false)
				$end = -1;

			// if accesscontrol tag is found, check if the user is allowed to see the content
			if (($start >= 0) && ($end > 0) && ($end > $start))
			{
				debugme("hookUserCan_4: accesscontrol tag found");
				{
					$allowedGroups = str_replace("(ro)","",substr($content, $start, $end-strlen($endtag)+1 ));
					debugme("hookUserCan_5: ".$allowedGroups);

					if ($wgUseMediaWikiGroups)
						controlMediaWikiUserAccess( $allowedGroups, true );
					else
						controlUserAccess( $allowedGroups, true );
				}
			}
		}
	}

	// Hook function for the edit action
	// $editpage: the editpage (object) being called
	function controlEditAccess(&$editpage)
	{
		global $wgAccessControlNoAccessPage;
		global $wgUseMediaWikiGroups;

		$starttag = "<accesscontrol>";
		$endtag = "</accesscontrol>";

		// search accesscontrol tag
		$title = $editpage->mTitle;
		$editPage = new Article( $title, 0 );
		$content = $editPage->getContent();

		// get start position of the content of the tag
		$start = strpos( $content, $starttag );
		if ($start === false)
			$start = -1;
		else
			$start += strlen($starttag);
			// get end position of the content of the tag
		$end = strpos( $content, $endtag );
		if ($end === false)
			$end = -1;


		// if accesscontrol tag is found, check if the user is allowed to see the content
		if (($start >= 0) && ($end > 0) && ($end > $start))
		{
			$allowedGroups = substr($content, $start, $end-strlen($endtag)+1 );
			debugme("controlEditAccess_1: AllowedGroups = ".$allowedGroups);

			if ($wgUseMediaWikiGroups)
				return controlMediaWikiUserAccess( $allowedGroups, false, true );
			else
				return controlUserAccess( $allowedGroups, false, true );
		}

		// if nothing happens here, go on normally
		return true;
	}

	// This function is for controlling the access (view and edit) to the "Usergroup:..." pages
	function controlUserGroupPageAccess( $out )
	{
		// Grab currently logged in user
		global $wgUser;

		// Grab the current title
		global $wgTitle;

		// current Output (for redirection)
		global $wgOut;

		// get configuration variables
		global $wgAccessControlNoAccessPage;
		global $wgAccessControlGroupPrefix;

		// if this is a Usergroup-Page allow access only to the sysop
		if (substr($wgTitle->getText(),0,strlen($wgAccessControlGroupPrefix)) == $wgAccessControlGroupPrefix)
		{
			if (!in_array("sysop", $wgUser->mGroups))
			{
				// redirect to the no-access-page if current user isn't a sysop
				$wgOut->redirect($wgAccessControlNoAccessPage);

				return false;
			}
		}
		else
			return true;
	}

?>
