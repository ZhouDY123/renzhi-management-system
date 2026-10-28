(() => {
 const form=document.getElementById('questionnaire-editor');if(!form)return;
 const list=form.querySelector('[data-question-list]'),status=form.querySelector('[data-editor-status]');
 const template=list.firstElementChild.cloneNode(true);let dirty=false,dragged=null;
 function setOpen(row,open){row.querySelector('.questionnaire-inline-editor').hidden=!open;row.classList.toggle('is-editing',open);const toggle=row.querySelector('[data-edit-question]');toggle.textContent=open?'收起':'编辑';toggle.setAttribute('aria-expanded',String(open));row.querySelector('details').open=false;}
 function openRow(row,focus=true){[...list.children].forEach(item=>setOpen(item,item===row));if(focus)row.querySelector('textarea').focus();}
 function update(changed=true){list.querySelectorAll('[data-question-number]').forEach((n,i)=>n.textContent=String(i+1).padStart(2,'0'));list.querySelectorAll('article').forEach(row=>row.querySelector('[data-question-stem]').textContent=row.querySelector('textarea').value.trim()||'请填写题目内容');form.querySelector('[data-question-count]').textContent=list.children.length+' 道题';if(changed){dirty=true;status.textContent='有未保存的修改';}}
 form.addEventListener('input',()=>update());
 document.querySelector('[data-add-question]').addEventListener('click',()=>{if(list.children.length>=60){status.textContent='最多添加 60 道题目';return;}const row=template.cloneNode(true);row.querySelector('textarea').value='';list.append(row);update();openRow(row);});
 list.addEventListener('click',event=>{const toggle=event.target.closest('[data-edit-question],[data-finish-question]');if(toggle){const row=toggle.closest('article');if(row.classList.contains('is-editing')){setOpen(row,false);row.querySelector('[data-edit-question]').focus();}else openRow(row);return;}const button=event.target.closest('[data-remove-question]');if(!button)return;if(list.children.length===1){status.textContent='请至少保留一道题目';button.closest('details').open=false;return;}const row=button.closest('article'),next=row.nextElementSibling||row.previousElementSibling;row.remove();update();next.querySelector('[data-edit-question]').focus();});
 form.addEventListener('invalid',event=>{const row=event.target.closest('article');if(row)openRow(row,false);},true);
 list.addEventListener('dragstart',event=>{if(!event.target.matches('.questionnaire-drag'))return;dragged=event.target.closest('article');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain','question');dragged.classList.add('is-dragging');});
 list.addEventListener('dragover',event=>{if(!dragged)return;event.preventDefault();const row=event.target.closest('article');if(!row||row===dragged)return;const rect=row.getBoundingClientRect();list.insertBefore(dragged,event.clientY<rect.top+rect.height/2?row:row.nextSibling);update();});
 list.addEventListener('drop',event=>event.preventDefault());
 list.addEventListener('dragend',()=>{if(dragged)dragged.classList.remove('is-dragging');dragged=null;});
 list.addEventListener('keydown',event=>{if(!event.target.matches('.questionnaire-drag')||!['ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();const row=event.target.closest('article');if(event.key==='ArrowUp'&&row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);else if(event.key==='ArrowDown'&&row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);event.target.focus();update();});
 form.addEventListener('submit',()=>dirty=false);
 window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
 [...list.children].forEach(row=>setOpen(row,false));
 const empty=[...list.children].find(row=>!row.querySelector('textarea').value.trim());if(empty)openRow(empty,false);
 update(false);
})();
