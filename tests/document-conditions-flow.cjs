const {mkdtempSync,cpSync,mkdirSync,rmSync}=require('node:fs');
const {tmpdir}=require('node:os');
const {join,resolve,dirname}=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const {once}=require('node:events');
const assert=require('node:assert/strict');
const {chromium}=require('playwright');
(async()=>{
 const root=mkdtempSync(join(tmpdir(),'recruit-basic-post-test-'));
 const php=process.argv[2]||'php',ext=process.argv[3];
 const args=ext?['-d',`extension_dir=${ext}`,'-d','extension=pdo_sqlite']:[];
 let server,browser;
 try{
  for(const dir of ['app','migrations','public'])cpSync(resolve(dir),join(root,dir),{recursive:true});
  mkdirSync(join(root,'data'));
  const fixture=mode=>JSON.parse(execFileSync(php,[...args,resolve('tests/basic-post-fixture.php'),root,mode],{encoding:'utf8'}));
  const ids=fixture('seed-conditions');
  server=spawn(php,[...args,'-S','127.0.0.1:18879','-t',join(root,'public')],{stdio:'ignore'});
  const base='http://127.0.0.1:18879';
  for(let i=0;i<40;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
  browser=await chromium.launch({channel:'msedge',headless:true});
  const page=await browser.newPage();
  await page.goto(base+'/index.php?page=login');
  await page.locator('[name=username]').fill('admin');await page.locator('[name=password]').fill('admin123');
  await page.locator('.login-submit').click();await page.waitForLoadState('networkidle');
  await page.goto(base+'/index.php?page=standards&section=suzhi');
  const csrf=await page.locator('.basic-rating-editor [name=csrf]').inputValue();
  await page.locator('[data-standard-show=conditions]').click();
  await page.locator('.condition-order summary').click();
  const before=await page.locator('.condition-order li').evaluateAll(items=>items.map(e=>e.dataset.code));
  await page.locator('.condition-order li').nth(1).locator('[data-up]').click();
  await page.locator('.condition-order footer button').click();
  await page.locator('.condition-order [role=status]').filter({hasText:'已保存'}).waitFor();
  const expectedOrder=[before[1],before[0],...before.slice(2)];
  assert.deepEqual(await page.locator('#standard-conditions .dimension-score-card [name=dim_code]').evaluateAll(items=>items.map(e=>e.value)),expectedOrder);
  await page.reload();
  assert.deepEqual(await page.locator('#standard-conditions .dimension-score-card [name=dim_code]').evaluateAll(items=>items.map(e=>e.value)),expectedOrder);
  const send=async(action,data)=>{const r=await page.request.post(base+'/index.php?page=standards&action='+action,{form:{csrf,...data}});const text=await r.text();assert(!/Fatal error|Parse error|Warning:/.test(text));return text;};
  const input=(post,stem)=>({post_id:String(post),stem,score:'3',sort:'1',enabled:'on'});
  for(let i=0;i<2;i++)await send('basic_rating_save',input(ids.posts[i],'测试岗位题'+i));
  await send('basic_rating_save',input(999999,'测试岗位无效'));
  await send('basic_rating_save',input('', '测试岗位缺失'));
  let state=fixture('inspect');assert.equal(state.questions.length,2,'Invalid posts must not save');
  for(let i=0;i<2;i++){
   await page.goto(base+'/index.php?page=standards&section=suzhi&basic_post='+ids.posts[i]);
   const library=page.locator('.basic-rating-library');
   assert((await library.textContent()).includes('测试岗位题'+i));assert(!(await library.textContent()).includes('测试岗位题'+(1-i)));
   await page.locator('[data-basic-rating-open]').click();assert.equal(await page.locator('.basic-rating-editor [name=post_id]').inputValue(),String(ids.posts[i]));await page.keyboard.press('Escape');
   await page.goto(base+'/index.php?page=questions');
   await page.locator('[data-question-post-select]').selectOption(String(ids.posts[i]));
   assert.equal(await page.locator('[data-basic-post-counts] b').textContent(),'1');
   await send('question_publish_v2',{post_id:String(ids.posts[i]),'professional_ids[]':String(ids.professional[i])});
  }
  await send('question_publish_v2',{post_id:String(ids.posts[2]),'professional_ids[]':String(ids.professional[2])});
  state=fixture('inspect');assert.equal(state.papers.length,2,'Empty post must not publish');
  state.papers.forEach((p,i)=>{assert.equal(p.post_id,ids.posts[i]);assert.equal(p.stem_snapshot,'测试岗位题'+i);});
  await send('basic_rating_save',{...input(ids.posts[1],'测试岗位已改题'),id:String(state.questions[0].id)});
  assert.equal(fixture('inspect').papers[0].stem_snapshot,'测试岗位题0','Published snapshots must not change');
  for(let i=0;i<2;i++){
   const token='post-test-'+['甲','乙'][i];
   await page.goto(base+'/h5.php?m=apply&t='+encodeURIComponent(token));
   const response=await page.request.post(base+'/h5.php?m=apply&t='+encodeURIComponent(token)+'&step=verify',{form:{csrf:await page.locator('[name=csrf]').first().inputValue(),name:'隔离测试'+i,mobile:'1390000080'+i,'profile[gender]':'男','profile[age]':'26','profile[edu]':'本科','profile[major]':'测试','profile[school_name]':'测试大学'}});
   const html=await response.text();assert(html.includes('测试岗位题'+i));assert(!html.includes('测试岗位题'+(1-i)));
   assert(!html.includes('name="politics"'));
   assert.equal((html.match(/name="conditions\[/g)||[]).length,12);
   assert.deepEqual([...html.matchAll(/name="conditions\[([^\]]+)\]"/g)].map(m=>m[1]),expectedOrder);
   const value=name=>html.match(new RegExp('name="'+name+'"[^>]*value="([^"]*)"'))[1];
   const form={csrf:value('csrf'),idempotency_key:value('idempotency_key')};
   for(const [code,label] of Object.entries(ids.conditions))form['conditions['+code+']']=label;
   for(const match of html.matchAll(/name="(answers\[(?:base|post)_\d+\])"/g))form[match[1]]='完全符合';
   const submitted=await page.request.post(base+'/h5.php?m=apply&t='+encodeURIComponent(token)+'&step=submit',{form});
   const appendixHtml=await submitted.text();
   assert(appendixHtml.includes('补充信息'),'Assessment must lead to the separate appendix page');
   assert(appendixHtml.includes('不影响测评结果'),'Appendix is optional and unscored');
   await page.goto(submitted.url());
   assert.equal(await page.locator('.appendix-item').count(),8);
   assert.equal(await page.locator('.appendix-form [required]').count(),0);
   assert.equal(await page.locator('.appendix-form input[type=radio]:checked').count(),0);
   await page.setViewportSize({width:390,height:844});
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow');
   const brand=await page.locator('.h5-brand').boundingBox(),intro=await page.locator('.appendix-intro').boundingBox();
   assert(intro.y>=brand.y+brand.height+12,'No header overlap');
   // One blank submission, one skip with malformed data; neither changes the 100-point result.
   const appendixCsrf=await page.locator('.appendix-form [name=csrf]').inputValue();
   const appendixAnswer=await page.locator('[name=appendix_answer_id]').inputValue();
   if(process.env.APPENDIX_SCREENSHOTS){mkdirSync(process.env.APPENDIX_SCREENSHOTS,{recursive:true});await page.screenshot({path:join(process.env.APPENDIX_SCREENSHOTS,'appendix-mobile.png'),fullPage:true});}
   assert.equal((await page.request.post(submitted.url(),{form:{csrf:'invalid',appendix_answer_id:appendixAnswer}})).status(),419,'CSRF enforced');
   assert.equal((await page.request.post(submitted.url(),{form:{csrf:appendixCsrf,appendix_answer_id:'999999'}})).status(),409,'Stale/foreign attempts rejected');
   const stranger=await browser.newContext();
   assert.equal((await stranger.request.get(submitted.url())).status(),403,'Another browser cannot access the appendix');await stranger.close();
   const done=await page.request.post(submitted.url(),{form:{csrf:appendixCsrf,appendix_answer_id:appendixAnswer,intent:i?'skip':'submit',...(i?{appendix:'invalid'}:{})}});
   assert((await done.text()).includes('100.0 / 100'),'All highest tiers must yield 100 overall after appendix');
   const reopened=await page.request.get(submitted.url());
   assert(!((await reopened.text()).includes('class="h5-card appendix-intro"')),'Completed appendix must not reopen');
  }
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/index.php?page=appendix');
  assert.equal(await page.locator('.appendix-library-row').count(),8);
  const nav=await page.locator('.sidebar nav a').evaluateAll(es=>es.map(e=>e.getAttribute('href')));
  assert.equal(nav.indexOf('/index.php?page=appendix'),nav.indexOf('/index.php?page=standards')+1,'Appendix navigation follows basic questions');
  if(process.env.APPENDIX_SCREENSHOTS)await page.screenshot({path:join(process.env.APPENDIX_SCREENSHOTS,'appendix-admin.png'),fullPage:true});
  await page.locator('[data-appendix-create]').click();
  assert(await page.locator('.appendix-modal').isVisible());
  assert(!(await page.locator('[data-appendix-options]').isVisible()));
  assert(await page.locator('#appendix-editor [name=options]').isDisabled());
  await page.locator('#appendix-editor [name=title]').fill('自动测试附录');
  await page.locator('#appendix-editor [name=type]').selectOption('select');
  await page.locator('#appendix-editor [name=options]').fill('是\n否\n不提供');
  assert(await page.locator('[data-appendix-options]').isVisible());
  await page.locator('#appendix-editor button[type=submit]').click();await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('.appendix-library-row').count(),9);
  const created=page.locator('.appendix-library-row').filter({hasText:'自动测试附录'});
  await created.getByRole('button',{name:'编辑'}).click();
  assert(await page.locator('#appendix-editor [name=enabled]').isChecked());
  await page.locator('#appendix-editor [name=title]').fill('自动测试附录修改');
  await page.locator('#appendix-editor button[type=submit]').click();await page.waitForLoadState('networkidle');
  const changed=page.locator('.appendix-library-row').filter({hasText:'自动测试附录修改'});
  assert((await changed.textContent()).includes('已启用'));
  const extraId=await changed.locator('[name=id]').first().inputValue();
  const adminCsrf=await page.locator('#appendix-editor [name=csrf]').inputValue();
  assert.equal((await page.request.post(base+'/index.php?page=appendix&action=appendix_toggle',{form:{csrf:'invalid',id:extraId}})).status(),419);
  await page.request.post(base+'/index.php?page=appendix&action=appendix_toggle',{form:{csrf:adminCsrf,id:extraId}});
  await page.reload();assert((await changed.textContent()).includes('已停用'));
  await page.request.post(base+'/index.php?page=appendix&action=appendix_delete',{form:{csrf:adminCsrf,id:extraId}});
  await page.reload();assert.equal(await page.locator('.appendix-library-row').count(),8);
  await page.goto(base+'/index.php?page=appendix&view=responses');assert.equal(await page.locator('.appendix-records details').count(),2);
  assert(!/Fatal error|Warning:/.test(await page.content()));
  for(const role of ['hr','lisi']){
   const context=await browser.newContext();const userPage=await context.newPage();
   await userPage.goto(base+'/index.php?page=login');await userPage.locator('[name=username]').fill(role);await userPage.locator('[name=password]').fill('admin123');await userPage.locator('.login-submit').click();await userPage.waitForURL(url=>!url.search.includes('page=login'),{waitUntil:'domcontentloaded'});
   assert.equal((await userPage.goto(base+'/index.php?page=appendix')).status(),role==='hr'?200:403);
   assert.equal((await userPage.goto(base+'/index.php?page=appendix&view=responses')).status(),role==='hr'?200:403);
   await context.close();
  }
  assert.deepEqual(fixture('inspect').fk,[]);
  console.log('PASS: assessment full flow; appendix blank/skip, mobile spacing, CSRF, ownership, replay; admin CRUD, enabled state, navigation, restricted records; immutable questions and 100-point scores');
 }finally{
  if(browser)await browser.close();
  if(server&&server.exitCode===null){server.kill();await once(server,'exit');}
  if(dirname(root)===tmpdir()&&root.includes('recruit-basic-post-test-'))rmSync(root,{recursive:true,force:true});
 }
})().catch(e=>{console.error(e);process.exit(1)});
