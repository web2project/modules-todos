<?php
if (!defined('W2P_BASE_DIR'))
{
    die('You should not access this file directly.');
}
global $AppUI, $project_id, $contact_id, $company_id, $tab, $owner;

$AppUI->savePlace('m=todos');

$todo = new CTodo();
$todoTimeframes = $todo->getTimeframes();

//TODO: this whole table mess should be converted over to nice divs
?>
<script src="./modules/todos/todos.js" type="text/javascript"></script>
<link rel="stylesheet" type="text/css" href="./modules/todos/todos.css" />

<table width="100%">
    <tr>
        <td valign="top">
            <div style="float: right; width: 300px;" id="addedit">
                <?php include W2P_BASE_DIR.'/modules/todos/addedit.php'; ?>
            </div>
            <div>
                <table id="todos_list">
                    <?php
                    $todoTimeframes = $todo->getTimeframes();
                    $todoCategories = w2PgetSysVal('TodoType');
                    foreach ($todoTimeframes as $dateRangeName => $daterange) {
                        $todoList = $todo->getTodosForDateRange($dateRangeName, $owner, $project_id, $contact_id, $company_id);
                        if (count($todoList) > 0) {
                            echo '<tr><td colspan="3"><strong>'.$AppUI->_($daterange).'</strong></td></tr>';
                            foreach ($todoList as $todoItem) {
                                ?>
                                <tr id="r<?php echo $todoItem['todo_id']; ?>">
                                    <td nowrap="true" id="cell_<?php echo $todoItem['todo_id']; ?>">
                                        <a href="#" onClick="delIt(<?php echo $todoItem['todo_id']; ?>)">
                                            <?php echo w2PshowImage('icons/stock_delete-16.png', 16, 16, ''); ?>
                                        </a>
                                        <a href="?m=todos&amp;todo_id=<?php echo $todoItem['todo_id']; ?>"><?php echo w2PshowImage('icons/pencil.gif', 16, 16, ''); ?></a>
                                        <input type="checkbox" onClick="markItemComplete(<?php echo $todoItem['todo_id']; ?>)" />
                                    </td>
                                    <td>
                                        <em><?php echo $AppUI->_($todoCategories[$todoItem['todo_category_id']]); ?></em>
                                        <?php if (in_array($dateRangeName, array('overdue', 'later')) && date('Y', strtotime($todoItem['todo_due'])) != 2020) { ?>
                                            <?php
                                                echo $AppUI->formatTZAwareTime($todoItem['todo_due'], '%b %d').' - ';
                                            } ?>
                                         </td>
                                    <td>
                                        <?php echo w2p_textarea($todoItem['todo_title']); ?>
                                         </td>
                                    <td>
                                        
                                        <?php if ($todoItem['todo_project_id'] > 0) { ?>
                                            <span style="padding: 2px; background-color: #<?php echo $todoItem['project_color_identifier']; ?>;">
                                                <a href="./index.php?m=projects&amp;a=view&amp;project_id=<?php echo $todoItem['todo_project_id']; ?>" style="color: <?php echo bestColor($todoItem['project_color_identifier']) ?>;"><?php echo $todoItem['project_name']; ?></a>
                                            </span>
                                        <?php } ?>
                                         </td>
                                    <td>

                                        <?php if ($todoItem['todo_related_to_contact_id'] > 0) {
                                            $contact = new CContact();
                                            $contact->load($todoItem['todo_related_to_contact_id']);
                                            ?>&nbsp;(Re: <a href="./index.php?m=contacts&amp;a=view&amp;contact_id=<?php echo $contact->contact_id; ?>"><?php echo $contact->contact_first_name; ?> <?php echo $contact->contact_last_name; ?></a> <a href="mailto:<?php echo $contact->contact_email; ?>"><img border="0" src="<?php echo w2PfindImage('stock_attach-16.png'); ?>" /></a>)<?php
                                        } ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                    }
                    ?>
                </table>
            </div>
        </td>
    </tr>
</table>