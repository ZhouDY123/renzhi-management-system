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
  const ids=fixture('seed');
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
  }
  assert.deepEqual(fixture('inspect').fk,[]);
  console.log('PASS: post validation, filtering, default selection, publishing counts, isolated papers, immutable snapshots and candidate questions');
 }finally{
  if(browser)await browser.close();
  if(server&&server.exitCode===null){server.kill();await once(server,'exit');}
  if(dirname(root)===tmpdir()&&root.includes('recruit-basic-post-test-'))rmSync(root,{recursive:true,force:true});
 }
})().catch(e=>{console.error(e);process.exit(1)});
