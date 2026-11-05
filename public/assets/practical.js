document.addEventListener('DOMContentLoaded', () => {
  const questions = document.querySelector('[data-practical-questions]');
  const renumber = () => questions?.querySelectorAll('[data-question-label]').forEach((el, i) => el.textContent = `题目 ${i + 1}`);
  document.querySelector('[data-add-question]')?.addEventListener('click', () => {
    if (questions.children.length >= 30) return;
    const row = questions.firstElementChild.cloneNode(true);
    row.querySelector('textarea').value = ''; questions.append(row); renumber(); row.querySelector('textarea').focus();
  });
  questions?.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-question]');
    if (button && questions.children.length > 1) { button.closest('.practical-config-question').remove(); renumber(); }
  });
  const entry = document.querySelector('[data-practical-entry]');
  if (entry) entry.textContent = new URL('/practical.php', location.href).href;
  const cards = [...document.querySelectorAll('.practical-question')];
  const finalForm = document.querySelector('.practical-submit');
  const pending = new Set(); let uploads = 0;
  function progress() {
    const saved = cards.filter(card => card.dataset.uploaded === '1').length;
    const text = document.querySelector('[data-practical-progress]');
    if (text) text.textContent = `已上传 ${saved} / ${cards.length} 道题${pending.size ? ` · ${pending.size} 道题有待保存修改` : ''}`;
    if (finalForm) finalForm.querySelector('button').disabled = saved !== cards.length || uploads > 0 || pending.size > 0;
  }
  document.querySelectorAll('.practical-upload-form').forEach(form => {
    const card = form.closest('.practical-question'), status = form.querySelector('.practical-upload-status');
    form.addEventListener('input', () => { pending.add(form); progress(); });
    form.addEventListener('change', () => { pending.add(form); progress(); });
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (form.dataset.busy) return;
      const file = form.elements.result_file.files[0];
      if (!file) return;
      if (file.size > Number(form.dataset.limit) || !file.size) { status.textContent = '文件为空或超过大小上限，请重新选择。'; return; }
      const data = new FormData(form);
      form.dataset.busy = '1'; uploads++; progress();
      const fields = [...form.querySelectorAll('input,textarea,button')]; fields.forEach(el => el.disabled = true);
      status.textContent = '正在上传，请勿关闭页面…';
      try {
        const response = await fetch(form.action, {method: 'POST', body: data, headers: {Accept: 'application/json'}, credentials: 'same-origin'});
        let result;try { result = await response.json(); } catch { throw new Error('上传未成功，可能超出服务器限制或身份已过期；已保存的成果不会丢失。'); }
        if (!response.ok || !result.ok) throw new Error(result.error || '上传失败，请重试');
        const link = document.createElement('a'); link.href = result.url; link.textContent = result.name;
        const size = result.bytes < 1024 ? `${result.bytes} B` : result.bytes < 1048576 ? `${(result.bytes / 1024).toFixed(1)} KB` : `${(result.bytes / 1048576).toFixed(1)} MB`;
        const meta = document.createElement('small'); meta.textContent = `${size} · ${result.uploaded_at}`;
        card.querySelector('.practical-upload-result').replaceChildren(link, meta);
        card.dataset.uploaded = '1'; card.querySelector('.practical-file-state').textContent = '已上传成果'; card.querySelector('.practical-file-state').classList.add('saved');
        form.elements.result_file.value = ''; pending.delete(form); status.textContent = '本题成果已保存，可继续完成其他题目。';
      } catch (error) { status.textContent = error.message; }
      finally { delete form.dataset.busy; fields.forEach(el => el.disabled = false); uploads--; progress(); }
    });
  });
  finalForm?.addEventListener('submit', event => {
    if (pending.size || uploads) { event.preventDefault(); return; }
    const button = finalForm.querySelector('button'); button.disabled = true; button.textContent = '正在提交…';
  });
  window.addEventListener('beforeunload', event => { if (pending.size || uploads) { event.preventDefault(); event.returnValue = ''; } });
  progress();
});
