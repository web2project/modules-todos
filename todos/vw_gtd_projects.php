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
<table width="100%">
    <tr>
        <td id="todos_list" style="width: 100%; vertical-align: top;" data-nonce="<?php echo $AppUI->getFormToken(); ?>" data-error="<?php echo $AppUI->_('The todo could not be updated. Please reload the page and try again.'); ?>">
            <?php
            foreach ($projects as $projectId => $data) {
                $name = $projectId ? $data['project_name'] : $AppUI->_('No project specified', UI_OUTPUT_RAW);
                echo '<strong>' . CTodo::e($name) . '</strong>';
                echo '<ul style="list-style-type: none;">';
                foreach ($data['todos'] as $todoItem) {
                    $todo->renderItem($todoItem, $todoCategories);
                }
                echo '</ul>';
            }
            ?>
        </td>
    </tr>
</table>
