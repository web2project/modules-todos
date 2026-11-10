<?php
if (!defined('W2P_BASE_DIR'))
{
    die('You should not access this file directly.');
}
global $AppUI, $m, $project_id, $contact_id, $company_id, $owner;

$todo = new CTodo();
// also needed when the list is a tab on another module's page
$AppUI->getTheme()->addFooterJavascriptFile('modules/todos/todos.js');
$todoTimeframes = $todo->getTimeframes();

$todoCategories = w2PgetSysVal('TodoType');
?>
<div class="row g-3">
    <div class="col-lg-8" id="todos_list" data-nonce="<?php echo $AppUI->getFormToken(); ?>" data-error="<?php echo $AppUI->_('The todo could not be updated. Please reload the page and try again.'); ?>" data-confirm="<?php echo $AppUI->_('Delete this todo?'); ?>">
        <?php
        foreach ($todoTimeframes as $dateRangeName => $daterange) {
            $todoList = $todo->getTodosForDateRange($dateRangeName, $owner, $project_id, $contact_id, $company_id);
            if (count($todoList)) {
                echo '<h3 class="h6 mt-2 mb-1">' . $AppUI->_($daterange) . '</h3>';
                echo '<ul class="list-group list-group-flush mb-2">';
                foreach($todoList as $todoItem) {
                    $todo->renderItem($todoItem, $todoCategories, $dateRangeName);
                }
                echo '</ul>';
            }
        }
        ?>
    </div>
    <div class="col-lg-4">
        <!-- stays in view while a long list scrolls -->
        <div class="position-sticky" style="top: calc(var(--w2p-header-height, 64px) + 1rem);" id="addedit">
            <?php include W2P_BASE_DIR.'/modules/todos/addedit.php'; ?>
        </div>
    </div>
</div>
