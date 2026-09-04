(() => {
  document.querySelectorAll('.h5-form .checkline').forEach(field => field.remove());

  const query = new URLSearchParams(window.location.search);
  const token = query.get('t');
  if (!token) return;

  fetch(`/h5.php?m=condition_options&t=${encodeURIComponent(token)}`, { credentials: 'same-origin' })
    .then(response => response.ok ? response.json() : {})
    .then(options => {
      Object.entries(options || {}).forEach(([field, values]) => {
        if (!Array.isArray(values) || values.length === 0) return;
        let select = document.querySelector(`select[name="${field}"]`);
        const input = document.querySelector(`input[name="${field}"]`);
        if (!select && input) {
          select = document.createElement('select');
          select.name = input.name;
          select.required = input.required;
          select.setAttribute('aria-label', input.closest('label')?.firstChild?.textContent?.trim() || field);
          input.replaceWith(select);
        }
        if (!select) return;
        const selected = input?.value || select.value;
        select.replaceChildren();
        values.forEach(value => {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = value;
          select.append(option);
        });
        if (values.includes(selected)) select.value = selected;
      });
    })
    .catch(() => {});
})();
