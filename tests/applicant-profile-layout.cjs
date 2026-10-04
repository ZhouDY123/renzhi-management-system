const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.TEST_BROWSER_CHANNEL||'msedge'});
 try{
  const page=await browser.newPage();
  for(const width of [320,375,480]){
   await page.setViewportSize({width,height:1000});
   await page.goto(process.argv[2]);await page.waitForLoadState('networkidle');
   for(const key of ['edu','major']){
    const field=page.locator(`input[name="profile[${key}]"]`);
    assert.equal(await field.count(),1);await field.fill('自填测试内容');assert.equal(await field.inputValue(),'自填测试内容');
   }
   const layout=await page.evaluate(()=>{
    const card=document.querySelector('.applicant-profile').getBoundingClientRect();
    const brand=document.querySelector('.h5-brand').getBoundingClientRect();
    return {gap:card.top-brand.bottom,overflow:document.documentElement.scrollWidth>innerWidth,heights:[...document.querySelectorAll('.applicant-profile input:not([type=hidden]),.applicant-profile select')].map(e=>e.getBoundingClientRect().height)};
   });
   assert(layout.gap>=0);assert(!layout.overflow);assert(layout.heights.every(h=>h>=48));
  }
  if(process.argv[3])await page.screenshot({path:process.argv[3],fullPage:true});
  console.log('PASS: free-text profile fields, consistent control sizes, no overlap/overflow at 320/375/480px');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
