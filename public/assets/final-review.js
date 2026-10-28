(() => {
 const report=document.querySelector('.final-review-tabbed');if(!report)return;
 const nav=report.querySelector('.final-report-nav');
 const panels=[...report.querySelectorAll('.final-report-content>.final-report-section')];
 const tabs=[...nav.querySelectorAll('a')].filter(a=>panels.some(p=>'#'+p.id===a.hash));
 nav.setAttribute('role','tablist');
 tabs.forEach(tab=>{tab.id='tab-'+tab.hash.slice(1);tab.setAttribute('role','tab');tab.setAttribute('aria-controls',tab.hash.slice(1));});
 panels.forEach(panel=>{panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby','tab-'+panel.id);panel.tabIndex=0;});
 function activate(hash,remember=false){
  if(!tabs.some(tab=>tab.hash===hash))hash='#final-overview';
  tabs.forEach(tab=>{const active=tab.hash===hash;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;tab.classList.toggle('active',active);});
  panels.forEach(panel=>panel.hidden='#'+panel.id!==hash);
  document.querySelectorAll('.final-review-person').forEach(link=>{link.hash=hash;});
  if(remember)history.replaceState(null,'',location.pathname+location.search+hash);
 }
 tabs.forEach((tab,i)=>{tab.addEventListener('click',event=>{event.preventDefault();activate(tab.hash,true);});tab.addEventListener('keydown',event=>{let next;if(event.key==='ArrowRight')next=(i+1)%tabs.length;else if(event.key==='ArrowLeft')next=(i+tabs.length-1)%tabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;else return;event.preventDefault();tabs[next].focus();activate(tabs[next].hash,true);});});
 activate(location.hash);
 window.addEventListener('hashchange',()=>{if(tabs.some(tab=>tab.hash===location.hash))activate(location.hash);});
 let printing=[];
 window.addEventListener('beforeprint',()=>{printing=panels.filter(p=>p.hidden);panels.forEach(p=>p.hidden=false);});
 window.addEventListener('afterprint',()=>printing.forEach(p=>p.hidden=true));
})();
