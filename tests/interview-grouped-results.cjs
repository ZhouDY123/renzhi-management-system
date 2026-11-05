const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const {execFileSync}=require('node:child_process');
(async()=>{
 const markup=execFileSync('php',['tests/interview-grouped-results.php','--html'],{encoding:'utf8'});
 const css=['app','extra'].map(n=>fs.readFileSync(`public/assets/${n}.css`,'utf8')).join('\n');
 const js=fs.readFileSync('public/assets/app.js','utf8');
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try {
  const page=await browser.newPage({viewport:{width:1440,height:1100}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://interview.test/**',route=>route.request().url().includes('action=review_states')
   ?route.fulfill({contentType:'application/json',body:JSON.stringify([{id:11,review_status:'final_pass'}])})
   :route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="zh-CN"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body><main style="padding:24px"><div class="page-head"><h1>面试结果</h1></div>${markup}</main><script>${js}</script></body></html>`}));
  await page.goto('http://interview.test/index.php?page=interview_results');
  const group=id=>page.locator(`[data-interview-group="${id}"]`);
  await page.locator('.table-action.is-disabled').waitFor();
  assert.equal(await page.locator('[data-interview-group]:visible').count(),10);
  assert.equal(await group(1).locator('.recommendation-tag.is-reject').count(),1);
  assert.equal(await group(1).locator('.recommendation-tag.is-pending').count(),1);
  assert.equal(await group(1).locator('a[data-review-jump]').getAttribute('href'),'/index.php?page=talent&tab=final&score_id=12');
  assert.equal(await group(1).locator('[name=score_id]').evaluateAll(nodes=>nodes.map(n=>n.value).join(',')),'11,12');
  await page.locator('[data-next]').click();
  assert.equal(await page.locator('[data-interview-group]:visible').count(),2);
  assert.equal(await group(11).locator('tr:visible').count(),3);
  assert.equal(await group(12).locator('form').count(),0);
  assert.equal(await group(12).locator('.interview-score-value').innerText(),'—');
  const questionnaire=new URL(await group(12).locator('a').getAttribute('href'));
  assert.equal(questionnaire.searchParams.get('candidate_id'),'12');
  assert.equal(questionnaire.searchParams.get('standalone'),'1');
  assert(await page.locator('[data-next]').isDisabled());
  await page.locator('[data-prev]').click();
  for(const width of [1440,1024,768,375]) {
   await page.setViewportSize({width,height:1100});
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Viewport overflow');
   assert(await page.locator('td:visible,th:visible').evaluateAll(cells=>cells.every(c=>c.scrollWidth<=c.clientWidth+2)),'Cell overflow');
  }
  await page.setViewportSize({width:1440,height:1100});
  if(process.env.TEST_SCREENSHOT)await page.screenshot({path:process.env.TEST_SCREENSHOT,fullPage:true});
  await group(1).locator('.danger-text').first().click();
  assert(await page.locator('.confirm-overlay').count());
  assert.deepEqual(errors,[]);
  console.log('PASS: group pagination, recommendations, review/reset IDs, questionnaire-only, 4 widths, reset confirmation');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
