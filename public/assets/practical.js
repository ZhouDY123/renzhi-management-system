document.addEventListener('DOMContentLoaded', () => {
  const entry = document.querySelector('[data-practical-entry]');
  if (entry) entry.textContent = new URL('/practical.php', location.href).href;
  const cards = [...document.querySelectorAll('.practical-question')];
  const finalForm = document.querySelector('.practical-submit');
  const pending = new Set(), savers = new Map(); let uploads = 0, submitting = false;
  function progress() {
    const saved = cards.filter(card => card.dataset.uploaded === '1').length;
    const heroProgress = document.querySelector('[data-practical-hero-progress]');
    if (heroProgress) heroProgress.textContent = `共 ${cards.length} 道任务 · 已保存 ${saved} 道成果`;
    const text = document.querySelector('[data-practical-progress]');
    if (text) text.textContent = `已上传 ${saved} / ${cards.length} 道题${pending.size ? ` · ${pending.size} 道题有待保存修改` : ''}`;
    if (finalForm) finalForm.querySelector('button').disabled = uploads > 0 || submitting;
  }
  document.querySelectorAll('.practical-upload-form').forEach(form => {
    const card = form.closest('.practical-question'), status = form.querySelector('.practical-upload-status');
    const note = form.elements.note; let savedNote = note.value;
    const changed = () => {
      if (form.elements.result_file.files.length || note.value !== savedNote) pending.add(form); else pending.delete(form);
      progress();
    };
    form.elements.result_file.required = card.dataset.uploaded !== '1';
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    const save = async () => {
      if (form.dataset.busy) return false;
      const file = form.elements.result_file.files[0];
      if (!form.reportValidity()) return false;
      if (file && (file.size > Number(form.dataset.limit) || !file.size)) { status.textContent = '文件为空或超过大小上限，请重新选择。'; return false; }
      const data = new FormData(form);
      form.dataset.busy = '1'; uploads++; progress();
      const fields = [...form.querySelectorAll('input,textarea,button')]; fields.forEach(el => el.disabled = true);
      status.textContent = '正在上传，请勿关闭页面…';
      try {
        const result = await new Promise((resolve, reject) => {
          const request = new XMLHttpRequest();
          request.open('POST', form.action); request.setRequestHeader('Accept', 'application/json');
          request.timeout = 30 * 60 * 1000;
          request.upload.addEventListener('progress', event => {
            if (!event.lengthComputable) return;
            const percent = Math.floor(event.loaded / event.total * 100);
            status.textContent = percent >= 100 ? '文件已传输，正在保存，请勿关闭页面…' : `正在上传 ${percent}%（${(event.loaded / 1048576).toFixed(1)} / ${(event.total / 1048576).toFixed(1)} MB），请勿关闭页面…`;
          });
          request.addEventListener('load', () => {
            let response;
            try { response = JSON.parse(request.responseText); } catch {
              reject(new Error(request.status === 413 ? '文件超过服务器请求大小限制，请联系管理员调整；已保存的成果不会丢失。' : '上传未成功，可能超出服务器限制或身份已过期；已保存的成果不会丢失。')); return;
            }
            if (request.status < 200 || request.status >= 300 || !response.ok) reject(new Error(response.error || '上传失败，请重试'));
            else resolve(response);
          });
          request.addEventListener('error', () => reject(new Error('网络连接中断，请检查网络后重新上传；已保存的成果不会丢失。')));
          request.addEventListener('timeout', () => reject(new Error('上传超时，请在网络稳定后重试；已保存的成果不会丢失。')));
          request.addEventListener('abort', () => reject(new Error('上传已中止，请重新选择文件上传。')));
          request.send(data);
        });
        const link = document.createElement('a'); link.href = result.url; link.textContent = result.name;
        const size = result.bytes < 1024 ? `${result.bytes} B` : result.bytes < 1048576 ? `${(result.bytes / 1024).toFixed(1)} KB` : `${(result.bytes / 1048576).toFixed(1)} MB`;
        const meta = document.createElement('small'); meta.textContent = `${size} · ${result.uploaded_at}`;
        card.querySelector('.practical-upload-result').replaceChildren(link, meta);
        card.dataset.uploaded = '1'; card.querySelector('.practical-file-state').textContent = '已上传成果'; card.querySelector('.practical-file-state').classList.add('saved');
        form.elements.result_file.value = ''; form.elements.result_file.required = false; savedNote = note.value;
        pending.delete(form); status.textContent = '本题成果已保存，可继续完成其他题目。'; return true;
      } catch (error) { status.textContent = error.message; return false; }
      finally { delete form.dataset.busy; fields.forEach(el => el.disabled = false); uploads--; progress(); }
    };
    savers.set(form, save);
    form.addEventListener('submit', event => { event.preventDefault(); if (!submitting) save(); });
  });
  finalForm?.addEventListener('submit', async event => {
    event.preventDefault(); if (submitting || uploads) return;
    const forms = [...savers.keys()], button = finalForm.querySelector('button');
    const message = finalForm.querySelector('[data-submit-status]');
    for (const form of forms) {
      if (!form.checkValidity()) {
        if (message) message.textContent = '请为每道题选择成果文件，并检查填写内容。';
        form.scrollIntoView({block:'center',behavior:'smooth'}); form.reportValidity(); return;
      }
    }
    submitting = true; progress(); let sent = false;
    // Lock all questions during batch submission so later edits cannot be missed.
    forms.forEach(form => form.querySelectorAll('input[type=file],textarea,button').forEach(el => el.inert = true));
    try {
      for (const [index, form] of forms.entries()) {
        if (pending.has(form) || form.closest('.practical-question').dataset.uploaded !== '1') {
          button.textContent = `正在保存第 ${index + 1} 题…`;
          if (message) message.textContent = '正在逐题上传并保存，全部成功后自动提交，请勿关闭页面。';
          if (!await savers.get(form)()) {
            if (message) message.textContent = `第 ${index + 1} 题保存失败，请查看该题提示后重试；其他已保存成果会保留。`;
            form.scrollIntoView({block:'center',behavior:'smooth'}); return;
          }
        }
      }
      button.textContent = '正在提交…';
      HTMLFormElement.prototype.submit.call(finalForm);
      sent = true;
    } finally {
      if (!sent) {
        submitting = false; forms.forEach(form => form.querySelectorAll('input[type=file],textarea,button').forEach(el => el.inert = false));
        button.textContent = '确认提交全部成果'; progress();
      }
    }
  });
  window.addEventListener('beforeunload', event => { if (pending.size || uploads) { event.preventDefault(); event.returnValue = ''; } });
  progress();
});
