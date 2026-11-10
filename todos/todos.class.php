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
    /**
     * The due date stored for "Later" / no due date. It sorts after every
     *   real date, so the "Later" bucket and the ordering need no special
     *   cases, and it is never converted between timezones.
     *
     * Before v3.0 this was 2020-01-01 (now in the past, so every "Later"
     *   item showed as Overdue); the v3.0 upgrade converts those rows.
     */
    const NO_DUE_DATE = '9999-12-31 23:59:59';

    /**
     * The values the add/edit form posts as todo_due_date.
     *
     * @var string[]
     */
    protected static $timeframeNames = array('overdue', 'today', 'tomorrow',
        'this-week', 'next-week', 'later', 'other');

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
		$user_id = ((int) $user_id) ? (int) $user_id : (int) $this->_AppUI->user_id;

        $q = $this->_getQuery();
		$q->addQuery('st.*');
		$q->addTable('todos', 'st');

		$q->addWhere('st.todo_status = 0');
		$q->addWhere("st.todo_owner = $user_id");

        $q->addQuery('p.project_name, p.project_color_identifier, p.project_company');
        $q->leftJoin('projects', 'p', 'p.project_id = st.todo_project');

        $q->addQuery('c.contact_first_name, c.contact_last_name');
        $q->leftJoin('contacts', 'c', 'c.contact_id = st.todo_contact');

        $this->_restrictToAllowedProjects($q, 'st.todo_project');

        $q->addOrder('st.todo_updated DESC, p.project_name, st.todo_name');

        return $q->loadList();
	}

	public function getTodosForDateRange($dateRangeName, $user_id = 0, $project_id = 0, $contact_id = 0, $company_id = 0)
	{
        $user_id = (int) $user_id;
        $project_id = (int) $project_id;
        $contact_id = (int) $contact_id;
        $company_id = (int) $company_id;

        // Day boundaries in the user's timezone, as stored (UTC) values.
        $b = $this->getTimeframeBoundaries();

        $q = $this->_getQuery();
		$q->addQuery('st.*');
		$q->addTable('todos', 'st');
		switch ($dateRangeName) {
			case 'overdue':
				$q->addWhere("st.todo_due_date < '{$b['today']}'");
				break;
			case 'today':
				$q->addWhere("st.todo_due_date >= '{$b['today']}'");
                $q->addWhere("st.todo_due_date < '{$b['tomorrow']}'");
				break;
			case 'tomorrow':
                $q->addWhere("st.todo_due_date >= '{$b['tomorrow']}'");
                $q->addWhere("st.todo_due_date < '{$b['this-week']}'");
				break;
			case 'this-week':
                $q->addWhere("st.todo_due_date >= '{$b['this-week']}'");
                $q->addWhere("st.todo_due_date < '{$b['next-week']}'");
				break;
			case 'next-week':
                $q->addWhere("st.todo_due_date >= '{$b['next-week']}'");
                $q->addWhere("st.todo_due_date < '{$b['later']}'");
				break;
			case 'later':
            default:
                $q->addWhere("st.todo_due_date >= '{$b['later']}'");
		}
		$q->addWhere('st.todo_status = 1');
        if ($user_id) {
            $q->addWhere("st.todo_owner = " . $user_id);
        }

        $q->addQuery('pr.project_name, pr.project_color_identifier, pr.project_company');
        $q->leftJoin('projects', 'pr', 'pr.project_id = st.todo_project');

        $this->_restrictToAllowedProjects($q, 'st.todo_project');
        if ($project_id > 0) {
            $q->addWhere("st.todo_project = $project_id");
        }

        if ($company_id > 0 && $this->_perms->checkModuleItem('companies', 'view', $company_id)) {
            // The project permissions are already applied through pr above.
            $q->addWhere('pr.project_company = ' . $company_id);
        }

        if ($contact_id > 0 && $this->_perms->checkModuleItem('contacts', 'view', $contact_id)) {
            $q->addWhere("st.todo_contact = $contact_id");
        }
        $q->addOrder('st.todo_due_date, pr.project_name, st.todo_name');

        return $q->loadList();
	}

    /**
     * Limits a todo query to todos without a project or on a project the
     *   user may see.
     *
     * CProject::setAllowedSQL() can't be used on the LEFT JOINed projects
     *   table here: for a user with restricted project permissions it adds
     *   "pr.project_id IN (...)", which is NULL for todos without a project,
     *   so all of those disappeared.
     *
     * @param w2p_Database_Query $q
     * @param string             $column the todo_project column, with alias
     */
    protected function _restrictToAllowedProjects($q, $column)
    {
        $project = new CProject();
        $allowed = array_map('intval', array_keys($project->getAllowedProjects($this->_AppUI->user_id, false)));
        $allowed[] = -1;

        $q->addWhere('(' . $column . ' = 0 OR ' . $column . ' IN (' . implode(',', $allowed) . '))');
    }

    /**
     * The Getting Things Done views: the next few open todos for each
     *   project, soonest first, in one query. Todos without a project come
     *   first under project id 0.
     *
     * @param int  $user_id    0 for all users
     * @param bool $activeOnly only active projects (and todos without one)
     * @param int  $perProject how many todos to keep per project
     *
     * @return array project id => array('project_name' => ..., 'todos' => rows)
     */
    public function getNextTodosByProject($user_id = 0, $activeOnly = true, $perProject = 3)
    {
        $user_id = (int) $user_id;

        $q = $this->_getQuery();
        $q->addQuery('st.*');
        $q->addTable('todos', 'st');
        $q->addQuery('pr.project_name, pr.project_color_identifier');
        $q->leftJoin('projects', 'pr', 'pr.project_id = st.todo_project');
        $q->addWhere('st.todo_status = 1');
        if ($user_id) {
            $q->addWhere('st.todo_owner = ' . $user_id);
        }
        if ($activeOnly) {
            $q->addWhere('(st.todo_project = 0 OR pr.project_active = 1)');
        }
        $this->_restrictToAllowedProjects($q, 'st.todo_project');
        $q->addOrder('(st.todo_project > 0), pr.project_name, st.todo_project, st.todo_due_date, st.todo_name');

        $projects = array();
        foreach ($q->loadList() as $row) {
            $projectId = (int) $row['todo_project'];
            if (!isset($projects[$projectId])) {
                $projects[$projectId] = array('project_name' => $row['project_name'], 'todos' => array());
            }
            if (count($projects[$projectId]['todos']) < $perProject) {
                $projects[$projectId]['todos'][] = $row;
            }
        }

        return $projects;
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
        $userId = (int) $userId;
        $days = (int) $days;
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
        if (!$this->load()) {
            return false;
        }
        $this->todo_status = 0;

		return $this->store();
	}

    /**
     * Runs after hook_preStore(), so the due date is already resolved. The
     *   checks only apply to open todos, so a legacy record (for example one
     *   whose name was NULL before 3.0) can still be completed or deleted.
     *
     * @return bool
     */
    public function isValid()
    {
        $baseErrorMsg = get_class($this) . '::store-check failed - ';

        if (1 == (int) $this->todo_status) {
            if ('' == trim((string) $this->todo_name)) {
                $this->_error['todo_name'] = $baseErrorMsg . 'todo name is not set';
            }
            if ((int) $this->todo_owner < 1) {
                $this->_error['todo_owner'] = $baseErrorMsg . 'todo owner is not set';
            }
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $this->todo_due_date)) {
            $this->_error['todo_due_date'] = $baseErrorMsg . 'todo due date is not valid';
        }

        return (count($this->_error)) ? false : true;
    }

    /**
     * Todos are soft-deleted: the row stays with a status of -1.
     *
     * The signature has to match w2p_Core_BaseObject::delete($oid = null);
     *   since PHP 8 a narrower one is a compile-time fatal error.
     *
     * @param int|null $oid
     *
     * @return bool
     */
    public function delete($oid = null)
    {
        if ($oid) {
            $this->todo_id = (int) $oid;
        }
        if (!$this->load() || !$this->canDelete()) {
            return false;
        }
        $this->todo_status = -1;

        return $this->store();
    }

    /**
     * Todos are personal. On top of the module permissions, a todo can only
     *   be opened by its owner, by someone who can view its project, or by a
     *   system admin, and only changed by its owner, by someone who can edit
     *   its project, or by a system admin. Without this, anyone with access
     *   to the module could open, complete or delete any todo by its id.
     */
    public function canView()
    {
        return parent::canView() && $this->_canTouch('view');
    }

    public function canEdit()
    {
        return parent::canEdit() && $this->_canTouch('edit');
    }

    public function canDelete($notUsed = '', $oid = null, $joins = null)
    {
        if (!parent::canDelete($notUsed, $oid, $joins)) {
            return false;
        }
        if (!$this->_canTouch('edit')) {
            $this->_error['noDeletePermission'] = $this->_AppUI->_('noDeletePermission');
            return false;
        }

        return true;
    }

    /**
     * Whether the current user may act on a todo row as listed (the row
     *   needs todo_owner and todo_project). The lists use this to hide the
     *   edit, complete and delete controls on other people's todos.
     *
     * @param array $todoItem
     *
     * @return bool
     */
    public function canChangeItem(array $todoItem)
    {
        return $this->_isAllowed((int) $todoItem['todo_owner'], (int) $todoItem['todo_project'], 'edit');
    }

    /**
     * Checks the stored owner and project of this todo. They are read from
     *   the table because load() asks canView() before the row is loaded.
     *
     * @param string $op 'view' or 'edit'
     *
     * @return bool
     */
    protected function _canTouch($op)
    {
        $id = (int) $this->todo_id;
        if ($id < 1) {
            // a new todo
            return true;
        }

        $q = new w2p_Database_Query();
        $q->addTable('todos');
        $q->addQuery('todo_owner, todo_project');
        $q->addWhere('todo_id = ' . $id);
        $row = $q->loadHash();
        if (!$row) {
            // nothing stored yet, so nothing to protect
            return true;
        }

        return $this->_isAllowed((int) $row['todo_owner'], (int) $row['todo_project'], $op);
    }

    /**
     * @param int    $ownerId
     * @param int    $projectId
     * @param string $op 'view' or 'edit'
     *
     * @return bool
     */
    protected function _isAllowed($ownerId, $projectId, $op)
    {
        if ($ownerId == (int) $this->_AppUI->user_id) {
            return true;
        }
        if ($projectId > 0 && $this->_perms->checkModuleItem('projects', $op, $projectId)) {
            return true;
        }

        return (bool) $this->_perms->checkModule('system', 'edit');
    }

    public function hook_preStore() {
        parent::hook_preStore();

        $q = $this->_getQuery();
        $this->todo_updated = $q->dbfnNowWithTZ();

        if ('' == (string) $this->todo_due_date) {
            $this->todo_due_date = 'later';
        }
        // Only a value posted by the form is a timeframe name ('today',
        //   'other', ...). complete() and delete() store a loaded record,
        //   whose due date is already a stored UTC datetime: leave it alone.
        if (in_array($this->todo_due_date, self::$timeframeNames, true)) {
            $this->todo_due_date = $this->resolveTimeframeEnd();
            if (self::hasDueDate($this->todo_due_date)) {
                $this->todo_due_date = $this->_AppUI->convertToSystemTZ($this->todo_due_date);
            }
        }
    }

    public function hook_preCreate() {
        parent::hook_preCreate();

        $q = $this->_getQuery();
        $this->todo_created = $q->dbfnNowWithTZ();
    }

	public function renderTimeframe()
    {
		$dateInfo = array();
		
		if ($this->todo_id && !self::hasDueDate($this->todo_due_date)) {
			$dateInfo['displayName'] = 'later';
			$dateInfo['cleanFormat'] = '';
			$dateInfo['displayFormat'] = '';
		} elseif ($this->todo_due_date != '') {
			// stored in UTC; shown as the user's own date
			$due = new DateTime($this->todo_due_date, new DateTimeZone('UTC'));
			$due->setTimezone($this->_userTimezone());
			// Keep the real date, including a past one: falling back to
			//   'today' moved an overdue todo to today on every save.
			$dateInfo['displayName'] = 'other';
			$dateInfo['cleanFormat'] = $due->format('Ymd');
			$dateInfo['displayFormat'] = $due->format('Y/M/d');
			$today = $this->_userToday();
			if ($due->format('Y-m-d') == $today->format('Y-m-d')) {
				$dateInfo['displayName'] = 'today';
			} elseif ($due->format('Y-m-d') == $today->modify('+1 day')->format('Y-m-d')) {
				$dateInfo['displayName'] = 'tomorrow';
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

        // A user without any project access must still get an array back:
        //   the add form does array(0 => '') + this, a TypeError on null.
        $projectList = array();
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

    /**
     * The first moment of each list bucket, as stored (UTC) values:
     *   'today', 'tomorrow', 'this-week' (the day after tomorrow),
     *   'next-week' and 'later'. Days and weeks are the user's: their
     *   TIMEZONE preference and the locale's first day of the week. When
     *   the week ends today or tomorrow, the This Week bucket is empty.
     *
     * @return array<string, string>
     */
    public function getTimeframeBoundaries()
    {
        $today = $this->_userToday();
        $dayAfterTomorrow = $today->modify('+2 day');
        $nextWeek = $this->_lastDayOfWeek($today)->modify('+1 day');
        if ($nextWeek < $dayAfterTomorrow) {
            $nextWeek = $dayAfterTomorrow;
        }
        $later = $this->_lastDayOfWeek($today)->modify('+8 day');

        $utc = new DateTimeZone('UTC');
        $boundaries = array(
            'today'     => $today,
            'tomorrow'  => $today->modify('+1 day'),
            'this-week' => $dayAfterTomorrow,
            'next-week' => $nextWeek,
            'later'     => $later,
        );
        foreach ($boundaries as $name => $day) {
            $boundaries[$name] = $day->setTimezone($utc)->format('Y-m-d H:i:s');
        }

        return $boundaries;
    }

    /**
     * The user's timezone, the same one CAppUI::convertToSystemTZ() uses.
     *
     * @return DateTimeZone
     */
    protected function _userTimezone()
    {
        $timezone = $this->_AppUI->getPref('TIMEZONE');
        try {
            return new DateTimeZone($timezone ? $timezone : date_default_timezone_get());
        } catch (Exception $e) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Midnight at the start of the user's today.
     *
     * @return DateTimeImmutable
     */
    protected function _userToday()
    {
        return new DateTimeImmutable('today', $this->_userTimezone());
    }

    /**
     * The last day of the week $day is in, using the locale's first day of
     *   the week (LOCALE_FIRST_DAY: 0 = Sunday, 1 = Monday).
     *
     * @param DateTimeImmutable $day
     *
     * @return DateTimeImmutable
     */
    protected function _lastDayOfWeek(DateTimeImmutable $day)
    {
        $firstDay = defined('LOCALE_FIRST_DAY') ? (int) LOCALE_FIRST_DAY : 0;
        $lastDay = ($firstDay + 6) % 7;
        $daysLeft = ($lastDay - (int) $day->format('w') + 7) % 7;

        return $day->modify('+' . $daysLeft . ' day');
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
			// these are the user's own days (see _userToday()); the caller
			//   converts the result to UTC
			case 'today':
				$endDate = $this->_userToday()->format('Y-m-d');
				break;
			case 'tomorrow':
				$endDate = $this->_userToday()->modify('+1 day')->format('Y-m-d');
				break;
			case 'this-week':
				$endDate = $this->_lastDayOfWeek($this->_userToday())->format('Y-m-d');
				break;
			case 'next-week':
				$endDate = $this->_lastDayOfWeek($this->_userToday())->modify('+7 day')->format('Y-m-d');
				break;
			default:
				// the "later" scenario: no due date
				return self::NO_DUE_DATE;
		}

		return $endDate.' 23:59:59';
	}

    /**
     * Escapes a value for HTML output. Names and other text are stored
     *   decoded (bind() decodes entities), so they must be escaped on output.
     *
     * @param mixed $value
     *
     * @return string
     */
    public static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * A project colour for use in a style attribute: six hex digits only.
     *
     * @param string|null $color
     *
     * @return string
     */
    public static function safeColor($color)
    {
        return preg_match('/^[0-9a-fA-F]{6}$/', (string) $color) ? $color : 'ffffff';
    }

    /**
     * Whether a stored due date is a real date rather than "Later".
     *
     * @param string|null $dueDate
     *
     * @return bool
     */
    public static function hasDueDate($dueDate)
    {
        return '' != (string) $dueDate && self::NO_DUE_DATE != $dueDate;
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
        // only the user's own open todos; deleted ones have a status of -1
        $search['table_extra'] = 't.todo_status = 1 AND t.todo_owner = ' . (int) $this->_AppUI->user_id;

        return $search;
    }

    public function renderItem($todoItem, $todoCategories = array(), $dateRangeName = '')
    {
        ?>
        <li id="r<?php echo $todoItem['todo_id']; ?>">
            <?php if ($this->canChangeItem($todoItem)) { ?>
            <a href="#" onClick="return w2pTodos.remove(<?php echo $todoItem['todo_id']; ?>);">
                <?php echo w2PshowImage('icons/stock_delete-16.png', 16, 16, ''); ?>
            </a>
            <a href="?m=todos&amp;todo_id=<?php echo $todoItem['todo_id']; ?>"><?php echo w2PshowImage('icons/pencil.gif', 16, 16, ''); ?></a>
            <input type="checkbox" onClick="w2pTodos.complete(<?php echo $todoItem['todo_id']; ?>)" />
            <?php } ?>
            <em><?php echo $this->_AppUI->_(isset($todoCategories[$todoItem['todo_category']]) ? $todoCategories[$todoItem['todo_category']] : ''); ?></em>
            <?php if (in_array($dateRangeName, array('overdue', 'later')) && self::hasDueDate($todoItem['todo_due_date'])) { ?>
                <?php
                echo $this->_AppUI->formatTZAwareTime($todoItem['todo_due_date'], '%b %d').' - ';
            } ?>
            <?php echo w2p_textarea($todoItem['todo_name']); ?>
            <?php if ($todoItem['todo_project'] > 0) { ?>
                <span style="padding: 2px; background-color: #<?php echo self::safeColor($todoItem['project_color_identifier']); ?>;">
                                <a href="./index.php?m=projects&amp;a=view&amp;project_id=<?php echo $todoItem['todo_project']; ?>" style="color: <?php echo bestColor(self::safeColor($todoItem['project_color_identifier'])) ?>;"><?php echo self::e($todoItem['project_name']); ?></a>
                            </span>
            <?php } ?>
            <?php if ($todoItem['todo_contact'] > 0) {
                $contact = new CContact();
                $contact->load($todoItem['todo_contact']);
                ?>&nbsp;(Re: <a href="./index.php?m=contacts&amp;a=view&amp;contact_id=<?php echo $contact->contact_id; ?>"><?php echo self::e($contact->contact_first_name . ' ' . $contact->contact_last_name); ?></a> <a href="mailto:<?php echo self::e($contact->contact_email); ?>"><img border="0" src="<?php echo w2PfindImage('stock_attach-16.png'); ?>" /></a>)<?php
            } ?>
        </li>
    <?php
    }
}
