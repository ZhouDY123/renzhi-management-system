(() => {
  const query = new URLSearchParams(window.location.search);
  const token = query.get('t');
  if (!token) return;

  fetch(`/h5.php?m=condition_options&t=${encodeURIComponent(token)}`, { credentials: 'same-origin' })
    .then(response => response.ok ? response.json() : {})
    .then(options => {
      Object.entries(options || {}).forEach(([field, values]) => {
        if (!Array.isArray(values) || values.length === 0) return;
        const select = document.querySelector(`select[name="${field}"]`);
        if (!select) return;
        const selected = select.value;
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
