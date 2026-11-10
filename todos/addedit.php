<?php 
if (!defined('W2P_BASE_DIR')) {
    die('You should not access this file directly.');
}

$todo_id = (int) w2PgetParam($_GET, 'todo_id', 0);

// After a failed save do_todo_aed holds the posted todo, so the form shows
//   what was entered instead of starting over.
$todo = $AppUI->restoreObject();
if ($todo instanceof CTodo) {
    $todo_id = (int) $todo->todo_id;
} else {
    if (is_object($todo)) {
        // another module's held object: put it back for that module
        $AppUI->holdObject($todo);
    }
    $todo = new CTodo();
    $todo->load($todo_id);
}

?>

<form name="addTodoItem" id="addTodoItem" action="?m=todos" method="post">
    <input type="hidden" name="dosql" value="do_todo_aed" />
    <input type="hidden" name="del" value="0" />
    <input type="hidden" name="todo_id" value="<?php echo $todo_id;?>" />
    <input type="hidden" name="return_module" value="<?php echo CTodo::e($m); ?>" />
    <?php
        // On a Project, Contact or Company page, come back to that page.
        $returnIds = array('projects' => $project_id, 'contacts' => $contact_id, 'companies' => $company_id);
    ?>
    <input type="hidden" name="return_id" value="<?php echo isset($returnIds[$m]) ? (int) $returnIds[$m] : 0; ?>" />

    <div class="card card-body">
        <div class="mb-2">
            <label class="form-label fw-semibold" for="todo_name"><?php echo $AppUI->_('Add a todo');?></label>
            <input type="text" class="form-control form-control-sm" name="todo_name" id="todo_name" value="<?php echo CTodo::e($todo->todo_name); ?>" maxlength="255" required />
        </div>
        <div class="mb-2">
            <label class="form-label fw-semibold" for="todo_due_date"><?php echo $AppUI->_('When is it due?');?></label>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php
                    array_shift($todoTimeframes);
                    $todoTimeframes['other'] = 'Other';

                    $dateInfo = $todo->renderTimeframe();
                    echo arraySelect( $todoTimeframes, 'todo_due_date', 'class="form-select form-select-sm w-auto"', $dateInfo['displayName']);
                ?>
                <span><?php echo $AppUI->_('or'); ?></span>
                <input type="hidden" name="display_todo_date" id="display_todo_date" value="<?php echo $dateInfo['cleanFormat']; ?>" />
                <input type="date" class="form-control form-control-sm w-auto" id="todo_date" value="<?php echo $dateInfo['displayFormat']; ?>" aria-label="<?php echo $AppUI->_('Due date'); ?>" onchange="w2pTodos.pickDate(this);" />
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label fw-semibold" for="todo_owner"><?php echo $AppUI->_('Assignee');?></label>
            <?php
                $availableAssignees = $AppUI->acl()->getPermittedUsers();
                $availableAssignees = array(0 => '') + $availableAssignees;
                if (count($availableAssignees) > 1) {
                    echo arraySelect( $availableAssignees, 'todo_owner', 'class="form-select form-select-sm"', ($todo->todo_owner ? $todo->todo_owner : $AppUI->user_id) );
                } else {
                    ?>
                    <input type="hidden" name="todo_owner" value="0" />
                    <div class="form-text"><?php echo $AppUI->_('No assignees available');?></div>
                    <?php
                }
            ?>
        </div>
        <div class="mb-2">
            <label class="form-label fw-semibold" for="todo_project"><?php echo $AppUI->_('Related to which project?');?></label>
            <?php
                $projectId = ($todo->todo_project > 0) ? $todo->todo_project : $project_id;
                $projectList =  array(0 => '') + $todo->getAllowedProjects($AppUI->user_id);
                echo arraySelect($projectList, 'todo_project', 'class="form-select form-select-sm"', $projectId);
            ?>
        </div>
        <div class="mb-2">
            <label class="form-label fw-semibold" for="todo_contact"><?php echo $AppUI->_('Related to which contact?');?></label>
            <?php
                $availableContacts = $todo->getContacts();
                $contactId = ($todo->todo_contact > 0) ? $todo->todo_contact : $contact_id;
                echo arraySelect( $availableContacts, 'todo_contact', 'class="form-select form-select-sm"', $contactId);
            ?>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="todo_category"><?php echo $AppUI->_('Choose a Category');?></label>
            <?php
                $todoCategories = w2PgetSysVal('TodoType');
                echo arraySelect( $todoCategories, 'todo_category', 'class="form-select form-select-sm"', (!empty($todo->todo_category) ? $todo->todo_category : '0') );
            ?>
        </div>
        <div>
            <input class="btn btn-primary btn-sm" type="submit" value="save!" id="submitter" />
        </div>
    </div>
</form>
