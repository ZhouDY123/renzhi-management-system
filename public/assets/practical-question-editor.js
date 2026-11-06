document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('practical-question-dialog');
  if (!dialog) return;
  const form = document.getElementById('practical-question-form'), input = form.elements.stem;
  const host = document.getElementById('practical-rich-editor');
  const discard = document.getElementById('practical-question-discard');
  const status = document.getElementById('practical-image-status');
  const save = dialog.querySelector('.practical-editor-footer .primary');
  let initial = '', busy = false, pending = 0, editor, changed = false, loading = false, resetImageResize;
  function sync() {
    if (editor && changed) input.value = editor.getMarkdown();
    const length = Array.from(input.value).length;
    dialog.querySelector('[data-practical-length]').textContent = `${length} / 5000`;
    save.disabled = pending > 0 || length > 5000;
    return length;
  }
  async function uploadImage(file, callback) {
    pending++;sync();status.textContent = '正在上传图片，请稍候…';
    try {
      if (!/^image\/(png|jpeg|gif|webp)$/.test(file.type) || file.size > 5*1024*1024) throw new Error('请使用不超过 5 MB 的 PNG、JPG、GIF 或 WebP 图片');
      if (sync() + 110 > 5000) throw new Error('剩余字数不足，请精简后再插入图片');
      const body = new FormData();body.append('csrf',form.elements.csrf.value);body.append('image',file);
      const url = new URL(location.href);url.searchParams.set('action','upload_image');
      const response = await fetch(url,{method:'POST',body,headers:{Accept:'application/json'}});
      let result;
      try { result = await response.json(); } catch { throw new Error('上传失败或登录已过期，请重试'); }
      if (!response.ok || !result.url) throw new Error(result.error || '图片上传失败');
      callback(result.url, '题目图片');
      changed = true;sync();status.textContent = '图片已插入，可继续编辑图文。';
    } catch (error) { status.textContent = error.message; }
    finally { pending--;sync(); }
  }
  function initEditor(value) {
    loading = true;
    if (!editor && window.toastui?.Editor) {
      editor = new toastui.Editor({
        el:host,height:'390px',minHeight:'260px',initialEditType:'wysiwyg',previewStyle:'tab',
        initialValue:value,language:'zh-CN',usageStatistics:false,autofocus:false,
        placeholder:'请输入题目内容，可直接粘贴图片，并在图片前后输入文字。',
        toolbarItems:[['heading','bold','italic'],['quote','ul','ol'],['table','link','image'],['code','codeblock']],
        customHTMLSanitizer:html=>DOMPurify.sanitize(html,{USE_PROFILES:{html:true},FORBID_TAGS:['style','iframe','form','input'],FORBID_ATTR:['style']}),
        hooks:{addImageBlobHook:(file,callback)=>{uploadImage(file,callback);return false;}},
        events:{change:()=>{if(!loading){changed=true;sync();}}}
      });
      resetImageResize = attachPracticalImageResize(editor,host);
      input.hidden = true;input.required = false;
      host.querySelector('.toastui-editor-ww-container [contenteditable]')?.setAttribute('aria-label','题目内容');
    } else if (editor) {
      editor.setMarkdown(value,false);editor.changeMode('wysiwyg',true);
    }
    resetImageResize?.();
    changed = false;loading = false;sync();
  }
  function open(row) {
    form.reset();form.querySelectorAll('.field-error,.form-error').forEach(el=>el.remove());
    form.elements.operation.value = row ? 'update' : 'create';
    form.elements.question_id.value = row?.dataset.practicalQuestion || '0';
    form.elements.revision.value = row?.dataset.revision || '0';
    input.value = row?.querySelector('[data-practical-stem]').dataset.markdownSource || '';initial=input.value;
    status.textContent='';
    document.getElementById('practical-dialog-title').textContent=row?'编辑实操题目':'新增实操题目';
    dialog.showModal();document.body.classList.add('practical-editor-open');initEditor(input.value);
    if(editor)editor.focus();else input.focus();
  }
  function close() {
    if(busy||pending)return;
    sync();
    if(input.value!==initial){discard.showModal();discard.querySelector('[data-practical-continue]').focus();}
    else dialog.close();
  }
  document.querySelector('[data-practical-create]')?.addEventListener('click',()=>open(null));
  document.querySelectorAll('[data-practical-edit]').forEach(button=>button.addEventListener('click',()=>open(button.closest('[data-practical-question]'))));
  dialog.querySelectorAll('[data-practical-close]').forEach(button=>button.addEventListener('click',close));
  dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
  dialog.addEventListener('close',()=>document.body.classList.remove('practical-editor-open'));
  discard.querySelector('[data-practical-continue]').addEventListener('click',()=>discard.close());
  discard.querySelector('[data-practical-discard]').addEventListener('click',()=>{discard.close();dialog.close();});
  input.addEventListener('input',sync);
  form.addEventListener('submit',event=>{
    const length=sync();
    if(busy||pending||!input.value.trim()||length>5000){
      event.preventDefault();event.stopImmediatePropagation();
      status.textContent=pending?'请等待图片上传完成':length>5000?'题目内容不能超过 5000 字':'请输入题目内容';
      if(editor)editor.focus();else input.focus();return;
    }
    if(!form.checkValidity())return;
    busy=true;
  },true);
  if(dialog.dataset.reopen==='1'){
    initial=input.value;dialog.showModal();document.body.classList.add('practical-editor-open');initEditor(input.value);
  }
});
