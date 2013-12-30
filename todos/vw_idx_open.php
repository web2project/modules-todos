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
                    foreach($todoList as $todoItem) {
                        $todo->renderItem($todoItem, $todoCategories, $dateRangeName);
                    }
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