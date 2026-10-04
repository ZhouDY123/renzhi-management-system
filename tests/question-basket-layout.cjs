const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const {resolve}=require('node:path');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:800,height:1000}});
  await page.route('http://basket.test/**',route=>route.fulfill({contentType:'text/html',body:'<!doctype html><html><head></head><body></body></html>'}));
  await page.goto('http://basket.test/');
  await page.setContent(`<main style="padding:12px"><form data-question-paper-builder><label class="question-post-select"><select data-question-post-select><option value="1">测试岗位</option></select></label><div class="question-pick-head"></div><div class="question-pick-list">${['我熟练使用金蝶、用友等财务及ERP软件，完成存货核算、成本归集、费用分配与数据核对。','我熟练使用Excel/WPS函数、数据透视表及Power Query进行数据清洗、对账与分析。','我能从采购、库存、生产或销售系统提取并核对业务数据。'].map((text,i)=>`<label data-question-post="1"><input type="checkbox" checked value="${i+1}"><b>${text}</b><small>五级自评 · 10 分</small></label>`).join('')}</div></form></main>`);
  await page.addStyleTag({path:resolve('public/assets/app.css')});
  await page.addStyleTag({path:resolve('public/assets/extra.css')});
  await page.addScriptTag({path:resolve('public/assets/app.js')});
  await page.evaluate(()=>initQuestionPaperBuilder());
  const basket=page.locator('.question-selection-basket');
  assert.equal(await basket.locator('li').count(),3);
  for(const width of [800,560,375]){
   await page.setViewportSize({width,height:1000});
   assert(await basket.evaluate(el=>el.scrollWidth<=el.clientWidth+1));
   assert(await basket.locator('li').evaluateAll(rows=>rows.every(row=>{const b=row.querySelector('b'),button=row.querySelector('button');return getComputedStyle(b).whiteSpace==='normal'&&b.scrollWidth<=b.clientWidth+1&&button.scrollWidth<=button.clientWidth+1&&b.getBoundingClientRect().right<button.getBoundingClientRect().left;})));
  }
  await page.setViewportSize({width:560,height:1000});
  if(process.argv[2])await basket.screenshot({path:process.argv[2]});
  await basket.locator('button').first().click();
  assert.equal(await basket.locator('li').count(),2);
  assert(!(await page.locator('[data-question-post] input').first().isChecked()));
  console.log('PASS: long stems wrap, remove buttons stay on one line, responsive basket and removal synchronization');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
