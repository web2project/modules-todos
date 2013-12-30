<?php
if (!defined('W2P_BASE_DIR'))
{
    die('You should not access this file directly.');
}
global $AppUI, $project_id, $contact_id, $company_id, $tab, $owner;

$todo = new CTodo();
$todoTimeframes = $todo->getTimeframes();

//TODO: this whole table mess should be converted over to nice divs
?>

<table width="100%">
    <tr>
        <td id="todos_list" style="width: 100%; vertical-align: top;">
            <?php
            $todoTimeframes = $todo->getTimeframes();
            $todoCategories = w2PgetSysVal('TodoType');
            foreach ($todoTimeframes as $dateRangeName => $daterange) {
                $todoList = $todo->getTodosForDateRange($dateRangeName, $owner, $project_id, $contact_id, $company_id);
                if (count($todoList)) {
                    echo '<strong>'.$AppUI->_($daterange).'</strong>';
                    echo '<ul style="list-style-type: none;">';
                    foreach($todoList as $todoItem) { ?>
                        <li id="r<?php echo $todoItem['todo_id']; ?>">
                            <a href="#" onClick="delIt(<?php echo $todoItem['todo_id']; ?>)">
                                <?php echo w2PshowImage('icons/stock_delete-16.png', 16, 16, ''); ?>
                            </a>
                            <a href="?m=todos&amp;todo_id=<?php echo $todoItem['todo_id']; ?>"><?php echo w2PshowImage('icons/pencil.gif', 16, 16, ''); ?></a>
                            <input type="checkbox" onClick="markItemComplete(<?php echo $todoItem['todo_id']; ?>)" />
                            <em><?php echo $AppUI->_($todoCategories[$todoItem['todo_category']]); ?></em>
                            <?php if (in_array($dateRangeName, array('overdue', 'later')) && date('Y', strtotime($todoItem['todo_due'])) != 2020) { ?>
                                <?php
                                echo $AppUI->formatTZAwareTime($todoItem['todo_due_date'], '%b %d').' - ';
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
                    <?php }
                    echo '</ul>';
                }
            }
            ?>
        </td>
        <td valign="top">
            <div style="float: right; width: 300px;" id="addedit">
                <?php include W2P_BASE_DIR.'/modules/todos/addedit.php'; ?>
            </div>
        </td>
    </tr>
</table>