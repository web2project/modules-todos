<?php
if (!defined('W2P_BASE_DIR')) {
    die('You should not access this file directly.');
}

global $AppUI, $project_id, $contact_id, $company_id;

$perms = $AppUI->acl();
if (!$perms->checkModuleItem('todos', 'access')) {
    $AppUI->redirect(ACCESS_DENIED);
}

$todolistTab = $AppUI->processIntState('todoListIdxTab', $_GET, 'tab', 0);

// prepare the users filter
if (isset($_POST['todo_user'])) {
    $AppUI->setState('TodoOwner', intval($_POST['todo_user']));
}
$owner = $AppUI->getState('TodoOwner') !== null ? $AppUI->getState('TodoOwner') : $AppUI->user_id;

$user_list[0] = 'All Users';
$user_list += $users = $perms->getPermittedUsers('projects');
?>
<script src="./modules/todos/todos.js" type="text/javascript"></script>
<link rel="stylesheet" type="text/css" href="./modules/todos/todos.css" />
<?php
$titleBlock = new w2p_Theme_TitleBlock('TodoList', '', $m, "$m.$a");
$titleBlock->addCell('<table><tr><form action="?m=todos" method="post" name="userIdForm" accept-charset="utf-8"><td nowrap="nowrap" align="right">' . $AppUI->_('Owner') . '</td><td nowrap="nowrap" align="left">' . arraySelect($user_list, 'todo_user', 'size="1" class="text" onChange="document.userIdForm.submit();"', $owner, false) . '</td></form></tr></table>', '', '', '');
$titleBlock->show();

$tabBox = new CTabBox("?m=$m", W2P_BASE_DIR . "/modules/$m/", $todolistTab);
$tabBox->add('vw_idx_open', 'Todo Items');
$tabBox->add('vw_gtd_active_projects', 'GTD Active Projects');
$tabBox->add('vw_gtd_all_projects', 'GTD All Projects');
$tabBox->add('vw_idx_closed', 'Closed Items');
$tabBox->show();
