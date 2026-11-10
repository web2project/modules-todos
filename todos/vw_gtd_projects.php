<?php
if (!defined('W2P_BASE_DIR')) {
    die('You should not access this file directly.');
}
/**
 * The "GTD Active Projects" and "GTD All Projects" tabs: the next three open
 *   todos per project. Included by vw_gtd_active_projects.php and
 *   vw_gtd_all_projects.php, which set $gtdActiveOnly.
 */
global $AppUI, $owner;

$todo = new CTodo();
// also needed when the list is a tab on another module's page
$AppUI->getTheme()->addFooterJavascriptFile('modules/todos/todos.js');
$todoCategories = w2PgetSysVal('TodoType');
$projects = $todo->getNextTodosByProject($owner, !empty($gtdActiveOnly));

?>
<div id="todos_list" data-nonce="<?php echo $AppUI->getFormToken(); ?>" data-error="<?php echo $AppUI->_('The todo could not be updated. Please reload the page and try again.'); ?>" data-confirm="<?php echo $AppUI->_('Delete this todo?'); ?>">
    <?php
    foreach ($projects as $projectId => $data) {
        $name = $projectId ? $data['project_name'] : $AppUI->_('No project specified', UI_OUTPUT_RAW);
        echo '<h3 class="h6 mt-2 mb-1">' . CTodo::e($name) . '</h3>';
        echo '<ul class="list-group list-group-flush mb-2">';
        foreach ($data['todos'] as $todoItem) {
            $todo->renderItem($todoItem, $todoCategories);
        }
        echo '</ul>';
    }
    ?>
</div>
