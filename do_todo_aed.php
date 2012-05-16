<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
    die('You should not access this file directly.');
}

$del = (int) w2PgetParam($_POST, 'del', 0);
$complete = (int) w2PgetParam($_POST, 'complete', 0);
$return_module = w2PgetParam($_POST, 'return_module', 'todos');
$return_module = (canAccess($return_module)) ? $return_module : 'todos';

$obj = new CTodo();
if (!$obj->bind($_POST)) {
    $AppUI->setMsg($obj->getError(), UI_MSG_ERROR);
    $AppUI->redirect();
}

$action = ($del) ? 'deleted' : 'stored';
if ($complete) {
    $result = $obj->complete($AppUI);
} else {
    $result = ($del) ? $obj->delete($AppUI) : $obj->store($AppUI);
}

if (is_array($result)) {
    $AppUI->setMsg($result, UI_MSG_ERROR, true);
    $AppUI->holdObject($obj);
    $AppUI->redirect('m=todos');
}
if ($result) {
    $AppUI->setMsg('Todos '.$action, UI_MSG_OK, true);
    switch($return_module) {
        case 'projects':
            $success = 'm=projects&a=view&project_id='.$obj->todo_project_id;
            break;
        case 'contacts':
            $success = 'm=contacts&a=view&contact_id='.$obj->todo_related_to_contact_id;
            break;
        default:
            $success = 'm=todos';
            break;
    }
    $AppUI->redirect($success);
} else {
    $AppUI->redirect('m=public&a=access_denied');
}