<?php

global $AppUI;

$project = new CProject();
$projects = array(0 => array('project_name' => 'No project specified', 'project_id' => 0 ));
$projects += $project->getAllowedProjects($AppUI->user_id, false);

$todo = new CTodo();
$todoCategories = w2PgetSysVal('TodoType');

?>
<table width="100%">
    <tr>
        <td id="todos_list" style="width: 100%; vertical-align: top;" data-nonce="<?php echo $AppUI->getFormToken(); ?>" data-error="<?php echo $AppUI->_('The todo could not be updated. Please reload the page and try again.'); ?>">
            <?php
            foreach ($projects as $data) {
                $todos = $todo->loadAll('todo_due_date', 'todo_status = 1 AND todo_owner = ' . (int) $AppUI->user_id . ' AND todo_project = ' . (int) $data['project_id']);

                if (count($todos)) {
                    echo '<strong>' . CTodo::e($data['project_id'] ? $data['project_name'] : $AppUI->_($data['project_name'], UI_OUTPUT_RAW)) . '</strong>';
                    echo '<ul style="list-style-type: none;">';
                    $i = 0;
                    foreach($todos as $todoItem) {
                        $i++;
                        if ($i > 3) {
                            break;
                        }
                        $todo->renderItem($todoItem, $todoCategories);
                    }
                    echo '</ul>';
                }
            }
            ?>
        </td>
    </tr>
</table>