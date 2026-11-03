(() => {
  if (window.appendixTextValidationReady) return;
  window.appendixTextValidationReady = true;
  const selector = 'textarea[data-appendix-text]';
  const validate = field => {
    const valid = field.disabled || (!field.required && field.value === '') || /\p{L}/u.test(field.value);
    const message = valid ? '' : '请填写文字内容，不能只填空格、符号、数字或表情。';
    field.setCustomValidity(message);
    let hint = field.nextElementSibling;
    if (!hint?.matches('[data-appendix-text-error]')) {
      hint = document.createElement('small');
      hint.dataset.appendixTextError = '';
      hint.className = 'form-error';
      hint.setAttribute('role', 'status');
      field.insertAdjacentElement('afterend', hint);
    }
    hint.textContent = message;
    hint.hidden = valid;
    if (valid) field.removeAttribute('aria-invalid');
    else field.setAttribute('aria-invalid', 'true');
    return valid;
  };
  ['input', 'change', 'focusout'].forEach(type => document.addEventListener(type, event => {
    if (event.target.matches(selector) && !event.isComposing) validate(event.target);
  }));
  document.addEventListener('compositionend', event => {
    if (event.target.matches(selector)) validate(event.target);
  });
  document.addEventListener('submit', event => {
    let firstInvalid;
    event.target.querySelectorAll(selector).forEach(field => {
      if (!validate(field) && !firstInvalid) firstInvalid = field;
    });
    if (!firstInvalid) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    firstInvalid.focus();
    firstInvalid.reportValidity();
  }, true);
})();
