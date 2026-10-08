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
  const preview = document.querySelector('.appendix-preview-modal');
  if (!preview) return;
  let previewOpener = null;
  const node = (tag, text, className) => {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    if (className) element.className = className;
    return element;
  };
  document.querySelectorAll('[data-appendix-preview]').forEach(button => button.addEventListener('click', () => {
    const question = JSON.parse(button.dataset.appendixPreview);
    const body = preview.querySelector('.appendix-preview-body');
    body.replaceChildren();
    body.append(node('h3', question.section, 'appendix-section'));
    if (Number(question.enabled) !== 1) body.append(node('p', '此题已停用，求职者当前不会看到。', 'appendix-preview-status'));
    const field = node('fieldset', undefined, 'appendix-item');
    const legend = node('legend', question.title);
    legend.append(node('small', '选填'));
    field.append(legend);
    if (question.help) field.append(node('p', question.help));
    const values = JSON.parse(question.options_json || '[]');
    if (values.length) {
      const choices = node('div', undefined, 'appendix-options');
      values.forEach(value => {
        const label = node('label');
        const input = node('input');
        input.type = 'radio';input.name = 'appendix-preview-answer';input.value = value;
        label.append(input, node('span', value));choices.append(label);
      });
      field.append(choices);
    } else {
      const input = node('textarea');
      input.rows = 3;input.maxLength = 2000;
      input.placeholder = '自愿填写，也可留空或填写不提供';
      input.setAttribute('aria-label', question.title);
      field.append(input);
    }
    if (question.supplement) {
      const label = node('label', question.supplement, 'appendix-note');
      const input = node('textarea');
      input.rows = 2;input.maxLength = 2000;input.placeholder = '补充说明（选填）';
      label.append(input);field.append(label);
    }
    body.append(field);
    previewOpener = button;
    preview.showModal();body.scrollTop = 0;
    document.body.classList.add('appendix-dialog-open');
  }));
  preview.querySelectorAll('[data-appendix-preview-close]').forEach(button => button.addEventListener('click', () => preview.close()));
  preview.addEventListener('close', () => {
    document.body.classList.remove('appendix-dialog-open');
    previewOpener?.focus({preventScroll:true});
  });
})();
