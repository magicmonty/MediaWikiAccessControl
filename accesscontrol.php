<?php
	// MediaWiki extension that enables group access restriction on a page-by-page 
	// basis
	// contributed by Martin Mueller (http://blog.pagansoft.de)
	// based on accesscontrol.php by Josh Greenberg
	
	// This is version 0.3
	// It's tested on MediaWiki 1.6.8 and 1.7.1
	
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
	// $wgWikiVersion = 1.6 // Set this to 1.7, if you use mediaWiki 1.7 or greater, this is for compatibility reasons
	//
	// Step 3:
	// Create a Wiki-Page with the Name Usergroup:Groupname and add the Users in the Group in a Bulletlist
	// Example: You want the Group "IT-Department" with the Users "John Doe" and "Jane Doe" in it:
	// Create the Wiki-Article: "Usergroup:IT-Department" and put the following in it's text:
	// *John Doe
	// *Jane Doe
	//
	// Step 4: Create a No-Access Page with the Name No_Access and Write some Text in it (i.e. "Access to this page is denied for you!"
	
	// That's it for the installation. To restrict access on a page-by-page basis to specific usergroups, just include the names of the allowed usergroups within an tag (separated by double commas) in the body of that page. Thus, if you wanted to restrict access to the people with usergroups "Administrators", "IT-Department" and "Sales", you would use the following syntax:
	// <accesscontrol>Administrators,,IT-Department,,Sales</accesscontrol>
	
	// Add the hook function call to an array defined earlier in the wiki //code execution. 
	$wgExtensionFunctions[] = "wfAccessControl";
	// Hook the conTolEditAccess function in the edit action
	$wgHooks['AlternateEdit'][] = 'controlEditAccess';
	// Hook the controlUserGroupPageAccess in the Article to allow access to the "Usergroup:..." pages only to the sysop
	$wgHooks['ArticleAfterFetchContent'][] = 'controlUserGroupPageAccess';
	
	//This is the hook function. It adds the tag to the wiki parser and 
	//tells it what callback function to use. 
	function wfAccessControl() 
	{
		global $wgParser;
		# register the extension with the WikiText parser
		$wgParser->setHook( "accesscontrol", "doControlUserAccess" );
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
	
	function doControlUserAccess( $input )
	{
		return  controlUserAccess( $input, true );
	}
	
	// The callback function for user access 
	function controlUserAccess( $input, $showGroupText )
	{
		// Grab currently logged in user
		global $wgUser;
		global $wgOut;
		
		// whether to show or to show not the little message on the Site
		global $wgAccessControlDisableMessages;
		// the prefix for usergroup pages
		global $wgAccessControlGroupPrefix;
		// the page where we redirect if access was denied
		global $wgAccessControlNoAccessPage;
		// the page where we redirect if access was denied
		global $wgWikiVersion;
		
		// some first initialisations	
		if (trim($wgAccessControlGroupPrefix)=="") $wgAccessControlGroupPrefix="Usergroup";
		if (trim($wgAccessControlNoAccessPage)=="") $wgAccessControlNoAccessPage="/index.php/No_Access";
		
		// holds later the allowed users
		$allowedAccess = Array();
		// holds the Text for allowed Groups
		$allowedGroups = "";
		// the first group has no komma befor it
		$first = true;
		// number of groups
		$groupCount = 0;
		
		// get allowed Groups from Tag
		$groupAccess = explode(",,", $input);
		
		foreach ($groupAccess as $groupEntry)
		{
			// get full Pagetitle for the group
			$groupTitle = $wgAccessControlGroupPrefix.":".trim($groupEntry); 
			// make the textual group list for the message on the top
			if ($first)
			{
				$allowedGroups = "[[".$groupTitle."|".trim($groupEntry)."]]";
			}
			else
			{
				$allowedGroups .= ", [[".$groupTitle."|".trim($groupEntry)."]]";
			}
			
			
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

			// Create array of users with permission to access this page
			$usersAccess = explode("*", $allowedUsers);
		
			// Trim leading whitespaces from usernames and make them lowercase
			foreach ($usersAccess as $userEntry) 
			{
				$allowedAccess[] = strtolower(trim($userEntry));
			}
			
			$groupCount++;
		}
		
		if (!in_array(strtolower(trim($wgUser->getName())), $allowedAccess))
		{
			if (!in_array("sysop", $wgUser->mGroups))
			{
				// redirect to the no-access-page if current user doesn't match the
				// accesscontrol list
				$wgOut->redirect($wgAccessControlNoAccessPage);
				
				return false;
			}
			else
			{
				if ($showGroupText)
				{
					return( displayGroups($groupCount, $allowedGroups) );
				}
				else
				{
					return true;
				}
			}
		}
		else
		{
			if ($showGroupText)
			{
				return( displayGroups($groupCount, $allowedGroups) );
			}
			else
			{
				return true;
			}
		}
	}
	
	function displayGroups($groupCount, $allowedGroups)
	{
		global $wgAccessControlDisableMessages;
		global $wgOut;
		
		if (($groupCount>0) && (!$wgAccessControlDisableMessages))
		{
			// output the little Message, if $wgAccessControlDisableMessages is not set
			$style = "<font style=\"font-size:8pt\">";
			$style_end = "</font>";
			
			if ( $groupCount == 1 )
				return( $wgOut->parse($style."Diese Seite ist nur f&uuml;r die Gruppe ".$allowedGroups." zug&auml;nglich!!!".$style_end) );
			else
				return( $wgOut->parse($style."Diese Seite ist nur f&uuml;r die Gruppen ".$allowedGroups." zug&auml;nglich!!!".$style_end) );
		}
		else
		{
			return("");
		}
	}
	
	// Hook function for the edit action
	// $editpage: the editpage (object) being called
	function controlEditAccess(&$editpage) 
	{
		global $wgAccessControlNoAccessPage;
		global $wgOut;
		
		$starttag = "<accesscontrol>";
		$endtag = "</accesscontrol>";
		
		// search accesscontrol tag
		$title = $editpage->mTitle;
		$editPage = new Article( $title, 0 );
		$content = $editPage->getContent();

		// get start position of the content of the tag
		$start = strpos( $content, $starttag ) + strlen($starttag);
		// get end position of the content of the tag
		$end = strpos( $content, $endtag ) - $start;
		
		
		// if accesscontrol tag is found, check if the user is allowed to see the content
		if (($start >= 0) && ($end > 0) && ($end < $start))
		{
			$allowedGroups = substr($content, $start, $end );
			return controlUserAccess( $allowedGroups, false );
		}
		
		// if nothing happens here, go on normally
		return true;
	}
?>
