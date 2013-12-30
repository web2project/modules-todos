<?php

global $AppUI;

$project = new CProject();
$projects = $project->getAllowedProjects($AppUI->user_id);

$todo = new CTodo();

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
            ?>
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
            <?php
            //todo: if there are any, show top three
        }
        echo '</ul>';
    }
}