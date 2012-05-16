<?php
if (!defined('W2P_BASE_DIR')){
  die('You should not access this file directly.');
}
/**
 * Name:			TodoList
 * Directory: todolist
 * Type:			user
 * UI Name:		todolist
 * UI Icon: 	?
 */

$config = array();
$config['mod_name']        = 'TodoList';			    // name the module
$config['mod_version']     = '1.2.0';			      	// add a version number
$config['mod_directory']   = 'todos';             // tell web2project where to find this module
$config['mod_setup_class'] = 'CSetupTodoList';		// the name of the PHP setup class (used below)
$config['mod_type']        = 'user';				      // 'core' for modules distributed with w2p by standard, 'user' for additional modules
$config['mod_ui_name']	   = $config['mod_name']; // the name that is shown in the main menu of the User Interface
$config['mod_ui_icon']     = '';                  // name of a related icon
$config['mod_description'] = 'Todo List';			    // some description of the module
$config['mod_config']      = false;					      // show 'configure' link in viewmods
$config['mod_main_class']  = 'CTodo';

$config['permissions_item_table'] = 'todos';
$config['permissions_item_field'] = 'todo_id';
$config['permissions_item_label'] = 'todo_title';

class CSetupTodoList
{
	public function install()
	{ 
		global $AppUI;

        $q = new w2p_Database_Query();
		$q->createTable('todos');
		$sql = '(
			`todo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`todo_title` text NOT NULL,
			`todo_due` datetime NOT NULL DEFAULT \'2038-01-01\',
			`todo_project_id` int(10) NOT NULL DEFAULT 0,
			`todo_user_id` int(10) NOT NULL DEFAULT 0,
			`todo_category_id` int(10) NOT NULL DEFAULT 0,
			`todo_related_to_contact_id` int(10) NOT NULL DEFAULT 0,
			`todo_status` int(1) NOT NULL DEFAULT 1,
			`todo_created` datetime NOT NULL,
			`todo_updated` datetime NOT NULL,
			`todo_closed` datetime NOT NULL,
			PRIMARY KEY  (`todo_id`))
			TYPE=MyISAM';
		$q->createDefinition($sql);
		$q->exec();

        $this->addCategories();

        $perms = $AppUI->acl();
        return $perms->registerModule('Todolist', 'todos');
	}

	public function upgrade($old_version)
	{
        switch ($old_version) {
            case '0.5':
            case '0.5.0':
            case '1.0.0':
            case '1.0.1':
                //todo add categories
                $this->addCategories();
            default:
				//do nothing
		}
		return true;
	}

	public function remove()
	{ 
		global $AppUI;

        $q = new w2p_Database_Query;
		$q->dropTable('todos');
		$q->exec();

		$q->setDelete('sysvals');
		$q->addWhere("sysval_title = 'TodoType'");
		$q->exec();

        $perms = $AppUI->acl();
        return $perms->unregisterModule('todos');
	}

    private function addCategories()
    {
        $q = new w2p_Database_Query();

        $i = 1;
        $todoCategories = array('Admin', 'Billing', 'Call', 'Config', 'Dev',
            'Email', 'Evaluation', 'Follow-up', 'Meeting', 'Personal',
            'Pitch/Proposal', 'Research', 'Writing');
        foreach ($todoCategories as $category) {
            $q->addTable('sysvals');
            $q->addInsert('sysval_key_id', 1);
            $q->addInsert('sysval_title', 'TodoType');
            $q->addInsert('sysval_value', $category);
            $q->addInsert('sysval_value_id', $i);
            $q->exec();
            $q->clear();
            $i++;
        }
        return true;
    }
}
