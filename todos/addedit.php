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

    <table border="0" cellpadding="4" cellspacing="0" width="100%" class="std">
        <tr>
            <td class="label"><?php echo $AppUI->_('Add a todo');?>:<br />
                <input type="text" class="text" size="35" name="todo_name" id="todo_name" value="<?php echo htmlentities($todo->todo_name, ENT_QUOTES);  ?>" maxlength="255">
            </td>
        </tr>
        <tr>
            <td class="label"><?php echo $AppUI->_('When is it due?');?><br />
                <?php
                    array_shift($todoTimeframes);
                    $todoTimeframes['other'] = 'Other';

                    $dateInfo = $todo->renderTimeframe();
                    echo arraySelect( $todoTimeframes, 'todo_due_date', 'size="1" class="text"', $dateInfo['displayName']);
                ?> or
                <input type="hidden" name="display_todo_date" id="display_todo_date" value="<?php echo $dateInfo['cleanFormat']; ?>" />
                <input type="date" class="text" id="todo_date" value="<?php echo $dateInfo['displayFormat']; ?>" aria-label="<?php echo $AppUI->_('Due date'); ?>" onchange="w2pTodos.pickDate(this);" />
            </td>
        </tr>
        <tr>
            <td class="label"><?php echo $AppUI->_('Assignee');?>:
                <?php
                    $availableAssignees = $AppUI->acl()->getPermittedUsers();
                    $availableAssignees = array(0 => '') + $availableAssignees;
                    if (count($availableAssignees) > 1) {
                        echo arraySelect( $availableAssignees, 'todo_owner', 'size="1" class="text"', ($todo->todo_owner ? $todo->todo_owner : $AppUI->user_id) );
                    } else {
                        ?>
                        <input type="hidden" name="todo_owner" value="0" />
                        <em><?php echo $AppUI->_('No assignees available');?></em>
                        <?php
                    }
                ?>
            </td>
        </tr>
        <tr>
            <td class="label"><?php echo $AppUI->_('Related to which project?');?>:<br />
                <?php
                    $projectId = ($todo->todo_project > 0) ? $todo->todo_project : $project_id;
                    $projectList =  array(0 => '') + $todo->getAllowedProjects($AppUI->user_id);
                    echo arraySelect($projectList, 'todo_project', 'size="1" class="text"', $projectId);
                ?>
            </td>
        </tr>
        <tr>
            <td class="label"><?php echo $AppUI->_('Related to which contact?');?>:<br />
                <?php
                    $availableContacts = $todo->getContacts();
                    $contactId = ($todo->todo_contact > 0) ? $todo->todo_contact : $contact_id;
                    echo arraySelect( $availableContacts, 'todo_contact', 'size="1" class="text"', $contactId);
                ?>
            </td>
        </tr>
        <tr>
            <td class="label"><?php echo $AppUI->_('Choose a Category');?>:<br />
                <?php
                    $todoCategories = w2PgetSysVal('TodoType');
                    echo arraySelect( $todoCategories, 'todo_category', 'size="1" class="text"', (!empty($todo->todo_category) ? $todo->todo_category : '0') );
                ?>
            </td>
        </tr>
        <tr>
            <td class="label">
                <input class="text" type="submit" value="save!" id="submitter">
                <div id="log"><div id="log_res"><!-- spanner --></div></div>
            </td>
        </tr>
    </table>
</form>
