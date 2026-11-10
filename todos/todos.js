/*
 * Completes or deletes a todo in the background.
 *
 * web2project rejects any dosql post without the session's form token
 *   (__nonce), so it is read from the list's data-nonce attribute. The row
 *   is only hidden once the server confirms the change.
 */
function postTodoAction(todoId, action) {
    var list = $('#todos_list');
    var data = {
        dosql: 'do_todo_aed',
        todo_id: todoId,
        ajax: 1,
        __nonce: list.data('nonce')
    };
    data[action] = 1;

    $.post('./index.php?m=todos', data, null, 'json')
        .done(function (response) {
            if (response && response.ok) {
                $('#r' + todoId).hide();
            } else {
                todoActionFailed(todoId);
            }
        })
        .fail(function () {
            todoActionFailed(todoId);
        });
}
function todoActionFailed(todoId) {
    $('#r' + todoId + ' input[type=checkbox]').prop('checked', false);
    alert($('#todos_list').data('error'));
}
function markItemComplete(todoId) {
    postTodoAction(todoId, 'complete');
}
function delIt(todoId) {
    postTodoAction(todoId, 'del');
}
