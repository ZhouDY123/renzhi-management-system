const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
(async()=>{
 const repo=process.cwd(),root=fs.mkdtempSync(path.join(os.tmpdir(),'renzhi-practical-test-'));
 for(const folder of ['app','public','migrations'])fs.cpSync(path.join(repo,folder),path.join(root,folder),{recursive:true});
 const ini=path.join(repo,'php.ini');
 const fixture=mode=>execFileSync('php',['-c',ini,'tests/practical-fixture.php',root,...(mode?[mode]:[])],{encoding:'utf8'});
 fixture();
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const base=`http://127.0.0.1:${port}`;
 const server=spawn('php',['-c',ini,'-S',`127.0.0.1:${port}`,'-t',path.join(root,'public')],{windowsHide:true,stdio:['ignore','pipe','pipe']});
 let logs='';server.stderr.on('data',d=>logs+=d);server.stdout.on('data',d=>logs+=d);
 let browser;
 try {
  for(let n=0;n<40;n++){try{await fetch(base+'/practical.php');break;}catch{await new Promise(r=>setTimeout(r,100));}}
  browser=await chromium.launch({headless:true,channel:'msedge'});
  const admin=await browser.newPage({viewport:{width:1440,height:1000}});
  await admin.goto(base+'/index.php?page=login');await admin.locator('[name=username]').fill('admin');await admin.locator('[name=password]').fill('admin123');await admin.locator('.login-submit').click();await admin.waitForURL(base+'/index.php');
  await admin.goto(base+'/index.php?page=interview_practical&session_id=1');
  await admin.locator('[name="questions[]"]').fill('请制作一份客户接待流程文档，并上传成果。');
  await admin.locator('[data-add-question]').click();await admin.locator('[name="questions[]"]').nth(1).fill('请制作一份数据汇总表，并上传成果。');
  await admin.locator('[name=enabled]').check();await admin.getByRole('button',{name:'保存实操设置'}).click();await admin.waitForLoadState('networkidle');
  assert.equal(await admin.locator('[name="questions[]"]').count(),2);
  const context=await browser.newContext(),page=await context.newPage();
  const pageErrors=[];page.on('pageerror',e=>pageErrors.push(e.message));
  await page.goto(base+'/index.php?page=interview_practical&session_id=1');assert(page.url().includes('page=login'));
  async function verify(p,name,mobile){await p.goto(base+'/practical.php');await p.locator('[name=name]').fill(name);await p.locator('[name=mobile]').fill(mobile);await p.getByRole('button',{name:'验证并进入实操'}).click();await p.waitForLoadState('networkidle');}
  await verify(page,'未安排人员','13800138999');assert(await page.getByRole('alert').isVisible());
  await verify(page,'陈晓宁','13800138000');await page.waitForURL('**/practical.php?session_id=1');assert.equal(await page.locator('.practical-question').count(),2);
  let csrf=await page.locator('[name=csrf]').first().inputValue(),attempt=await page.locator('[name=attempt_id]').first().inputValue();
  const qids=await page.locator('[name=question_id]').evaluateAll(xs=>xs.map(x=>x.value));
  let response=await context.request.post(base+'/practical.php?session_id=1&action=submit',{form:{csrf,attempt_id:attempt}});assert((await response.text()).includes('请先为每一道题上传成果文件'));
  response=await context.request.post(base+'/practical.php?session_id=1&action=upload',{headers:{Accept:'application/json'},multipart:{csrf,attempt_id:attempt,question_id:'99999',result_file:{name:'wrong.txt',mimeType:'text/plain',buffer:Buffer.from('test')}}});assert.equal(response.status(),400);
  response=await context.request.post(base+'/practical.php?session_id=1&action=upload',{headers:{Accept:'application/json'},multipart:{csrf,attempt_id:attempt,question_id:qids[0],result_file:{name:'unsafe.php',mimeType:'text/plain',buffer:Buffer.from('<?php echo 1;')}}});assert.equal(response.status(),400);
  response=await context.request.post(base+'/practical.php?session_id=1&action=submit',{form:{csrf:'wrong',attempt_id:attempt}});assert.equal(response.status(),419);
  // Queue a file on the second question before uploading the first: it must survive.
  await page.locator('[name=result_file]').nth(1).setInputFiles({name:'第二题.csv',mimeType:'text/csv',buffer:Buffer.from('name,value\ntest,12')});
  await page.locator('[name=result_file]').first().setInputFiles({name:'第一题.txt',mimeType:'text/plain',buffer:Buffer.from('成果一')});
  await page.locator('[name=note]').first().fill('第一题说明');
  await page.locator('.practical-upload-form').first().getByRole('button',{name:'上传并保存本题成果'}).click();await page.locator('.practical-question').first().locator('.saved').waitFor();
  assert.equal(await page.locator('[name=result_file]').nth(1).evaluate(el=>el.files[0].name),'第二题.csv');
  await page.locator('.practical-upload-form').nth(1).getByRole('button',{name:'上传并保存本题成果'}).click();await page.locator('.practical-question').nth(1).locator('.saved').waitFor();
  response=await context.request.post(base+'/practical.php?session_id=1&action=upload',{headers:{Accept:'application/json'},multipart:{csrf,attempt_id:attempt,question_id:qids[0],note:'更新后的说明',result_file:{name:'第一题更新.txt',mimeType:'text/plain',buffer:Buffer.from('成果一（更新）')}}});assert.equal(response.status(),200);
  response=await context.request.post(base+'/practical.php?session_id=1&action=upload',{headers:{Accept:'application/json'},multipart:{csrf,attempt_id:attempt,question_id:qids[0],result_file:{name:'empty.txt',mimeType:'text/plain',buffer:Buffer.alloc(0)}}});assert.equal(response.status(),400);
  response=await context.request.get(base+`/practical.php?session_id=1&action=download&question_id=${qids[0]}`);assert.equal(await response.text(),'成果一（更新）');
  await page.reload();assert.equal(await page.locator('.practical-file-state.saved').count(),2);
  for(const width of [1440,768,375]){await page.setViewportSize({width,height:1000});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Public page overflow');}
  fs.mkdirSync('output',{recursive:true});await page.screenshot({path:'output/practical-mobile.png',fullPage:true});await page.setViewportSize({width:1280,height:1100});await page.screenshot({path:'output/practical-desktop.png',fullPage:true});
  await admin.reload();assert(await admin.locator('[name="questions[]"]').first().getAttribute('readonly')!==null);
  const adminCsrf=await admin.locator('[name=csrf]').first().inputValue();
  response=await admin.request.post(base+'/index.php?page=interview_practical&session_id=1',{form:{csrf:adminCsrf,'questions[0]':'偷偷换题',enabled:'1'}});assert((await response.text()).includes('题目已锁定'));
  await page.getByRole('button',{name:'确认提交全部成果'}).click();await page.getByRole('heading',{name:'成果已提交'}).waitFor();
  response=await context.request.post(base+'/practical.php?session_id=1&action=upload',{headers:{Accept:'application/json'},multipart:{csrf,attempt_id:attempt,question_id:qids[0],result_file:{name:'late.txt',mimeType:'text/plain',buffer:Buffer.from('late')}}});assert.equal(response.status(),400);
  const otherContext=await browser.newContext(),other=await otherContext.newPage();await verify(other,'另一应聘者','13800138001');
  response=await otherContext.request.get(base+`/practical.php?session_id=1&action=download&question_id=${qids[0]}`);assert(!(await response.text()).includes('成果一'));
  await admin.reload();await admin.locator('.practical-result-person summary').click();
  response=await admin.request.get(await admin.locator('.practical-result-question a').first().getAttribute('href').then(h=>base+'/index.php'+h));assert.equal(await response.text(),'成果一（更新）');assert(response.headers()['content-disposition'].includes('attachment'));
  await admin.evaluate(()=>{document.activeElement?.blur();window.scrollTo(0,0);});
  await admin.screenshot({path:'output/practical-admin.png',fullPage:true});
  await admin.setViewportSize({width:375,height:1000});assert(await admin.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Admin page overflow');
  fixture('second');const fresh=await browser.newPage();await verify(fresh,'陈晓宁','13800138000');assert.equal(await fresh.locator('.practical-session').count(),2);
  fixture('close');response=await context.request.get(base+'/practical.php?session_id=1');assert((await response.text()).includes('该场次未开放'));
  const state=JSON.parse(fixture('inspect'));assert.equal(state.files.length,2);assert(state.attempts.some(a=>a.submitted_at));assert.deepEqual(state.fk,[]);
  assert.equal(fs.readdirSync(path.join(root,'data','practical-files')).length,2);assert.deepEqual(pageErrors,[]);
  assert(!/Fatal error|Warning:|Uncaught/.test(logs),logs);
  console.log('PASS: admin setup, public identity, assignment isolation, two independent uploads, refresh recovery, validation, CSRF, locked questions, submission lock, private downloads, multiple sessions, cancellation, responsive layout');
  console.log('Isolated test root: '+root);
 } finally {if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1);});
