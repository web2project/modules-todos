function markItemComplete(todoId) {
  $.post("./index.php?m=todos", {
      dosql: 'do_todo_aed',
			todo_id: todoId,
			complete: '1'
		}
	);
  $('#r' + todoId).hide();
}
function delIt(todoId) {
  $.post("./index.php?m=todos", {
      dosql: 'do_todo_aed',
      todo_id: todoId,
      del: '1'
    }
  );
  $('#r' + todoId).hide();
}