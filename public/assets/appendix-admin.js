(() => {
  const dialog = document.querySelector('.appendix-modal');
  if (!dialog) return;
  const form = dialog.querySelector('form');
  const type = form.elements.type;
  const options = form.elements.options;
  let opener = null;
  function updateType() {
    const select = type.value === 'select';
    form.querySelector('[data-appendix-options]').hidden = !select;
    options.disabled = !select;
    options.required = select;
    if (!select) {
      options.removeAttribute('aria-invalid');
      options.closest('label').querySelectorAll('.field-error').forEach(el => el.remove());
    }
  }
  function show(data, button) {
    opener = button;
    form.reset();
    form.querySelectorAll('.form-error,.field-error').forEach(el => el.remove());
    form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
    for (const key of ['id','title','section','help','supplement','sort']) form.elements[key].value = data[key] ?? '';
    const values = JSON.parse(data.options_json || '[]');
    type.value = values.length ? 'select' : 'text';
    options.value = values.join('\n');
    form.elements.enabled.checked = Number(data.enabled) === 1;
    dialog.querySelector('h2').textContent = Number(data.id) ? '编辑附录题目' : '新增附录题目';
    updateType();
    dialog.showModal();
    document.body.classList.add('appendix-dialog-open');
    dialog.querySelector('.appendix-modal-body').scrollTop = 0;
    form.elements.title.focus({preventScroll:true});
  }
  document.querySelectorAll('[data-appendix-create]').forEach(button => button.addEventListener('click', () => show(JSON.parse(dialog.dataset.defaults), button)));
  document.querySelectorAll('[data-appendix-edit]').forEach(button => button.addEventListener('click', () => show(JSON.parse(button.dataset.appendixEdit), button)));
  dialog.querySelectorAll('[data-appendix-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => {
    document.body.classList.remove('appendix-dialog-open');
    opener?.focus({preventScroll:true});
  });
  type.addEventListener('change', updateType);
  updateType();
  if (dialog.dataset.autoOpen === '1') {
    dialog.showModal();document.body.classList.add('appendix-dialog-open');
  }
})();
