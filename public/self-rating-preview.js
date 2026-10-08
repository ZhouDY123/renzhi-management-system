const names=['完全不符合','较不符合','基本符合','比较符合','完全符合'];
document.querySelectorAll('.card').forEach(card=>{
  const output=card.querySelector('.answer');
  const update=value=>{
    output.replaceChildren();output.classList.add('selected');
    const prefix=document.createElement('span');prefix.textContent='当前选择：';
    const text=document.createElement('strong');text.textContent=names[value-1];
    output.append(prefix,text);
    card.querySelectorAll('.stars .choice').forEach((choice,index)=>choice.classList.toggle('lit',index<value));
    const slider=card.querySelector('.slider');if(slider)slider.setAttribute('aria-valuetext',names[value-1]);
  };
  card.querySelectorAll('input').forEach(input=>input.addEventListener('input',()=>update(Number(input.value))));
  const checked=card.querySelector('input:checked');if(checked)update(Number(checked.value));
  const slider=card.querySelector('.slider');if(slider&&card.dataset.example==='1')update(Number(slider.value));
});