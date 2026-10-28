(() => {
 const form=document.getElementById('questionnaire-editor');if(!form)return;
 const list=form.querySelector('[data-question-list]'),status=form.querySelector('[data-editor-status]');
 const template=list.firstElementChild.cloneNode(true);let dirty=false,dragged=null;
 function update(changed=true){list.querySelectorAll('[data-question-number]').forEach((n,i)=>n.textContent=i+1);form.querySelector('[data-question-count]').textContent=list.children.length+' 道';if(changed){dirty=true;status.textContent='有未保存的修改';}}
 form.addEventListener('input',()=>update());
 form.querySelector('[data-add-question]').addEventListener('click',()=>{if(list.children.length>=60){status.textContent='最多添加 60 道题目';return;}const row=template.cloneNode(true);row.querySelector('textarea').value='';list.append(row);update();row.querySelector('textarea').focus();});
 list.addEventListener('click',event=>{const button=event.target.closest('[data-remove-question]');if(!button)return;if(list.children.length===1){status.textContent='请至少保留一道题目';return;}button.closest('article').remove();update();});
 list.addEventListener('dragstart',event=>{if(!event.target.matches('.questionnaire-drag'))return;dragged=event.target.closest('article');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain','question');dragged.classList.add('is-dragging');});
 list.addEventListener('dragover',event=>{if(!dragged)return;event.preventDefault();const row=event.target.closest('article');if(!row||row===dragged)return;const rect=row.getBoundingClientRect();list.insertBefore(dragged,event.clientY<rect.top+rect.height/2?row:row.nextSibling);update();});
 list.addEventListener('drop',event=>event.preventDefault());
 list.addEventListener('dragend',()=>{if(dragged)dragged.classList.remove('is-dragging');dragged=null;});
 list.addEventListener('keydown',event=>{if(!event.target.matches('.questionnaire-drag')||!['ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();const row=event.target.closest('article');if(event.key==='ArrowUp'&&row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);else if(event.key==='ArrowDown'&&row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);event.target.focus();update();});
 form.addEventListener('submit',()=>dirty=false);
 window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
 update(false);
})();
