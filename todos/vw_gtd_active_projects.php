<?php

global $AppUI;

$project = new CProject();
$projects = $project->getAllowedProjects($AppUI->user_id);

$todo = new CTodo();
$todoCategories = w2PgetSysVal('TodoType');

foreach ($projects as $data) {
    $todos = $todo->loadAll('todo_due_date ASC', 'todo_status = 1 AND todo_owner = ' . $AppUI->user_id . ' AND todo_project = ' . $data['project_id']);

    if (count($todos)) {
        echo '<strong>'.$data['project_name'].'</strong>';
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