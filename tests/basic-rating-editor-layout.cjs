const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try{
  const page=await browser.newPage({viewport:{width:1366,height:1100}});
  await page.goto('http://127.0.0.1:8080/index.php?page=login');
  if(await page.locator('input[name=username]').count()){
   await page.locator('input[name=username]').fill('admin');await page.locator('input[name=password]').fill('admin123');
   await page.locator('.login-submit').click();await page.waitForLoadState('networkidle');
  }
  await page.goto('http://127.0.0.1:8080/index.php?page=standards&section=suzhi');await page.waitForLoadState('networkidle');
  const form=page.locator('.basic-rating-editor');
  const library=page.locator('.basic-rating-library');
  assert(await library.isVisible());
  for(const width of [1366,768,375]){
   await page.setViewportSize({width,height:1100});
   assert(await library.evaluate(el=>el.scrollWidth<=el.clientWidth+2),'Library must not overflow horizontally');
  }
  await page.setViewportSize({width:1366,height:1100});
  const row=library.locator('tbody tr').first();
  if(await row.locator('.rating-row-actions').count()){
   const buttons=await row.locator('.rating-row-actions .btn').evaluateAll(items=>items.map(e=>e.getBoundingClientRect().top));
   assert(buttons.every(y=>Math.abs(y-buttons[0])<2),'Desktop row actions must align');
  }
  assert(!await form.isVisible());
  const trigger=page.locator('[data-basic-rating-open]');
  assert(await trigger.isVisible());
  assert.equal(await page.getByRole('button',{name:'新增基本素质维度'}).count(),0);
  await page.locator('[data-standard-show=conditions]').click();assert(!await trigger.isVisible());
  assert(await page.locator('.standard-create-slot .modal-trigger').isVisible());
  await page.locator('[data-standard-show=suzhi]').click();assert(await trigger.isVisible());
  assert(!await page.locator('.standard-create-slot .modal-trigger').isVisible());
  await trigger.click();await form.waitFor({state:'visible'});
  await page.keyboard.press('Escape');await form.waitFor({state:'hidden'});
  assert(await trigger.evaluate(e=>e===document.activeElement));
  await trigger.click();await form.waitFor({state:'visible'});
  assert.equal(await form.locator('.rating-level-preview li').count(),5);
  assert(await form.locator('.rating-editor-head').evaluate(el=>el.getBoundingClientRect().height<64),'Modal introduction must stay compact');
  const toggle=form.locator('[name=enabled]');assert(await toggle.isChecked());await form.locator('.rating-switch').click();assert(!await toggle.isChecked());await form.locator('.rating-switch').click();
  for(const width of [1366,768,375]){
   await page.setViewportSize({width,height:1100});
   const bounds=await form.evaluate(f=>{const r=f.getBoundingClientRect();return [...f.querySelectorAll('textarea,input[type=number],footer')].every(e=>{const x=e.getBoundingClientRect();return x.left>=r.left&&x.right<=r.right+1;});});assert(bounds);
  }
  await page.setViewportSize({width:1200,height:1100});
  if(process.argv[2])await form.screenshot({path:process.argv[2]});
  await form.locator('[name=stem]').fill('未保存的测试题');
  await form.locator('[data-basic-rating-cancel]').click();
  await page.getByRole('button',{name:'放弃修改',exact:true}).click();await form.waitFor({state:'hidden'});
  await trigger.click();assert.equal(await form.locator('[name=stem]').inputValue(),'');
  console.log('PASS: tab-specific triggers, modal open/close, discard, responsive editor, five levels and enabled switch; no records submitted');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
