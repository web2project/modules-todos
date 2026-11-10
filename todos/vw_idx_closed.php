<?php
if (!defined('W2P_BASE_DIR'))
{
    die('You should not access this file directly.');
}
global $AppUI, $m, $project_id, $contact_id, $company_id, $owner;

$todo = new CTodo();
$todoCategories = w2PgetSysVal('TodoType');
//TODO: this whole table mess should be converted over to nice divs
?>
<table width="100%">
    <tr>
        <td valign="top">
            <table width="100%">
            <?php
                $todoList = $todo->getClosedTodosForDateRange($owner);
                $todoCount = count($todoList);

                if ($todoCount > 0)
                {
                    // This file runs inside w2p_Theme_TabBox::show(), so the
                    //   page links take the tab from the saved tab state.
                    $tab = (int) $AppUI->getState('todoListIdxTab', 0);
                    $paginator = new w2p_Utilities_Paginator($todoList);
                    $todoItems = $paginator->getItemsOnPage((int) w2PgetParam($_GET, 'page', 1));

                    echo '<tr><td colspan="2" align="center">';
                    echo $paginator->buildNavigation($AppUI, $m, $tab);
                    echo '</td></tr>';

                    $lastDate = null;
                    foreach ($todoItems as $todoItem)
                    {
                        if ($lastDate != substr($todoItem['todo_due_date'], 0, 10)) {
                            ?><tr><td colspan="2"><hr /></td></tr><?php
                        }
                        ?>
                        <tr id="row_<?php echo $todoItem['todo_id']; ?>">
                            <td nowrap="true" width="5%">
                                <?php if (CTodo::hasDueDate($todoItem['todo_due_date'])) { ?>
                                    <?php echo substr($todoItem['todo_due_date'], 0, 10); ?>
                                <?php } else { ?>
                                    <?php echo $AppUI->_('Later'); ?>
                                <?php } ?>
                            </td>
                            <td id="cell_<?php echo $todoItem['todo_id']; ?>">
                                <em><?php echo CTodo::e(isset($todoCategories[$todoItem['todo_category']]) ? $todoCategories[$todoItem['todo_category']] : ''); ?></em>
                                <?php if (CTodo::hasDueDate($todoItem['todo_due_date'])) { ?>
                                    <?php echo date('M d', strtotime($todoItem['todo_due_date']));  ?> -
                                <?php } ?>
                                <?php echo w2p_textarea($todoItem['todo_name']); ?>
                                <?php if ($todoItem['todo_project'] > 0) { ?>
                                    <span style="padding: 2px; background-color: #<?php echo CTodo::safeColor($todoItem['project_color_identifier']); ?>;">
                                        <a href="./index.php?m=projects&amp;a=view&amp;project_id=<?php echo $todoItem['todo_project']; ?>" style="color: <?php echo bestColor(CTodo::safeColor($todoItem['project_color_identifier'])) ?>;"><?php echo CTodo::e($todoItem['project_name']); ?></a>
                                    </span>
                                <?php } ?>
                                <?php echo $todo->renderContact($todoItem); ?>
                            </td>
                        </tr>
                        <?php
                        $lastDate = substr($todoItem['todo_due_date'], 0, 10);
                    }
                }
            ?>
            </table>
        </td>
    </tr>
</table>