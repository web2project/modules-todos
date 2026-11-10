<?php
if (!defined('W2P_BASE_DIR'))
{
    die('You should not access this file directly.');
}
global $AppUI, $m, $project_id, $contact_id, $company_id, $owner;

$todo = new CTodo();
$todoCategories = w2PgetSysVal('TodoType');
$todoList = $todo->getClosedTodosForDateRange($owner);

if (count($todoList)) {
    // This file runs inside w2p_Theme_TabBox::show(), so the page links take
    //   the tab from the saved tab state.
    $tab = (int) $AppUI->getState('todoListIdxTab', 0);
    $paginator = new w2p_Utilities_Paginator($todoList);
    $todoItems = $paginator->getItemsOnPage((int) w2PgetParam($_GET, 'page', 1));

    echo $paginator->buildNavigation($AppUI, $m, $tab);
    ?>
    <table class="table table-sm align-middle">
        <thead>
            <tr>
                <th scope="col" class="text-nowrap"><?php echo $AppUI->_('Due'); ?></th>
                <th scope="col"><?php echo $AppUI->_('Todo'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($todoItems as $todoItem) { ?>
            <tr id="row_<?php echo (int) $todoItem['todo_id']; ?>">
                <td class="text-nowrap">
                    <?php
                    // stored in UTC, shown as the user's date
                    echo CTodo::hasDueDate($todoItem['todo_due_date'])
                        ? $AppUI->formatTZAwareTime($todoItem['todo_due_date'], '%Y-%m-%d')
                        : $AppUI->_('Later');
                    ?>
                </td>
                <td id="cell_<?php echo (int) $todoItem['todo_id']; ?>">
                    <em class="text-body-secondary"><?php echo CTodo::e(isset($todoCategories[$todoItem['todo_category']]) ? $todoCategories[$todoItem['todo_category']] : ''); ?></em>
                    <?php echo w2p_textarea($todoItem['todo_name']); ?>
                    <?php if ($todoItem['todo_project'] > 0) {
                        $color = CTodo::safeColor($todoItem['project_color_identifier']); ?>
                        <a class="badge text-decoration-none" style="background-color: #<?php echo $color; ?>; color: <?php echo bestColor($color); ?>;" href="./index.php?m=projects&amp;a=view&amp;project_id=<?php echo (int) $todoItem['todo_project']; ?>"><?php echo CTodo::e($todoItem['project_name']); ?></a>
                    <?php } ?>
                    <?php echo $todo->renderContact($todoItem); ?>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php
} else {
    echo '<p class="text-body-secondary">' . $AppUI->_('No closed items.') . '</p>';
}
