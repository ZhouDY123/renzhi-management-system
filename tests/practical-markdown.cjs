const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
(async()=>{
 const repo=process.cwd(),root=fs.mkdtempSync(path.join(os.tmpdir(),'renzhi-practical-test-'));
 for(const folder of ['app','public','migrations'])fs.cpSync(path.join(repo,folder),path.join(root,folder),{recursive:true});
 const ini=path.join(repo,'php.ini');
 const fixture=mode=>execFileSync('php',['-c',ini,'tests/practical-fixture.php',root,...(mode?[mode]:[])],{encoding:'utf8'});
 fixture();process.stdout.write(fixture('limits'));
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
  await admin.locator('.sidebar a[href*=practical_questions]').click();
  assert.equal(await admin.locator('.sidebar a[href*=practical_questions]').count(),1);
  const search=admin.getByRole('combobox',{name:'搜索并选择岗位'});
  await search.fill((await admin.locator('select[name=post_id] option[value="1"]').textContent()).split(' · ')[0]);await admin.locator('.searchable-post-option').first().click();
  await admin.getByRole('button',{name:'查看岗位题目'}).click();await admin.waitForLoadState('networkidle');
  assert(admin.url().includes('post_id=1'));

  const errors=[];admin.on('pageerror',e=>errors.push(e.message));
  const editor=admin.locator('#practical-question-dialog');
  const csrf=await admin.locator('[name=csrf]').first().inputValue();
  const source='## 创作任务\n\n**核心要求**\n\n- 第一项\n- 第二项\n\n| 项目 | 要求 |\n| --- | --- |\n| 时间 | 30分钟 |';
  await admin.request.post(admin.url(),{form:{csrf,operation:'create',stem:source}});await admin.reload();
  await admin.locator('[data-practical-edit]').click();
  const rich=editor.locator('.toastui-editor-ww-container [contenteditable=true]');
  await rich.waitFor();assert(await rich.isVisible());assert(!(await editor.locator('[name=stem]').isVisible()));
  assert.equal(await rich.locator('h2').textContent(),'创作任务');assert.equal(await rich.locator('table').count(),1);
  await rich.press('Control+End');await rich.press('Enter');
  await rich.evaluate(async el=>{
    const c=document.createElement('canvas');c.width=480;c.height=180;
    const x=c.getContext('2d');x.fillStyle='#2855c7';x.fillRect(0,0,480,180);x.fillStyle='white';x.font='28px sans-serif';x.fillText('IMAGE PASTE TEST',100,100);
    const blob=await new Promise(r=>c.toBlob(r,'image/png'));
    const d=new DataTransfer();d.items.add(new File([blob],'clipboard.png',{type:'image/png'}));
    el.dispatchEvent(new ClipboardEvent('paste',{clipboardData:d,bubbles:true,cancelable:true}));
  });
  await admin.waitForFunction(()=>document.querySelector('#practical-image-status').textContent.includes('图片已插入'));
  await rich.locator('img[src]').waitFor();await admin.waitForFunction(()=>document.querySelector('.toastui-editor-ww-container img')?.naturalWidth===480);
  assert(!(await rich.innerText()).includes('![题目图片]'));
  await rich.press('Control+End');await rich.press('Enter');await rich.pressSequentially('图片后面可以继续输入文字');

  const img=rich.locator('img[src]');
  await img.click();
  const handle=editor.locator('[data-resize=se]');
  const box=await handle.boundingBox();
  await admin.mouse.move(box.x+box.width/2,box.y+box.height/2);await admin.mouse.down();await admin.mouse.move(box.x-112,box.y-38,{steps:8});await admin.mouse.up();
  await admin.waitForFunction(()=>document.querySelector('.toastui-editor-ww-container img[src]')?.getAttribute('src').includes('#practical-size='));
  await admin.waitForFunction(()=>document.querySelector('.toastui-editor-ww-container img[src]').getBoundingClientRect().width<480);
  let resized=await img.boundingBox();assert(resized.width<480&&resized.width>250);assert(Math.abs(resized.width/resized.height-480/180)<.06);
  await img.click();const side=await editor.locator('[data-resize=s]').boundingBox();
  await admin.mouse.move(side.x+7,side.y+7);await admin.mouse.down();await admin.mouse.move(side.x+7,side.y+47,{steps:5});await admin.mouse.up();
  const storedSize=(await img.getAttribute('src')).match(/#practical-size=(\d+)x(\d+)/).slice(1).map(Number);
  await admin.waitForFunction(s=>Math.abs(document.querySelector('.toastui-editor-ww-container img[src]').getBoundingClientRect().height-s[1])<2,storedSize);
  resized=await img.boundingBox();assert(Math.abs(resized.width-storedSize[0])<2);assert(Math.abs(resized.height-storedSize[1])<2);
  fs.mkdirSync('output',{recursive:true});await admin.screenshot({path:'output/practical-visual-editor.png'});
  for(const width of [1440,390]){await admin.setViewportSize({width,height:850});assert(await editor.evaluate(e=>e.scrollWidth<=e.clientWidth+1));}
  await editor.getByRole('button',{name:'保存题目'}).click();await admin.waitForLoadState('networkidle');
  await admin.locator('.practical-bank-text img').waitFor();
  assert((await admin.locator('.practical-bank-text').innerText()).includes('图片后面可以继续输入文字'));
  const imageUrl=await admin.locator('.practical-bank-text img').getAttribute('src');
  await admin.locator('[data-practical-edit]').click();await rich.locator('img[src]').waitFor();
  assert((await rich.innerText()).includes('图片后面可以继续输入文字'));
  assert((await rich.locator('img[src]').getAttribute('src')).endsWith('#practical-size='+storedSize.join('x')));
  await editor.getByRole('button',{name:'取消',exact:true}).click();assert(!(await editor.isVisible()),'Unchanged editor should close without discard prompt');
  const context=await browser.newContext();assert.equal((await context.request.get(base+imageUrl)).status(),404);
  const page=await context.newPage();await page.goto(base+'/practical.php');await page.locator('[name=name]').fill('陈晓宁');await page.locator('[name=mobile]').fill('13800138000');await page.getByRole('button',{name:'验证并进入实操'}).click();
  await page.locator('.practical-question-stem img').waitFor();assert.equal((await context.request.get(base+imageUrl)).status(),200);
  const shown=await page.locator('.practical-question-stem img').boundingBox();assert(Math.abs(shown.width-storedSize[0])<2&&Math.abs(shown.height-storedSize[1])<2);
  await page.setViewportSize({width:390,height:850});const mobile=await page.locator('.practical-question-stem img').boundingBox();assert(mobile.width<=350);assert(Math.abs(mobile.width/mobile.height-storedSize[0]/storedSize[1])<.05);
  await admin.goto(base+'/index.php?page=practical_results&candidate_id=1');await admin.locator('.practical-result-stem img').waitFor();
  assert.deepEqual(errors,[]);assert(!/Fatal error|Warning:|Uncaught/.test(logs),logs);
  console.log('PASS: inline image paste, proportional drag, independent height drag, persisted dimensions, re-edit, responsive candidate/results display and image authorization');
 } finally {if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1);});

