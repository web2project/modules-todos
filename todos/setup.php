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
$config['mod_version']     = '3.0.0';
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
    array('require' => 'php',           'comparator' => '>=', 'version' => '8.3'),
    array('require' => 'web2project',   'comparator' => '>=', 'version' => '5')
);

class CSetupTodos extends w2p_System_Setup
{
	public function install()
	{
        // _meetsRequirements() only returns the result of the last check,
        //   so also look at the errors it collected along the way.
        if (!$this->_meetsRequirements() || count((array) $this->getErrors())) {
            return false;
        }

        $q = $this->_getQuery();
        $q->createTable('todos');
        $sql = '(
            `todo_id` int unsigned NOT NULL AUTO_INCREMENT,
            `todo_name` text NOT NULL,
            `todo_due_date` datetime NOT NULL DEFAULT \'' . CTodo::NO_DUE_DATE . '\',
            `todo_project` int NOT NULL DEFAULT 0,
            `todo_owner` int NOT NULL DEFAULT 0,
            `todo_category` int NOT NULL DEFAULT 0,
            `todo_contact` int NOT NULL DEFAULT 0,
            `todo_status` int NOT NULL DEFAULT 1,
            `todo_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `todo_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (`todo_id`),
            KEY `idx_todo_owner` (`todo_owner`, `todo_status`, `todo_due_date`),
            KEY `idx_todo_project` (`todo_project`),
            KEY `idx_todo_contact` (`todo_contact`))
            ENGINE=MyISAM DEFAULT CHARSET=utf8 ';
        $q->createDefinition($sql);
        if (!$q->exec()) {
            return false;
        }

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
            case '1.4':
            case '1.4.1':
            case '2.0':
            case '2.0.0':
                if (!$this->_convertNoDueDates() || !$this->_alignSchema()) {
                    return false;
                }
            case '3.0.0':                                   //current version
            default:
				//do nothing
		}
		return true;
	}

    /**
     * Brings a pre-3.0 table in line with a fresh install: the 1.x -> 2.0
     *   rename created todo_name as a nullable column, and there were no
     *   indexes besides the primary key, while every list query filters on
     *   owner, status and due date.
     *
     * @return bool
     */
    private function _alignSchema()
    {
        $q = $this->_getQuery();
        $q->addTable('todos');
        $q->addUpdate('todo_name', '');
        $q->addWhere('todo_name IS NULL');
        if (!$q->exec()) {
            return false;
        }

        $q->clear();
        $q->alterTable('todos');
        $q->createDefinition(array(
            array('action' => 'MODIFY', 'type' => '', 'spec' => 'todo_name text NOT NULL'),
            array('action' => 'MODIFY', 'type' => '', 'spec' => 'todo_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'),
            array('action' => 'MODIFY', 'type' => '', 'spec' => 'todo_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'),
            array('action' => 'ADD', 'type' => 'INDEX', 'spec' => 'idx_todo_owner (todo_owner, todo_status, todo_due_date)'),
            array('action' => 'ADD', 'type' => 'INDEX', 'spec' => 'idx_todo_project (todo_project)'),
            array('action' => 'ADD', 'type' => 'INDEX', 'spec' => 'idx_todo_contact (todo_contact)'),
        ));

        return (bool) $q->exec();
    }

    /**
     * Before v3.0 "Later" was stored as 2020-01-01 23:59:59 in the user's
     *   timezone (so 2020-01-01 or 2020-01-02 once shifted to UTC), and the
     *   column defaulted to 2038-01-01. 2020 is now in the past, so those
     *   items showed as Overdue. Both become CTodo::NO_DUE_DATE.
     *
     * @return bool
     */
    private function _convertNoDueDates()
    {
        $q = $this->_getQuery();
        $q->alterTable('todos');
        $q->createDefinition(array(array('action' => 'MODIFY', 'type' => '',
            'spec' => "todo_due_date datetime NOT NULL DEFAULT '" . CTodo::NO_DUE_DATE . "'")));
        if (!$q->exec()) {
            return false;
        }

        $q->clear();
        $q->addTable('todos');
        $q->addUpdate('todo_due_date', CTodo::NO_DUE_DATE);
        $q->addWhere("(todo_due_date BETWEEN '2020-01-01 00:00:00' AND '2020-01-02 23:59:59'"
            . " OR todo_due_date = '2038-01-01 00:00:00')");

        return (bool) $q->exec();
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
        // A reinstall after a partial remove would otherwise add a second
        //   copy of every category; keep the existing (maybe edited) list.
        $q = $this->_getQuery();
        $q->addTable('sysvals');
        $q->addQuery('COUNT(*)');
        $q->addWhere("sysval_title = 'TodoType'");
        if ((int) $q->loadResult() > 0) {
            return true;
        }

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
        $q->addField('todo_project', 'int NOT NULL DEFAULT 0');
        $q->addField('todo_owner', 'int NOT NULL DEFAULT 0');
        $q->addField('todo_category', 'int NOT NULL DEFAULT 0');
        $q->addField('todo_contact', 'int NOT NULL DEFAULT 0');
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