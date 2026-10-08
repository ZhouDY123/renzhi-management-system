(() => {
  const dialog = document.querySelector('.appendix-detail-modal');
  if (!dialog) return;
  const body = dialog.querySelector('.appendix-detail-body');
  const retry = dialog.querySelector('[data-detail-retry]');
  let controller, opener, answerId;
  const node = (tag, className, text) => {
    const el = document.createElement(tag);el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
  };
  function render(data) {
    body.replaceChildren();
    const meta=node('section','appendix-detail-meta');
    const person=node('div','');person.append(node('small','','求职者 / 应聘岗位'),node('h3','',data.name),node('p','',data.post_name));
    meta.append(person,node('span','appendix-record-status '+data.state.tone,data.state.label));
    const time=node('dl','appendix-detail-time');
    for(const [label,value] of [['测评提交',data.submit_at],['附录提交',data.completed_at]]){const item=node('div','');item.append(node('dt','',label),node('dd','',value||'—'));time.append(item);}
    body.append(meta,time);
    if(!data.questions.length){const empty=node('div','appendix-detail-empty');empty.append(node('span','',data.state.label),node('p','',data.state.description));body.append(empty);return;}
    let section=null;
    data.questions.forEach((q,index)=>{
      if(q.section!==section){section=q.section;body.append(node('h3','appendix-detail-section',section));}
      const item=node('article','appendix-detail-question');
      const title=node('h4','');title.append(node('span','',String(index+1)),document.createTextNode(q.title));item.append(title);
      item.append(node('p',q.value?'appendix-detail-answer':'appendix-detail-unanswered',q.value||'未填写'));
      if(q.note){const note=node('div','appendix-detail-note');note.append(node('small','','补充说明'),node('p','',q.note));item.append(note);}
      body.append(item);
    });
  }
  async function load() {
    controller?.abort();controller=new AbortController();const current=controller;
    retry.hidden=true;body.replaceChildren(node('p','appendix-detail-loading','正在加载附录信息…'));body.setAttribute('aria-busy','true');
    try {
      const response=await fetch('/index.php?page=preregister&appendix_id='+encodeURIComponent(answerId),{credentials:'same-origin',signal:current.signal,headers:{Accept:'application/json'}});
      if(response.redirected)throw new Error('登录已过期，请刷新页面后重新登录。');
      const data=await response.json();
      if(!response.ok)throw new Error(data.error||'加载失败，请稍后重试。');
      if(current!==controller||!dialog.open)return;
      render(data);body.scrollTop=0;
    }catch(error){
      if(error.name==='AbortError'||current!==controller||!dialog.open)return;
      body.replaceChildren(node('p','form-error',error.message||'暂时无法读取附录，请重试。'));retry.hidden=false;
    }finally{if(current===controller)body.removeAttribute('aria-busy');}
  }
  document.addEventListener('click',event=>{
    const button=event.target.closest('[data-appendix-detail]');if(!button)return;
    opener=button;answerId=button.dataset.appendixDetail;dialog.showModal();document.body.classList.add('appendix-dialog-open');load();
  });
  dialog.querySelectorAll('[data-detail-close]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
  retry.addEventListener('click',load);
  dialog.addEventListener('close',()=>{controller?.abort();document.body.classList.remove('appendix-dialog-open');opener?.focus({preventScroll:true});});
})();
