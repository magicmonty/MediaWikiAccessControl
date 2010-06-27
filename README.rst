=================================================
Group Based AccessControl Extension for MediaWiki
=================================================

Features
--------
 
* easy to setup
* no patches, real extension
* as many groups as you want
* access is controlled for viewing and editing the pages (also if you access it manually per action=edit in the URL)
* only sysops can view and edit the special "Usergroup:.." pages
* All users in the sysop-Group (the one from mediaWiki) can see and edit the protected pages, so if you made a mistake, you can always correct it, even if the page is protected
* access can be granted to multiple groups
* read only access is also possible
* You can alternatively use the internal groups from your wiki for access control

Caveats
-------

* Requires that you disable caching, otherwise the restrictions aren't consistently enforced
* Users can still use search feature to find unauthorized pages and see excerpts in the search results

=============
Documentation
=============

Installation
------------

### Step 1:
* add the following lines to the bottom of your LocalSettings.php:
  ::	
  	require_once("extensions/accesscontrolSettings.php");
  	include("extensions/accesscontrol.php");
	
### Step 2:
* check (and maybe edit) the settings in accesscontrolSettings.php:
  ::	
  	$wgAccessControlDisableMessages = false; // if false, show a Line on Top of each secured Page, which says, which Groups are allowed to see this page.
  	$wgAccessControlGroupPrefix = "Usergroup"; // The Prefix for the Usergroup-Pages
  	$wgAccessControlNoAccessPage = $wgScriptPath . "/index.php/No_Access"; // To this Page will these users redirected who are not allowed to see the page.
  	$wgWikiVersion = 1.6; // Set this to 1.7, if you use mediaWiki 1.7 or greater, this is for compatibility reasons
  	$wgUseMediaWikiGroups = false; // use the groups from MediaWiki instead of own Usergroup pages
  	$wgAdminCanReadAll = true; // sysop users can read all restricted pages
  	$wgGroupLineText = "This page is only accessible for group %s !!!"; // The text for the showing on the restricted pages, for one group
  	$wgGroupsLineText = "This page is only accessible for group %s !!!"; // The text for the showing on the restricted pages, for more than one group
  	$wgAccesscontrolDebug = false;	// Debug log on
  	$wgAccesscontrolDebugFile = "/var/www/wiki/config/debug.txt"; // Path to the debug log
i
### Step 3:
* Create a Wiki-Page with the Name Usergroup:Groupname and add the Users in the Group in a Bulletlist
* Example: You want the Group "IT-Department" with the Users "John Doe" and "Jane Doe" in it:
*
  * Create the Wiki-Article: "Usergroup:IT-Department" and put the following in its text:
    ::	
    	*John Doe
    	*Jane Doe

There is a second possibility for using groups. You can set the variable $wgUseMediaWikiGroups to true and use the internal groups from MediaWiki. Then you can use the special page "Special:Userrights"
for Useradministration and you don't have to create the Usergroup pages.

### Step 4:
* Create a No-Access Page with the Name No_Access and Write some Text in it (i.e. "Access to this page is denied for you!")
* If you want to protect the page, so the users of a group can read but not edit, you have to append "(ro)" to the group name.
* Example:
  ::	
  	<accesscontrol>Administrators,,IT-Department(ro),,Sales(ro)</accesscontrol>
  		
  In this example all users from the groups "Administrators", "IT-Department" and "Sales" can read the page but only the users from "Administrators" can edit it.
  
* Attention for the german users: if you set $wgUseMediaWikiGroups to true then you have to use the english names for the groups i.E., instead of writing
  ::	
  	<accesscontrol>Bürokraten</accesscontrol>
  	
  you have to write
  	
  	<accesscontrol>bureaucrats</accesscontrol>
	
Usage
-----

That's it for the installation. To restrict access on a page-by-page basis to specific usergroups, just include the names of the allowed usergroups within a tag (separated by double commas) in the body of that page. Thus, if you wanted to restrict access to the people with usergroups "Administrators", "IT-Department" and "Sales", you would use the following syntax:
::	
	<accesscontrol>Administrators,,IT-Department,,Sales</accesscontrol>
	

Custom urls
-----------

By default the extension links to /index.php/Page, for example for the group links. If you've customized your mediawiki to listen to a different url scheme, you have to find the following line in accesscontrol.php:
::	
	// return the HTML link
	return "<a href=\"/index.php/$linkTitle\" title=\"$linkTitle\">$linkName</a>";
	
Change 'index.php' to the links that your wiki is using.


