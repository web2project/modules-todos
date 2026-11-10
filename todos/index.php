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

$user_list[0] = $AppUI->_('All Users');
$user_list += $users = $perms->getPermittedUsers('projects');

$titleBlock = new w2p_Theme_TitleBlock('TodoList', '', $m);
$titleBlock->addFilterCell('Owner', 'todo_user', $user_list, $owner);
$titleBlock->show();

$tabBox = new CTabBox("?m=$m", W2P_BASE_DIR . "/modules/$m/", $todolistTab);
$tabBox->add('vw_idx_open', 'Todo Items');
$tabBox->add('vw_gtd_active_projects', 'GTD Active Projects');
$tabBox->add('vw_gtd_all_projects', 'GTD All Projects');
$tabBox->add('vw_idx_closed', 'Closed Items');
$tabBox->show();
