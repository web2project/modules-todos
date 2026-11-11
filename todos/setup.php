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
$config['mod_version']     = '3.0.1';
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
    /**
     * w2p_System_Setup's constructor sets $this->_query without declaring
     *   it, which is a dynamic property deprecation on PHP 8.2+ (core
     *   issue #550). Declaring it here keeps install, upgrade and remove
     *   quiet until core declares it.
     *
     * @var w2p_Database_Query
     */
    protected $_query;

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
        // Each step runs for every release older than the one that made the
        //   change, so point releases (1.1.2, 1.4.1, 2.0) aren't skipped.
        //   Thresholds have two parts on purpose: version_compare() puts
        //   '1.4' before '1.4.0'.
        if (version_compare($old_version, '3.0.1', '<')) {
            // First, because any other ALTER on a table that still has
            //   todo_closed fails under MySQL 8 strict mode (see below).
            //   Also runs for 3.0.0, which kept the column.
            if (!$this->_dropLegacyClosedColumn()) {
                return false;
            }
        }
        if (version_compare($old_version, '1.1', '<')) {
            // 1.1 moved the hardcoded categories into System Lookup Values
            $this->_convertCategoriesToSysvals();
        }
        if (version_compare($old_version, '1.4', '<')) {
            // 1.4 renamed the columns to the core naming conventions
            if (!$this->_renameFieldsToMatchConventions()) {
                return false;
            }
        }
        if (version_compare($old_version, '3.0', '<')) {
            if (!$this->_convertNoDueDates() || !$this->_alignSchema()) {
                return false;
            }
        }

		return true;
	}

    /**
     * Removes todo_closed, a column that installs from 1.3 and earlier
     *   still have: "datetime NOT NULL" with no default. Nothing has used
     *   it since 1.4, but the column was never dropped. Under MySQL 8
     *   strict mode it breaks the module in two ways:
     *   - adding a todo fails with "Field 'todo_closed' doesn't have a
     *     default value", because the insert doesn't name it, and
     *   - every open todo holds 0000-00-00 there (MySQL 5 filled that
     *     in), so any ALTER TABLE that copies the table fails, which
     *     stops the other upgrade steps.
     *
     * A real closed date is kept in todo_updated, which the Closed Items
     *   tab sorts by. Zero dates in the remaining datetime columns are
     *   cleared first, because they also make the DROP's table copy fail.
     *
     * @return bool
     */
    private function _dropLegacyClosedColumn()
    {
        $columns = $this->_todoColumns();
        if (!in_array('todo_closed', $columns, true)) {
            return true;
        }

        $q = $this->_getQuery();
        $q->addTable('todos');
        $q->addUpdate('todo_updated', 'todo_closed', false, true);
        $q->addWhere("todo_status = 0 AND todo_closed >= '1000-01-01'");
        if (!$q->exec()) {
            return false;
        }

        // the due date column is todo_due before 1.4 and todo_due_date after
        $candidates = array('todo_due' => "'" . CTodo::NO_DUE_DATE . "'",
            'todo_due_date' => "'" . CTodo::NO_DUE_DATE . "'",
            'todo_created' => 'NOW()', 'todo_updated' => 'todo_created');
        $zeroDates = array();
        foreach ($candidates as $column => $value) {
            if (in_array($column, $columns, true)) {
                $zeroDates[$column] = $value;
            }
        }
        if (!$this->_clearZeroDates($zeroDates)) {
            return false;
        }

        return $this->_alterTodos(array(array('DROP', 'todo_closed')));
    }

    /**
     * The todos table's column names, in lower case.
     *
     * @return string[]
     */
    private function _todoColumns()
    {
        $names = $this->_getQuery()->_db->MetaColumnNames('todos');

        return array_map('strtolower', array_values(is_array($names) ? $names : array()));
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
        // a 2.x table converted from 1.x under MySQL 5 can still hold zero
        //   dates, which would make the ALTER below fail
        $zeroDates = array('todo_due_date' => "'" . CTodo::NO_DUE_DATE . "'",
            'todo_created' => 'NOW()', 'todo_updated' => 'todo_created');
        if (!$this->_clearZeroDates($zeroDates)) {
            return false;
        }

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

    /**
     * @return bool
     */
    private function _renameFieldsToMatchConventions()
    {
        // 1.x tables come from MySQL 5 and can hold 0000-00-00, as values
        //   and as column defaults. Under MySQL 8 strict mode any ALTER on
        //   such a table fails, so clear the values first and replace the
        //   defaults in the same ALTER that adds the new columns (the
        //   approach of core's install/sql/056).
        $zeroDates = array('todo_due' => "'2038-01-01'", 'todo_created' => 'NOW()',
            'todo_updated' => 'todo_created');
        if (!$this->_clearZeroDates($zeroDates)) {
            return false;
        }
        $alter = array(
            array('MODIFY', "todo_due datetime NOT NULL DEFAULT '2038-01-01'"),
            array('MODIFY', 'todo_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'),
            array('MODIFY', 'todo_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'),
            array('ADD', 'todo_name text'),
            array('ADD', "todo_due_date datetime NOT NULL DEFAULT '2038-01-01'"),
            array('ADD', 'todo_project int NOT NULL DEFAULT 0'),
            array('ADD', 'todo_owner int NOT NULL DEFAULT 0'),
            array('ADD', 'todo_category int NOT NULL DEFAULT 0'),
            array('ADD', 'todo_contact int NOT NULL DEFAULT 0'),
        );
        if (!$this->_alterTodos($alter)) {
            return false;
        }

        $convert = array('todo_title' => 'todo_name',
            'todo_due' => 'todo_due_date',
            'todo_project_id' => 'todo_project',
            'todo_user_id' => 'todo_owner',
            'todo_category_id' => 'todo_category',
            'todo_related_to_contact_id' => 'todo_contact',
        );

        $q = $this->_getQuery();
        foreach($convert as $from => $to) {
            $q->clear();
            $q->addTable('todos');
            $q->addUpdate($to, $from, false, true);
            if (!$q->exec()) {
                return false;
            }
        }

        $drop = array();
        foreach (array_keys($convert) as $column) {
            $drop[] = array('DROP', $column);
        }

        return $this->_alterTodos($drop);
    }

    /**
     * Replaces NULL and 0000-00-00 values, which make any ALTER fail under
     *   MySQL 8 strict mode.
     *
     * @param array $columns column => SQL expression for the new value
     *
     * @return bool
     */
    private function _clearZeroDates(array $columns)
    {
        $q = $this->_getQuery();
        foreach ($columns as $column => $value) {
            $q->clear();
            $q->addTable('todos');
            $q->addUpdate($column, $value, false, true);
            $q->addWhere($column . ' IS NULL OR CAST(' . $column . " AS CHAR(20)) LIKE '0000-00-00%'");
            if (!$q->exec()) {
                return false;
            }
        }

        return true;
    }

    /**
     * One ALTER TABLE on todos. Built with createDefinition() because
     *   addField()/dropField() are deprecated.
     *
     * @param array $changes list of array(action, column spec)
     *
     * @return bool
     */
    private function _alterTodos(array $changes)
    {
        $definition = array();
        foreach ($changes as $change) {
            $definition[] = array('action' => $change[0], 'type' => '', 'spec' => $change[1]);
        }

        $q = $this->_getQuery();
        $q->alterTable('todos');
        $q->createDefinition($definition);

        return (bool) $q->exec();
    }
}