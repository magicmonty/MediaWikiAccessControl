<?php
        // MediaWiki extension that enables group access restriction on a page-by-page
        // basis
        // contributed by Martin Mueller (http://blog.pagansoft.de)
        // based on accesscontrol.php by Josh Greenberg

        // INSTALLATION:
        //
        // Step 1:
        // add the following lines to the bottom of your LocalSettings.php:
        // $wgAccessControlDisableMessages = false; // if false, show a Line on Top of each secured Page, which says, which Groups are allowed to see this page.
        // $wgAccessControlGroupPrefix = "Usergroup"; // The Prefix for the Usergroup-Pages
        // $wgAccessControlNoAccessPage = "/index.php/No_Access"; // To this Page will these users redirected who ar not allowed to see the page.
        //
        // Step 2:
        // Create a Wiki-Page with the Name Usergroup:Groupname and add the Users in the Group in a Bulletlist
        // Example: You want the Group "IT-Department" with the Users "John Doe" and "Jane Doe" in it:
        // Create the Wiki-Article: "Usergroup:IT-Department" and put the following in it's text:
        // *John Doe
        // *Jane Doe
        //
        // Step 3: Create a No-Access Page with the Name No_Access and Write some Text in it (i.e. "Access to this page is denied for you!"

        // That's it for the installation. To restrict access on a page-by-page basis to specific usergroups, just include the names of the allowed usergroups within an tag (separated by double commas) in the body of that page. Thus, if you wanted to restrict access to the people with usergroups "Administrators", "IT-Department" and "Sales", you would use the following syntax:
        // <accesscontrol>Administrators,,IT-Department,,Sales</accesscontrol>

        //Add the hook function call to an array defined earlier in the wiki //code execution.
        $wgExtensionFunctions[] = "wfAccessControl";


        //This is the hook function. It adds the tag to the wiki parser and
        //tells it what callback function to use.
        function wfAccessControl()
        {
                global $wgParser;
                # register the extension with the WikiText parser
                $wgParser->setHook( "accesscontrol", "controlUserAccess" );
        }

        // The callback function for user access
        function controlUserAccess( $input )
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
                        $groupPage = new Article( $groupTitle, 0 );
                        $allowedUsers=$groupPage->getPreloadedText($groupTitle);
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
                        // redirect to the no-access-page if current user doesn't match the
                        // accesscontrol list
                        $wgOut->redirect($wgAccessControlNoAccessPage);
                }
                else
                {
                        if (($groupCount>0) && (!$wgAccessControlDisableMessages))
                        {
                                // output the little Message, if $wgAccessControlDisableMessages is not set
                                $style = "<font style=\"font-size:8pt\">";
                                $style_end = "</font>";

                                if ( $groupCount == 1 )
                                        return( $wgOut->parse($style."Diese Seite ist nur f&uuml;r die Gruppe ".$allowedGroups." zug&auml;nglich!!!".$style_end) );
                                else
                                        return( $wgOut->parse($style."Diese Seite ist nur f&uuml;r die Gruppe(n) ".$allowedGroups." zug&auml;nglich!!!".$style_end) );
                        }
                        else
                        {
                                return("");
                        }
                }
        }
?>