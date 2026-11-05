document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('practical-question-dialog');
  if (!dialog) return;
  const form = document.getElementById('practical-question-form'), input = form.elements.stem;
  const discard = document.getElementById('practical-question-discard');
  let initial = '', busy = false;
  const count = () => { dialog.querySelector('[data-practical-length]').textContent = `${Array.from(input.value).length} / 5000`; input.setCustomValidity(''); };
  function open(row) {
    form.reset();form.querySelectorAll('.field-error,.form-error').forEach(el => el.remove());
    form.elements.operation.value = row ? 'update' : 'create';
    form.elements.question_id.value = row?.dataset.practicalQuestion || '0';
    form.elements.revision.value = row?.dataset.revision || '0';
    input.value = row?.querySelector('[data-practical-stem]').textContent || ''; initial = input.value;
    document.getElementById('practical-dialog-title').textContent = row ? '编辑实操题目' : '新增实操题目';
    count();dialog.showModal();document.body.classList.add('practical-editor-open');input.focus();
  }
  function close() {
    if (busy) return;
    if (input.value !== initial) { discard.showModal(); discard.querySelector('[data-practical-continue]').focus(); }
    else dialog.close();
  }
  document.querySelector('[data-practical-create]')?.addEventListener('click', () => open(null));
  document.querySelectorAll('[data-practical-edit]').forEach(button => button.addEventListener('click', () => open(button.closest('[data-practical-question]'))));
  dialog.querySelectorAll('[data-practical-close]').forEach(button => button.addEventListener('click', close));
  dialog.addEventListener('cancel', event => { event.preventDefault();close(); });
  dialog.addEventListener('close', () => document.body.classList.remove('practical-editor-open'));
  discard.querySelector('[data-practical-continue]').addEventListener('click', () => discard.close());
  discard.querySelector('[data-practical-discard]').addEventListener('click', () => { discard.close();dialog.close(); });
  input.addEventListener('input', count);
  form.addEventListener('submit', event => {
    if (busy) { event.preventDefault(); event.stopImmediatePropagation(); return; }
    if (!input.value.trim()) {
      event.preventDefault();event.stopImmediatePropagation();input.setCustomValidity('请输入题目内容');input.reportValidity();return;
    }
    if (!form.checkValidity()) return;
    busy = true;
  }, true);
  if (dialog.dataset.reopen === '1') { count();dialog.showModal();document.body.classList.add('practical-editor-open');input.focus(); }
});
