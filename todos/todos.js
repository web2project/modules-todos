/*
 * Complete and delete todos in the background.
 *
 * Everything lives on one object so the list can be embedded in other
 *   modules' pages (the Todolist tabs) without clashing with their global
 *   functions, such as delIt().
 *
 * web2project rejects any dosql post without the session's form token
 *   (__nonce), so it is read from the list's data-nonce attribute. The row
 *   is only hidden once the server confirms the change.
 */
var w2pTodos = {
    post: function (todoId, action) {
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
                    w2pTodos.failed(todoId);
                }
            })
            .fail(function () {
                w2pTodos.failed(todoId);
            });
    },
    failed: function (todoId) {
        $('#r' + todoId + ' input[type=checkbox]').prop('checked', false);
        alert($('#todos_list').data('error'));
    },
    complete: function (todoId) {
        w2pTodos.post(todoId, 'complete');
    },
    remove: function (todoId) {
        w2pTodos.post(todoId, 'del');
        return false;
    }
};
