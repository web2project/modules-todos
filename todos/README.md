TodoList v2.0
CaseySoftware, LLC
webmaster@caseysoftware.com

** This module will not work with any version of web2project prior to v3.0! **

The TodoList module handles simple todo management for web2project. These todo items are usually things that are either done or not done in minutes as opposed to hours or days. Therefore, they don't make sense as Tasks. These items can optionally be attached to specific Projects, Contacts, or both.

COMPATIBLE VERSIONS

=====================================

*  v1.4+ of this module has been validated to work with web2project v3.0-pre and above and is known to be incompatible with earlier releases.

*  v1.1 has been validated to work with the current Web2project v2.0 and above.

*  This won't work with dotProject, don't even bother trying.

Changelog
=====================================

2.0  Major version update to denote that anything before 3.0-pre is not compatible
-  Tweaked the layouts to use less tables and more simple css;

1.4/1.3 Updated to use all of the new web2project v3.0 functionality
-  This is incompatible with any version before pre-3.0;
-  Makes use of all pre/post hooks;

1.2 Updated to use web2project v3.0 functionality
-  Various css tweaks to simplify the styles;
-  Removed the variable passing rendered unneeded in web2project v3.0+;
-  Removed the old store() method in favor of using our pre/post hooks;

1.1 Minor configuration tweaks
-  On deleting an item, the screen scrolls up to the top of the page;
-  The Category List is now stored in the System Lookup Values as opposed to being hardcoded in the CTodo class;

KNOWN ISSUES

=====================================

Open Issues:

*  The column names don't follow our naming conventions, so the HTMLHelper won't be useful here.

*  The Add Edit form is static and attached to the top of the page by default, this should be moved to track the user.

INSTALL

=====================================

0.  Previous installations of this module can simply be overwritten.

1.  To install this module, please follow the standard module installation procedure.  Download the latest version and unzip this directory into your web2project/modules directory.

2.  Select to System Admin -> View Modules and you should see "todos" near the bottom of the list.

3.  On the "TodoList" row, select "install".  The screen should refresh.  Now select "hidden" and then "disabled" from the same row to make it display in your module navigation.

USAGE

=====================================

1.  Within the TodoList module, fill in the form and hit "save!"

2.  If you've attached the todo item to a Project or Contact, it should appear on the related View screens under the proper tabs.

3.  If you use the iCal feed from your web2project system, your todo items should automatically appear in it with no additional configuration.


If you find this module particularly useful and would like to express gratitude, seek additional development, or just write large checks, please do not hesitate to contact CaseySoftware, LLC via webmaster@caseysoftware.com
