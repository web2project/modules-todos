TodoList v3.0
CaseySoftware, LLC


** v3.0 of this module requires web2project v5.0 or later and PHP 8.3 or later. Use v2.0 for web2project v3.x and v4.x. **

The TodoList module handles simple todo management for web2project. Todo items are different from Tasks in that a todo is either done or not done. They don't have a percent complete or a true duration therefore, they don't make sense as Tasks. These items can optionally be attached to specific Projects, Contacts, or both.

COMPATIBLE VERSIONS

=====================================

* v3.0 of this module has been updated for web2project v5.0 (PHP 8.3+, MySQL 8 strict mode, the Bootstrap theme) and refuses to install on anything older.

* v2.0 of this module added some Getting Things Done concepts in only displaying the next few items for any given project.

* v1.4+ of this module has been validated to work with web2project v3.0-pre and above and is known to be incompatible with earlier releases.

* v1.1 has been validated to work with the current Web2project v2.0 and above.

* This won't work with dotProject, don't even bother trying.

Changelog
=====================================

3.0  Updated for web2project v5.0
-  Requires web2project v5.0+ and PHP 8.3+; loads on PHP 8 again (CTodo::delete() signature);
-  Completing and deleting items works with web2project's form tokens and only hides the item once it is saved;
-  "Later" items are stored as 9999-12-31 instead of 2020-01-01, so they no longer show as Overdue; completing an item keeps its due date;
-  Today, This Week and so on follow the user's timezone and the locale's first day of the week;
-  A todo can only be changed by its owner, by people who can edit its project, or by admins; names are escaped in every list;
-  Bootstrap layout and icons, a native date picker, and an add form that stays in view;
-  The GTD views use a single query and follow the Owner filter; the Todolist tabs on Projects, Contacts and Companies work and return to their page;
-  Upgrades run every step a version needs, work under MySQL 8 strict mode and add indexes;
-  Complete Brazilian Portuguese translation and an English key list for translators.

2.0  Major version update to denote that anything before 3.0-pre is not compatible
-  Tweaked the layouts to use less tables and more simple css;
-  Added some nifty GTD views for all projects or just the active ones;
-  All the database column names have been corrected to our naming conventions, so the HTML Helper is an option.

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

*  The module can't set its own icon in the Bootstrap sidebar, so it shows the generic one until web2project offers a way for add-on modules to do that.

INSTALL

=====================================

0.  Previous installations of this module can simply be overwritten.

1.  To install this module, please follow the standard module installation procedure.  Download the latest version and unzip this directory into your web2project/modules directory.

2.  Go to System Admin -> View Modules and you should see "todos" near the bottom of the list.

3.  On the "Todos" row, select "install".  The screen should refresh.  Now select "disabled" to make it active and "hidden" to make it visible in your module navigation.

4.  Upgrading from an earlier version: overwrite the files, then select "upgrade" on the "Todos" row. Back up the todos table first; the upgrade converts the stored "Later" dates and changes the table.

TESTS

=====================================

The unit tests in todos/unit_tests use web2project's own test setup. With the module installed in modules/todos, run them from web2project's unit_tests directory:

    ../vendor/bin/phpunit --bootstrap ./CommonSetup.php ../modules/todos/unit_tests

To check the module with web2project's static analysis settings, run this from the web2project root:

    vendor/bin/phpstan analyse -c phpstan.neon.dist modules/todos

USAGE

=====================================

1.  Within the TodoList module, fill in the form and hit "save"

2.  If you've attached the todo item to a Project or Contact, it should appear on the related View screens under the proper tabs.

3.  If you use the iCal feed from your web2project system, your todo items should automatically appear in it with no additional configuration.


If you find this module particularly useful and would like to express gratitude, seek additional development, or just write large checks, please do not hesitate to contact me. :)
