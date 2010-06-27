<?php
/*
	MediaWiki extension that enables group access restriction on a page-by-page basis
	contributed by Martin Gondermann (http://blog.pagansoft.de)
	based on accesscontrol.php by Josh Greenberg

	This is version 0.9
	It's tested on MediaWiki 1.12.0
	
	INSTALLATION:
	
	Step 1:
	add the following lines to the bottom of your LocalSettings.php:
	require_once("extensions/accesscontrol.php");
	
	Step 2:
	set some settings in LocalSetting.php:
		$wgAccessControlDisableMessages:
			if false, show a Line on Top of each secured Page, which says, which Groups are allowed to see this page.
			Default: false
			
		$wgAccessControlUseMediaWikiGroups:
			use the groups from MediaWiki instead of own Usergroup pages
			Default: false
			
		$wgAccessControlAdminCanReadAll:
			sysop users can read all restricted pages
			Default: true
			
		$wgAccessControlDebug:
			Debug log on
			Default: false
			
		$wgAccessControlDebugFile:
			Path to the debug log
			Default: "$IP/config/debug.txt"
			
		$wgAccessControlAnonymousGroupName
			Name of the anonymous user group
			Default: "anon"
			
		$wgAccessControlPagePrefix:
			the prefix for the Article Pages
			Default: "/index.php?title=" 

	Step 3:
	Create a Wiki-Page with the Name Usergroup:Groupname and add the Users in the Group in a Bulletlist
	Example: You want the Group "IT-Department" with the Users "John Doe" and "Jane Doe" in it:
	Create the Wiki-Article: "Usergroup:IT-Department" and put the following in it's text:
	*John Doe
	*Jane Doe
	
	There is a second possibility for using groups. You can set the variable $wgUseMediaWikiGroups to true and use the internal groups from MediaWiki.
	Then you can use the special page "Special:Userrights" for Useradministration and you don't have to create the Usergroup pages.
	
	Step 4: Create a No-Access Page with the Name No_Access and Write some Text in it (i.e. "Access to this page is denied for you!"
	
	That's it for the installation. To restrict access on a page-by-page basis to specific usergroups, just include the names of the allowed usergroups within an tag (separated by double commas) in the body of that page. Thus, if you wanted to restrict access to the people with usergroups "Administrators", "IT-Department" and "Sales", you would use the following syntax:
	<accesscontrol>Administrators,,IT-Department,,Sales</accesscontrol>
	
	If you want to protect the page, so the users of a group can read but not edit, you have to append "(ro)" to the group name.
	
	Example:
	<accesscontrol>Administrators,,IT-Department(ro),,Sales(ro)</accesscontrol>
	
	In this example all users from the groups "Administrators", "IT-Department" and "Sales" can read the page but only the users from
	"Administrators" can edit it.
	
	Attention for the german users: if you set $wgUseMediaWikiGroups to true then you have to use the english names for the groups i.E.,
	instead of writing <accesscontrol>Bürokraten</accesscontrol> you have to write <accesscontrol>bureaucrats</accesscontrol>!!!
	
	Achtung deutsche Benutzer: Wenn Ihr $wgUseMediaWikiGroups auf "true" setzt, müsst Ihr als Gruppennamen die englischen Namen der Gruppen benutzen, z.B.
	anstatt <accesscontrol>Bürokraten</accesscontrol> muss <accesscontrol>bureaucrats</accesscontrol> benutzt werden!!!
*/
	
	define('ACCESSCONTROL_EXTENSION_VERSION', '0.9');

	# Alert the user that this is not a valid entry point to MediaWiki if they try to access the skin file directly.
	if (!defined('MEDIAWIKI')) {
		echo 'To install this extension, put the following line in LocalSettings.php:<br /><pre>require_once( "$IP/extensions/accesscontrol/accesscontrol.php" );</pre>';
    	exit( 1 );
	}
	
	$dir = dirname(__FILE__) . '/';
	require( $dir.'accesscontrol.i18n.php' );
	require( $dir.'accesscontrol.body.php' );

	function wgAccessControl_controlUserGroupPageAccess( $out )
	{
		global $wgAccessControl;
		return $wgAccessControl->controlUserGroupPageAccess( $out );
	}
	
	function wgAccessControl_hookUserCan(&$title, &$wgUser, $action, &$result)
	{
		global $wgAccessControl;
		return $wgAccessControl->hookUserCan( $title, $wgUser, $action, $result );
	}
	
	function wgAccessControl_controlEditAccess(&$editpage)
	{
		global $wgAccessControl;
		return $wgAccessControl->controlEditAccess( $editpage );
	}
	
	function doControlUserAccess16( $pContent, $pArgv, &$pParser)
	{
		global $wgAccessControl;
		return $wgAccessControl->doControlUserAccess($pContent, $pArgv, $pParser, new Parser() );
	}

	function setNewHook($hookName, $hook)
	{
		if( isset( $wgHooks[$hookName] ) && is_array( $wgHooks[$hookName] ) ) 
		{
			array_unshift( $wgHooks[$hookName], $hook );
		} else {
			$wgHooks[$hookName] = array( $hook );
		}
	}
	
	// This is the hook function. It adds the tag to the wiki parser and
	//tells it what callback function to use.
	function wfAccessControl_Install() {
		global $wgAccessControlDisableMessages, $wgAccessControlAdminCanReadAll, $wgAccessControlUseMediaWikiGroups,
				$wgAccessControlDebug, $wgAccessControlDebugFile, $wgAccessControlAnonymousGroupName, $wgAccessControlPagePrefix,
				$wgAccessControl, $wgParser, $wgHooks, $wgLanguageCode, $wgAccessControlMessages, $wgAccessControlCustomMessages,
				$wgContLang, $wgTitle, $wgUser;
				
		#################################################
		# Variable initializations
		#################################################
		if (!isset($wgAccessControlDisableMessages))
		{
			// if set to false, show a line on top of each secured page, which says, which groups are allowed to see this page.
			$wgAccessControlDisableMessages = false;
		}
		
		if (!isset($wgAccessControlAdminCanReadAll))
		{
			// sysop users can read all restricted pages
			$wgAccessControlAdminCanReadAll = true;
		}
		
		if (!isset($wgAccessControlUseMediaWikiGroups))
		{
			// use the groups from MediaWiki instead of own Usergroup pages
			$wgAccessControlUseMediaWikiGroups = false;
		}
		
		if (!isset($wgAccessControlDebug))
		{
			// Debug log on if set to true
			$wgAccessControlDebug = false;
		}
		
		if (!isset($wgAccessControlDebugFile))
		{
			// Path to the debug log
			$wgAccessControlDebugFile = null;
		}
		
		if (!isset($wgAccessControlAnonymousGroupName))
		{
			// The name of the group the anonymous users belongs to
			$wgAccessControlAnonymousGroupName = "anon";
		}
		
		if (!isset($wgAccessControlPagePrefix))
		{
			$wgAccessControlPagePrefix = "/index.php?title=";
		}
		
		if (!isset($wgAccessControlCustomMessages))
		{
			$wgAccessControlCustomMessages = null;
		}
		
		#################################################
		# Init AccessControl Class
		#################################################
		$wgAccessControl = new AccessControl(
								$wgAccessControlDisableMessages,
								$wgAccessControlAdminCanReadAll,
								$wgAccessControlUseMediaWikiGroups,
								$wgAccessControlDebug,
								$wgAccessControlDebugFile,
								$wgAccessControlAnonymousGroupName,
								$wgAccessControlPagePrefix,
								$wgAccessControlMessages,
								$wgAccessControlCustomMessages,
								$wgLanguageCode,
								$wgContLang,
								$wgTitle,
								$wgUser );
								
		#################################################
		# Hooks
		#################################################
		$wgParser->setHook( "accesscontrol", "doControlUserAccess16" );
		
		// Hook the conTolEditAccess function in the edit action
		setNewHook("AlternateEdit", "wgAccesscontrol_controlEditAccess");
		
		// Hook the userCan function for bypassing the cache (bad bad hackaround)
		setNewHook("userCan", "wgAccesscontrol_hookUserCan");
		
		// Hook the controlUserGroupPageAccess in the Article to allow access to the "Usergroup:..." pages only to the sysop
		setNewHook("ArticleAfterFetchContent", "wgAccesscontrol_controlUserGroupPageAccess");
		
		/*
		function fnSearchUpdate($id, $namespace, $title, $text) { ... }
		$wgHooks['SearchUpdate'][] = 'fnSearchUpdate';
		*/
		
		// $wgSpecialPages['AccessControlUserGroups'] = new SpecialPage('AccessControlUserGroups');
	}

		// Add the hook function call to an array defined earlier in the wiki //code execution.
	$wgExtensionFunctions[] = "wfAccessControl_Install";
		
	$wgExtensionCredits['parserhook'][] = array(
		'name'			=> 'Group Based Access Control',
		'author'		=> 'Martin Gondermann',
		'version'		=> ACCESSCONTROL_EXTENSION_VERSION,
		'url'			=> 'http://blog.pagansoft.de',
		'description'	=> 'Extension to restrict access to specific pages based on groups.'
	);	
?>
