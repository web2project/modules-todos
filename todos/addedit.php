<?php 
if (!defined('W2P_BASE_DIR')) {
    die('You should not access this file directly.');
}

$todo_id = (int) w2PgetParam($_GET, 'todo_id', 0);

$todo = new CTodo();
$todo->load($todo_id);

$AppUI->loadCalendarJS();
$df = $AppUI->getPref('SHDATEFORMAT');
global $cal_sdf;

?>
<script language="javascript" type="text/javascript">
    function setDate( frm_name, f_date ) {
        fld_date = eval( 'document.' + frm_name + '.' + f_date );
        fld_real_date = eval( 'document.' + frm_name + '.' + 'display_' + f_date );
        document.getElementById('todo_due_date').value = 'other';
        fld_date.style.width = '10';
        fld_date.style.border = '1';

        if (fld_date.value.length>0) {
          if ((parseDate(fld_date.value))==null) {
                alert('The Date/Time you typed does not match your prefered format, please retype.');
                fld_real_date.value = '';
                fld_date.style.backgroundColor = 'red';
            } else {
                fld_real_date.value = formatDate(parseDate(fld_date.value), 'yyyyMMdd');
                fld_date.value = formatDate(parseDate(fld_date.value), '<?php echo $cal_sdf ?>');
                fld_date.style.backgroundColor = '';
            }
        } else {
            fld_real_date.value = '';
        }
    }
    window.addEvent('domready', function() {
        $('todo_title').focus();
    });
</script>

<form name="addTodoItem" id="addTodoItem" action="?m=todos" method="post">
    <input type="hidden" name="dosql" value="do_todo_aed" />
    <input type="hidden" name="del" value="0" />
    <input type="hidden" name="todo_id" value="<?php echo $todo_id;?>" />
    <input type="hidden" name="return_module" value="<?php echo $m; ?>" />

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
                <a onclick="return showCalendar('todo_date', '<?php echo $df ?>', 'addTodoItem', null, true)" href="javascript: void(0);">
                    <img src="<?php echo w2PfindImage('calendar.gif'); ?>" width="24" height="12" alt="<?php echo $AppUI->_('Calendar'); ?>" border="0" />
                </a>
                <input type="hidden" name="display_todo_date" id="display_todo_date" value="<?php echo $dateInfo['cleanFormat']; ?>" />
                <input type="text" class="text" name="todo_date" id="todo_date" size="11" value="<?php echo $dateInfo['displayFormat']; ?>" />
            </td>
        </tr>
        <tr>
            <td class="label"><?php echo $AppUI->_('Assignee');?>:
                <?php
                    $availableAssignees = $AppUI->acl()->getPermittedUsers();
                    $availableAssignees = array(0 => '') + $availableAssignees;
                    if (count($availableAssignees) > 1) {
                        echo arraySelect( $availableAssignees, 'todo_owner', 'size="1" class="text"', ($todo->todo_user ? $todo->todo_user : $AppUI->user_id) );
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
