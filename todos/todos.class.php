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
	public $todo_name = '';
	public $todo_due_date = '';
	public $todo_project = 0;
	public $todo_owner = 0;
	public $todo_category = 0;
	public $todo_contact = 0;
	public $todo_status = 1;
	public $todo_created = NULL;
	public $todo_updated = NULL;

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
		$q->addWhere("st.todo_owner = $user_id");

        $q->addQuery('p.project_name, p.project_color_identifier, p.project_company');
        $q->leftJoin('projects', 'p', 'p.project_id = st.todo_project');

        $q->addQuery('c.contact_first_name, c.contact_last_name');
        $q->leftJoin('contacts', 'c', 'c.contact_id = st.todo_contact');

//        $projObj = new CProject();
//        $projObj->setAllowedSQL($this->_AppUI->user_id, $q, null, 'p');

        $q->addOrder('st.todo_updated DESC, p.project_name, st.todo_name');

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
				$q->addWhere("st.todo_due_date < '$today'");
				break;
			case 'today':
				$q->addWhere("st.todo_due_date >= '$today'");
                $q->addWhere("st.todo_due_date < '$tomorrow'");
				break;
			case 'tomorrow':
                $q->addWhere("st.todo_due_date >= '$tomorrow'");
                $q->addWhere("st.todo_due_date < '$dayAfterTomorrow'");
				break;
			case 'this-week':
                $q->addWhere("st.todo_due_date >= '$dayAfterTomorrow'");
                $q->addWhere("st.todo_due_date < '$thisSunday'");
				break;
			case 'next-week':
                $q->addWhere("st.todo_due_date > '$thisSunday'");
                $q->addWhere("st.todo_due_date <= '$nextSunday'");
				break;
			case 'later':
            default:
                $q->addWhere("st.todo_due_date > '$nextSunday'");
		}
		$q->addWhere('st.todo_status = 1');
        if ($user_id) {
            $q->addWhere("st.todo_owner = " . $user_id);
        }

        $q->addQuery('pr.project_name, pr.project_color_identifier, pr.project_company');
        $q->leftJoin('projects', 'pr', 'pr.project_id = st.todo_project');

        $projObj = new CProject();
        $projObj->setAllowedSQL($this->_AppUI->user_id, $q, null, 'pr');
        if ($project_id > 0) {
            $q->addWhere("st.todo_project = $project_id");
        }

        if ($company_id > 0 && $this->_perms->checkModuleItem('companies', 'view', $company_id)) {
            $projects = CCompany::getProjects($this->_AppUI, $company_id);
            $project_id_string = '';
            foreach ($projects as $project) {
                $project_id_string .= $project['project_id'].',';
            }
            $project_id_string .= '-1';
            $q->addWhere("st.todo_project IN ($project_id_string)");
        }

        if ($contact_id > 0 && $this->_perms->checkModuleItem('contacts', 'view', $contact_id)) {
            $q->addWhere("st.todo_contact = $contact_id");
        }
        $q->addOrder('st.todo_due_date, pr.project_name, st.todo_name');

        return $q->loadList();
	}

    /**
     * The  "description" field purposely not included as we don't have that
     *  field on this table.  This list of fields - id, name, description,
     *  startDate, endDate, updatedDate - are named specifically for the iCal
     *  creation. If you change them, it's probably going to break.  So don't do
     *  that.
     *
     * @param int $userId
     * @param int $days
     * @return array 
     */
	public function getOpenTodoItems($userId, $days = 30)
    {
		$q = $this->_getQuery();
		$q->addQuery('todo_id as id');
		$q->addQuery('todo_name as name');
        $q->addQuery('todo_name as description');
        $q->addQuery('todo_project as project_id');
		$q->addQuery("todo_due_date as startDate");
        $q->addQuery("todo_due_date as endDate");
		$q->addQuery("todo_updated as updatedDate");
        $q->addQuery('CONCAT(\''. W2P_BASE_URL . '/index.php?m=todos&todo_id=' . '\', todo_id) as url');
        $q->addQuery('p.project_id, p.project_name');
        $q->addJoin('projects', 'p', 'p.project_id = todo_project');
        
		$q->addTable('todos');
		$q->addWhere("todo_due_date < DATE_ADD(CURDATE(), INTERVAL $days DAY)");
		$q->addWhere("todo_owner = $userId");
		$q->addWhere("todo_status = 1");
		$q->addOrder('todo_due_date');

		return $q->loadList();
	} 

	public function complete()
	{
		$this->load();
        $this->todo_status = 0;

		return $this->store();
	}

	public function delete()
	{
		$this->load();
		$this->todo_status = -1;

		return $this->store();
	}

    public function hook_preStore() {
        parent::hook_preStore();

        $q = $this->_getQuery();
        $this->todo_updated = $q->dbfnNowWithTZ();
        $this->todo_due_date = $this->resolveTimeframeEnd($this->_AppUI);
        $this->todo_due_date = $this->_AppUI->convertToSystemTZ($this->todo_due_date);
    }

    public function hook_preCreate() {
        parent::hook_preCreate();

        $q = $this->_getQuery();
        $this->todo_created = $q->dbfnNowWithTZ();
    }

	public function renderTimeframe()
    {
		$dateInfo = array();
		
		if ($this->todo_due_date != '') {
			$timeString = strtotime($this->todo_due_date);
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
		$q->addQuery('contact_id, contact_display_name');
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

        switch ($this->todo_due_date) {
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
        $search['table_orderby'] = 'todo_name';
        $search['search_fields'] = array('todo_name');
        $search['display_fields'] = $search['search_fields'];

        return $search;
    }

    public function renderItem($todoItem, $todoCategories = array(), $dateRangeName = '')
    {
        ?>
        <li id="r<?php echo $todoItem['todo_id']; ?>">
            <a href="#" onClick="delIt(<?php echo $todoItem['todo_id']; ?>)">
                <?php echo w2PshowImage('icons/stock_delete-16.png', 16, 16, ''); ?>
            </a>
            <a href="?m=todos&amp;todo_id=<?php echo $todoItem['todo_id']; ?>"><?php echo w2PshowImage('icons/pencil.gif', 16, 16, ''); ?></a>
            <input type="checkbox" onClick="markItemComplete(<?php echo $todoItem['todo_id']; ?>)" />
            <em><?php echo $this->_AppUI->_($todoCategories[$todoItem['todo_category']]); ?></em>
            <?php if (in_array($dateRangeName, array('overdue', 'later')) && date('Y', strtotime($todoItem['todo_due'])) != 2020) { ?>
                <?php
                echo $this->_AppUI->formatTZAwareTime($todoItem['todo_due_date'], '%b %d').' - ';
            } ?>
            <?php echo w2p_textarea($todoItem['todo_name']); ?>
            <?php if ($todoItem['todo_project'] > 0) { ?>
                <span style="padding: 2px; background-color: #<?php echo $todoItem['project_color_identifier']; ?>;">
                                <a href="./index.php?m=projects&amp;a=view&amp;project_id=<?php echo $todoItem['todo_project']; ?>" style="color: <?php echo bestColor($todoItem['project_color_identifier']) ?>;"><?php echo $todoItem['project_name']; ?></a>
                            </span>
            <?php } ?>
            <?php if ($todoItem['todo_contact'] > 0) {
                $contact = new CContact();
                $contact->load($todoItem['todo_contact']);
                ?>&nbsp;(Re: <a href="./index.php?m=contacts&amp;a=view&amp;contact_id=<?php echo $contact->contact_id; ?>"><?php echo $contact->contact_first_name; ?> <?php echo $contact->contact_last_name; ?></a> <a href="mailto:<?php echo $contact->contact_email; ?>"><img border="0" src="<?php echo w2PfindImage('stock_attach-16.png'); ?>" /></a>)<?php
            } ?>
        </li>
    <?php
    }
}
