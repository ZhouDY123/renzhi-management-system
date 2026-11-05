document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('practical-preview');
  if (!dialog) return;
  const content = dialog.querySelector('.practical-preview-content');
  let controller;
  document.querySelectorAll('[data-result-preview]').forEach(button => button.addEventListener('click', async () => {
    controller?.abort(); controller = new AbortController();
    const signal = controller.signal, url = button.dataset.resultPreview;
    document.getElementById('practical-preview-title').textContent = button.dataset.filename;
    dialog.querySelector('[data-preview-download]').href = url.replace('action=preview', 'action=download');
    content.textContent = '正在加载预览…'; dialog.showModal(); document.body.classList.add('practical-editor-open');
    const fail = () => { if (!signal.aborted) content.textContent = '无法预览此文件，请下载原文件查看。'; };
    if (button.dataset.kind === 'text') {
      try { const response = await fetch(url, {signal}); if (!response.ok) throw Error(); const value = await response.text(); if (signal.aborted) return; const pre = document.createElement('pre'); pre.textContent = value; content.replaceChildren(pre); } catch { fail(); }
    } else {
      const media = document.createElement(button.dataset.kind === 'pdf' ? 'iframe' : 'img');
      if (media.tagName === 'IMG') { media.alt = button.dataset.filename; media.onload = () => { if (!signal.aborted) content.replaceChildren(media); }; media.onerror = fail; media.src = url; }
      else { media.title = button.dataset.filename; media.src = url; content.replaceChildren(media); }
    }
  }));
  dialog.querySelector('[data-preview-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { controller?.abort(); content.replaceChildren(); document.body.classList.remove('practical-editor-open'); });
});
