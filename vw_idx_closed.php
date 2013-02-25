<?php
if (!defined('W2P_BASE_DIR'))
{
    die('You should not access this file directly.');
}
global $AppUI, $project_id, $contact_id, $company_id, $tab, $owner;

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
                    echo '<tr><td colspan="2" align="center">';
                    $page = w2PgetParam($_GET, 'page', 1);
                    $tab = 1;
                    $xpg_pagesize = w2PgetConfig('page_size', 50);
                    $xpg_min = $xpg_pagesize * ($page - 1); // This is where we start our record set from
                    echo buildPaginationNav($AppUI, $m, $tab, $todoCount, $xpg_pagesize, $page);
                    echo '</td></tr>';

                    for ($i = ($xpg_min); $i < ($page * $xpg_pagesize); $i++)
                    {
                        $todoItem = $todoList[$i];
                        if ($lastDate != substr($todoItem['todo_closed'], 0, 10)) {
                            ?><tr><td colspan="2"><hr /></td></tr><?php
                        }
                        ?>
                        <tr id="row_<?php echo $todoItem['todo_id']; ?>">
                            <td nowrap="true">
                                <?php echo substr($todoItem['todo_closed'], 0, 10); ?>
                            </td>
                            <td id="cell_<?php echo $todoItem['todo_id']; ?>">
                                <em><?php echo $todoCategories[$todoItem['todo_category']]; ?></em>
                                <?php if ($todoItem['display_date'] > 0 && date('Y', strtotime($todoItem['display_date'])) != 2020) { ?>
                                    <?php echo date('M d', strtotime($todoItem['display_date']));  ?> -
                                <?php } ?>
                                <?php echo w2p_textarea($todoItem['todo_title']); ?>
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
                            </td>
                        </tr>
                        <?php
                        $lastDate = substr($todoItem['todo_closed'], 0, 10);
                    }
                }
            ?>
            </table>
        </td>
    </tr>
</table>