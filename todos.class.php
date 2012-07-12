<?php
if (!defined('W2P_BASE_DIR'))
{
  die('You should not access this file directly.');
}
##
## TodoList Class
##

class CTodo extends w2p_Core_BaseObject
{
	public $todo_id = 0;
	public $todo_title = '';
	public $todo_due = '';
	public $todo_project_id = 0;
	public $todo_user_id = 0;
	public $todo_category_id = 0;
	public $todo_related_to_contact_id = 0;
	public $todo_status = 1;
	public $todo_created = NULL;
	public $todo_updated = NULL;
	public $todo_closed = NULL;

	public function __construct()
	{
		parent::__construct('todos', 'todo_id');
	}

	public function getClosedTodosForDateRange($user_id = 0)
    {
		$user_id = ($user_id) ? $user_id : $this->_AppUI->user_id;

        $q = $this->_getQuery();
		$q->addQuery('st.*');
		$q->addTable('todos', 'st');

		$q->addWhere('st.todo_status = 0');
		$q->addWhere("st.todo_user_id = $user_id");

        $q->addQuery('p.project_name, p.project_color_identifier, p.project_company');
        $q->leftJoin('projects', 'p', 'p.project_id = st.todo_project_id');

        $q->addQuery('c.contact_first_name, c.contact_last_name');
        $q->leftJoin('contacts', 'c', 'c.contact_id = st.todo_related_to_contact_id');

        $projObj = new CProject();
        $projObj->setAllowedSQL($this->_AppUI->user_id, $q, null, 'p');

        $q->addOrder('st.todo_closed DESC, p.project_name, st.todo_title');

        return $q->loadList();
	}

	public function getTodosForDateRange($dateRangeName, $user_id = 0, $project_id = 0, $contact_id = 0, $company_id = 0)
	{
        $today = date('Y-m-d 23:59:59');
        $today = $this->_AppUI->convertToSystemTZ($today);

        $tomorrow = date('Y-m-d 23:59:59', strtotime('tomorrow'));
        $tomorrow = $this->_AppUI->convertToSystemTZ($tomorrow);

        $dayAfterTomorrow = date('Y-m-d 23:59:59', strtotime('tomorrow +1 day'));
        $dayAfterTomorrow = $this->_AppUI->convertToSystemTZ($dayAfterTomorrow);
//TODO: this should grab the 'end of week' variable from CORE
        $thisSunday = date('Y-m-d 23:59:59', strtotime('this Sunday +1 day'));
        $thisSunday = $this->_AppUI->convertToSystemTZ($thisSunday);
        
        $nextSunday = date('Y-m-d 23:59:59', strtotime('this Sunday +8 day'));
        $nextSunday = $this->_AppUI->convertToSystemTZ($nextSunday);

        $q = $this->_getQuery();
		$q->addQuery('st.*');
		$q->addTable('todos', 'st');
		switch ($dateRangeName) {
			case 'overdue':
				$q->addWhere("st.todo_due < '$today'");
				break;
			case 'today':
				$q->addWhere("st.todo_due >= '$today'");
                $q->addWhere("st.todo_due < '$tomorrow'");
				break;
			case 'tomorrow':
                $q->addWhere("st.todo_due >= '$tomorrow'");
                $q->addWhere("st.todo_due < '$dayAfterTomorrow'");
				break;
			case 'this-week':
                $q->addWhere("st.todo_due >= '$dayAfterTomorrow'");
                $q->addWhere("st.todo_due < '$thisSunday'");
				break;
			case 'next-week':
                $q->addWhere("st.todo_due > '$thisSunday'");
                $q->addWhere("st.todo_due <= '$nextSunday'");
				break;
			case 'later':
            default:
                $q->addWhere("st.todo_due > '$nextSunday'");
		}
		$q->addWhere('st.todo_status = 1');
        $q->addWhere("st.todo_user_id = ".(($user_id > 0) ? $user_id : $this->_AppUI->user_id));

        $q->addQuery('p.project_name, p.project_color_identifier, p.project_company');
        $q->leftJoin('projects', 'p', 'p.project_id = st.todo_project_id');

        $projObj = new CProject();
        $projObj->setAllowedSQL($this->_AppUI->user_id, $q, null, 'p');
        if ($project_id > 0 && $this->_perms->checkModuleItem('projects', 'view', $project_id)) {
            $q->addWhere("st.todo_project_id = $project_id");
        }

        if ($company_id > 0 && $this->_perms->checkModuleItem('companies', 'view', $company_id)) {
            $projects = CCompany::getProjects($this->_AppUI, $company_id);
            $project_id_string = '';
            foreach ($projects as $project) {
                $project_id_string .= $project['project_id'].',';
            }
            $project_id_string .= '-1';
            $q->addWhere("st.todo_project_id IN ($project_id_string)");
        }

        if ($contact_id > 0 && $this->_perms->checkModuleItem('contacts', 'view', $contact_id)) {
            $q->addWhere("st.todo_related_to_contact_id = $contact_id");
        }
		$q->addOrder('st.todo_due, p.project_name, st.todo_title');

		return $q->loadList();
	}

	public function getOpenTodoItems($userId, $days = 30)
    {
		/*
		 *  The  "description" field purposely not included as we don't have that
		 * field on this table.  This list of fields - id, name, description,
		 * startDate, endDate, updatedDate - are named specifically for the iCal
		 * creation. If you change them, it's probably going to break.  So don't do
		 * that.
		 */

		$q = $this->_getQuery();
		$q->addQuery('todo_id as id');
		$q->addQuery('todo_title as name');
        $q->addQuery('todo_title as description');
        $q->addQuery('todo_project_id as project_id');
		$q->addQuery("todo_due as startDate");
        $q->addQuery("todo_due as endDate");
		$q->addQuery("todo_updated as updatedDate");
        $q->addQuery('CONCAT(\''. W2P_BASE_URL . '/index.php?m=todos&todo_id=' . '\', todo_id) as url');
        $q->addQuery('p.project_id, p.project_name');
        $q->addJoin('projects', 'p', 'p.project_id = todo_project_id');
        
		$q->addTable('todos');
		$q->addWhere("todo_due < DATE_ADD(CURDATE(), INTERVAL $days DAY)");
		$q->addWhere("todo_user_id = $userId");
		$q->addWhere("todo_status = 1");
		$q->addOrder('todo_due');

		return $q->loadList();
	} 

	public function complete()
	{
		$this->load();
        $q = $this->_getQuery();
        $this->todo_closed = $q->dbfnNowWithTZ();
        $this->todo_status = 0;

		return $this->store();
	}

	public function delete()
	{
		$this->load();
		$this->todo_status = -1;

		return $this->store();
	}

    protected function hook_preCreate()
    {
        $q = $this->_getQuery();
        $this->todo_created = $q->dbfnNowWithTZ();
        $this->todo_closed = null;

        parent::hook_preCreate();
    }

    protected function  hook_preStore()
    {
        $q = $this->_getQuery();
        $this->todo_updated = $q->dbfnNowWithTZ();
        $this->todo_due = $this->resolveTimeframeEnd();
        $this->todo_due = $this->_AppUI->convertToSystemTZ($this->todo_due);

        parent::hook_preStore();
    }

	public function renderTimeframe()
    {
		$dateInfo = array();
		
		if ($this->todo_due != '') {
			$timeString = strtotime($this->todo_due);
			if ($timeString > time()) {
				//TODO: if the date translates to something easy like today or tomorrow, it should be set accordingly
				$dateInfo['displayName'] = 'other';
				$dateInfo['cleanFormat'] = date('Ymd', $timeString);
				$dateInfo['displayFormat'] = date('Y/M/d', $timeString);
			} else {
				$dateInfo['displayName'] = 'today';
			}
		} else {
			$dateInfo['displayName'] = 'today';
			$dateInfo['cleanFormat'] = '';
			$dateInfo['displayFormat'] = '';
		} 
		return $dateInfo;
	}

	public function getTimeframes()
    {
        $todoTimeframes = array('overdue' => $this->_AppUI->_('Overdue'),
            'today' => $this->_AppUI->_('Today'), 'tomorrow' => $this->_AppUI->_('Tomorrow'),
            'this-week' => $this->_AppUI->_('This Week'),
            'next-week' => $this->_AppUI->_('Next Week'), 'later' => $this->_AppUI->_('Later'));
		return $todoTimeframes;
	}

	public function getAllowedProjects($user_id)
    {
        $project = new CProject();
        $projects = $project->getAllowedProjects($user_id);

        foreach ($projects as $project_id => $project_info) {
            $projectList[$project_id] = $project_info['project_name'];
        }

		return $projectList;
	}

	public function getContacts()
    {
		//TODO: this should be converted to a core function
		$company = new CCompany;
		$allowedCompanies = $company->getAllowedSQL($this->_AppUI->user_id);

		$q = $this->_getQuery();
		$q->addQuery('contact_id, contact_order_by');
		$q->addQuery('contact_first_name, contact_last_name');
		$q->addTable('contacts');
		$q->addWhere('
			(contact_private=0
				OR (contact_private=1 AND contact_owner=' . $this->_AppUI->user_id . ')
				OR contact_owner IS NULL OR contact_owner = 0
			)');
		$q->addWhere('contact_first_name IS NOT NULL');
		$q->addWhere('contact_last_name IS NOT NULL');
		//TODO: add filter for companies ACL
		$q->addOrder('contact_first_name');
		$q->addOrder('contact_last_name');
		
		$allowedContacts = array(0 => ' ');
		$allowedContacts = $allowedContacts + $q->loadHashList();
		
		return $allowedContacts;
	}

	protected function resolveTimeframeEnd()
	{

        switch ($this->todo_due) {
			case 'other':
				$endDate = (int) w2PgetParam($_POST, 'display_todo_date', '2100-01-01');
				if ($endDate != '2020-01-01') {
					$endDate = substr($endDate, 0, 4).'-'.substr($endDate, 4, 2).'-'.substr($endDate, 6, 2);
				}
				break;
			case 'today':
				$endDate = date('Y-m-d');
				break;
			case 'tomorrow':
				$endDate = date('Y-m-d', strtotime('tomorrow'));
				break;
			case 'this-week':
				//TODO: this should grab the 'end of week' variable from CORE
				if (date('w') == 0) {
					$endDate = date('Y-m-d');
				} else {
					$endDate = date('Y-m', strtotime('next Sunday')).date('-d', strtotime('next Sunday'));
				}
				break;
			case 'next-week':
				//TODO: this should grab the 'end of week' variable from CORE
				if (date('w') == 0) {
					$mondayAfterNext = strtotime('+7 day');
				} else {
					$mondayAfterNext = strtotime('+7 day', strtotime('next Sunday'));
				}
				$endDate = date('Y-m', $mondayAfterNext).date('-d', $mondayAfterNext);
				break;
			default:
				//defaults to later scenario
				$endDate = '2020-01-01';
				break;
		}

		return $endDate.' 23:59:59';
	}

	public function hook_calendar($userId)
    {
		return $this->getOpenTodoItems($userId);
	}

    public function hook_search()
    {
        $search['table'] = 'todos';
        $search['table_alias'] = 't';
        $search['table_module'] = 'todos';
        $search['table_key'] = $search['table_alias'].'.todo_id'; // primary key in searched table
        $search['table_link'] = 'index.php?m=todos&todo_id='; // first part of link
        $search['table_title'] = 'Todos';
        $search['table_orderby'] = 'todo_title';
        $search['search_fields'] = array('todo_title');
        $search['display_fields'] = $search['search_fields'];

        return $search;
    }
}
