(() => {
 const form=document.getElementById('questionnaire-editor');if(!form)return;
 const list=form.querySelector('[data-question-list]'),status=form.querySelector('[data-editor-status]');
 const dialog=document.getElementById('questionnaire-dialog'),editor=document.getElementById('questionnaire-dialog-form');
 const input=document.getElementById('questionnaire-content'),error=dialog.querySelector('[data-dialog-error]');
 const add=document.querySelector('[data-add-question]'),template=list.firstElementChild.cloneNode(true);
 let editing=null,busy=false,dragged=null,originalOrder=null;
 const values=()=>[...list.children].map(row=>row.querySelector('input[name="questions[]"]').value);
 function update(){[...list.children].forEach((row,i)=>{row.querySelector('[data-question-number]').textContent=String(i+1).padStart(2,'0');row.querySelector('[data-question-stem]').textContent=row.querySelector('input').value;});form.querySelector('[data-question-count]').textContent=list.children.length+' 道题';}
 function setBusy(value){busy=value;form.setAttribute('aria-busy',String(value));[...form.querySelectorAll('button'),...editor.querySelectorAll('button'),add,input].forEach(el=>el.disabled=value);list.querySelectorAll('[draggable]').forEach(el=>el.draggable=!value);}
 async function save(questions){
  setBusy(true);status.textContent='正在保存…';
  try{
   const body=new FormData(form);body.delete('questions[]');questions.forEach(q=>body.append('questions[]',q));body.set('response_format','json');
   const response=await fetch(form.action||location.href,{method:'POST',body,headers:{Accept:'application/json'}});
   if(!response.headers.get('content-type')?.includes('application/json'))throw new Error('保存未确认，请刷新页面检查登录状态和最新题目。');
   const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'保存失败，请刷新页面后重试。');
   form.elements.namedItem('version').value=result.version;status.textContent='已保存';return true;
  }catch(e){status.textContent=e.message;error.textContent=e.message;error.hidden=false;return false;}
  finally{setBusy(false);}
 }
 function open(row=null){if(busy)return;editing=row;input.setCustomValidity('');input.value=row?row.querySelector('input').value:'';error.hidden=true;document.getElementById('questionnaire-dialog-title').textContent=row?'编辑题目':'新增题目';dialog.showModal();input.focus();}
 add.addEventListener('click',()=>{if(list.children.length>=60){status.textContent='最多添加 60 道题目';return;}open();});
 dialog.querySelectorAll('[data-close-question]').forEach(button=>button.addEventListener('click',()=>{if(!busy)dialog.close();}));
 dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
 editor.addEventListener('submit',async event=>{
  event.preventDefault();event.stopImmediatePropagation();if(busy)return;
  const text=input.value.trim();if(!text){input.setCustomValidity('请填写题目内容。');input.reportValidity();return;}
  error.hidden=true;const questions=values(),index=editing?[...list.children].indexOf(editing):questions.length;questions[index]=text;
  if(await save(questions)){const row=editing||template.cloneNode(true);row.querySelector('input').value=text;if(!editing)list.append(row);update();dialog.close();row.querySelector('[data-edit-question]').focus();}
 });
 input.addEventListener('input',()=>input.setCustomValidity(''));
 list.addEventListener('click',async event=>{
  if(busy)return;const edit=event.target.closest('[data-edit-question]');if(edit){open(edit.closest('article'));return;}
  const remove=event.target.closest('[data-remove-question]');if(!remove)return;
  if(list.children.length===1){status.textContent='请至少保留一道题目';return;}
  const row=remove.closest('article');
  showConfirm({title:'确认删除这道题目？',description:'删除后将自动保存，新打开的问卷不再显示此题。已提交记录保留原内容。',actionLabel:'确认删除',onConfirm:async()=>{
   if(busy||!row.isConnected)return;
   if(list.children.length===1){status.textContent='请至少保留一道题目';return;}
   const questions=values(),index=[...list.children].indexOf(row);questions.splice(index,1);
   if(await save(questions)){const next=row.nextElementSibling||row.previousElementSibling;row.remove();update();next.querySelector('[data-edit-question]').focus();}
  }});
 });
 async function saveOrder(before){if(before.every((row,i)=>list.children[i]===row))return;if(!await save(values()))list.append(...before);update();}
 list.addEventListener('dragstart',event=>{if(busy||!event.target.matches('.questionnaire-drag')){event.preventDefault();return;}originalOrder=[...list.children];dragged=event.target.closest('article');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain','question');dragged.classList.add('is-dragging');});
 list.addEventListener('dragover',event=>{if(!dragged)return;event.preventDefault();const row=event.target.closest('article');if(!row||row===dragged)return;const rect=row.getBoundingClientRect();list.insertBefore(dragged,event.clientY<rect.top+rect.height/2?row:row.nextSibling);update();});
 list.addEventListener('drop',event=>event.preventDefault());
 list.addEventListener('dragend',()=>{if(!dragged)return;dragged.classList.remove('is-dragging');dragged=null;saveOrder(originalOrder);});
 list.addEventListener('keydown',event=>{if(busy||!event.target.matches('.questionnaire-drag')||!['ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();const before=[...list.children],row=event.target.closest('article');if(event.key==='ArrowUp'&&row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);else if(event.key==='ArrowDown'&&row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);event.target.focus();update();saveOrder(before);});
 form.addEventListener('submit',event=>event.preventDefault());
 window.addEventListener('beforeunload',event=>{if(busy){event.preventDefault();event.returnValue='';}});
 update();
})();
