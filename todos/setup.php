<?php
if (!defined('W2P_BASE_DIR')){
  die('You should not access this file directly.');
}
/**
 * Name:        TodoList
 * Directory:   todolist
 * Type:        user
 * UI Name:     todolist
 * UI Icon:     ?
 */

$config = array();
$config['mod_name']        = 'Todos';
$config['mod_version']     = '1.4.1';
$config['mod_directory']   = 'todos';               // tell web2project where to find this module
$config['mod_setup_class'] = 'CSetupTodos';         // the name of the PHP setup class (used below)
$config['mod_type']        = 'user';                // 'core' for modules distributed with w2p by standard, 'user' for additional modules
$config['mod_ui_name']	   = $config['mod_name'];   // the name that is shown in the main menu of the User Interface
$config['mod_ui_icon']     = '';                    // name of a related icon
$config['mod_description'] = 'Todo List';           // some description of the module
$config['mod_config']      = false;                 // show 'configure' link in viewmods
$config['mod_main_class']  = 'CTodo';

$config['permissions_item_table'] = 'todos';
$config['permissions_item_field'] = 'todo_id';
$config['permissions_item_label'] = 'todo_name';

$config['requirements'] = array(
    array('require' => 'web2project',   'comparator' => '>=', 'version' => '3')
);  

class CSetupTodos extends w2p_Core_Setup
{
	public function install()
	{ 
        $result = $this->_checkRequirements();

        if (!$result) {
            return false;
        }

        $q = $this->_getQuery();
        $q->createTable('todos');
        $sql = '(
            `todo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `todo_name` text NOT NULL,
            `todo_due_date` datetime NOT NULL DEFAULT \'2038-01-01\',
            `todo_project` int(10) NOT NULL DEFAULT 0,
            `todo_owner` int(10) NOT NULL DEFAULT 0,
            `todo_category` int(10) NOT NULL DEFAULT 0,
            `todo_contact` int(10) NOT NULL DEFAULT 0,
            `todo_status` int(1) NOT NULL DEFAULT 1,
            `todo_created` datetime NOT NULL,
            `todo_updated` datetime NOT NULL,
            PRIMARY KEY  (`todo_id`))
            ENGINE=MyISAM DEFAULT CHARSET=utf8 ';
        $q->createDefinition($sql);
        $q->exec();

        $this->_convertCategoriesToSysvals();

        return parent::install();
	}

	public function upgrade($old_version)
	{
        switch ($old_version) {
            case '0.5':
            case '0.5.0':
            case '1.0.0':
            case '1.0.1':
                $this->_convertCategoriesToSysvals();
            case '1.2.0':
            case '1.3.0':
                $this->_renameFieldsToMatchConventions();
            case '1.4':                                     //current version
            default:
				//do nothing
		}
		return true;
	}

	public function remove()
	{ 
        $q = $this->_getQuery();
		$q->dropTable('todos');
        $q->exec();

		$q->clear();
		$q->setDelete('sysvals');
		$q->addWhere("sysval_title = 'TodoType'");
        $q->exec();

        return parent::remove();
	}

    private function _convertCategoriesToSysvals()
    {
        $i = 1;
        $todoCategories = array('Admin', 'Billing', 'Call', 'Config', 'Dev',
            'Email', 'Evaluation', 'Follow-up', 'Meeting', 'Personal',
            'Pitch/Proposal', 'Research', 'Writing');
        foreach ($todoCategories as $category) {
            $q = $this->_getQuery();
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

    private function _renameFieldsToMatchConventions()
    {
        $q = $this->_getQuery();
        $q->alterTable('todos');
        $q->addField('todo_name', 'text');
        $q->addField('todo_due_date', "datetime NOT NULL DEFAULT '2038-01-01'");
        $q->addField('todo_project', 'int(10) NOT NULL DEFAULT 0');
        $q->addField('todo_owner', 'int(10) NOT NULL DEFAULT 0');
        $q->addField('todo_category', 'int(10) NOT NULL DEFAULT 0');
        $q->addField('todo_contact', 'int(10) NOT NULL DEFAULT 0');
        $q->exec();

        $convert = array('todo_title' => 'todo_name',
            'todo_due' => 'todo_due_date',
            'todo_project_id' => 'todo_project',
            'todo_user_id' => 'todo_owner',
            'todo_category_id' => 'todo_category',
            'todo_related_to_contact_id' => 'todo_contact',
        );

        foreach($convert as $from => $to) {
            $q->clear();
            $q->addTable('todos');
            $q->addUpdate($to, $from, false, true);
            $q->exec();
        }

        $q->clear();
        $q->alterTable('todos');
        $q->dropField('todo_title');
        $q->dropField('todo_due');
        $q->dropField('todo_project_id');
        $q->dropField('todo_user_id');
        $q->dropField('todo_category_id');
        $q->dropField('todo_related_to_contact_id');
        $q->exec();
    }
}