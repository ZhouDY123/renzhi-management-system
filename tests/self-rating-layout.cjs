const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try{
  const page=await browser.newPage({viewport:{width:375,height:850}});
  await page.goto(process.argv[2]);
  for(const [name,value] of Object.entries({name:'布局测试',mobile:'13900000988','profile[age]':'26','profile[edu]':'本科','profile[major]':'测试专业','profile[school_name]':'测试大学'}))await page.locator(`[name="${name}"]`).fill(value);
  await page.locator('[name="profile[gender]"]').selectOption('女');
  await page.getByRole('button',{name:'下一步，进入答题'}).click();
  await page.waitForLoadState('networkidle');
  const questions=page.locator('.self-rating-question');assert(await questions.count()>1);
  for(const width of [320,375,480]){
   await page.setViewportSize({width,height:850});
   assert.equal(await questions.first().locator('input[type=radio]').count(),5);
   await questions.first().getByText('比较符合',{exact:true}).click();
   assert.equal(await questions.first().locator('input:checked').inputValue(),'比较符合');
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  }
  if(process.argv[3])await questions.first().screenshot({path:process.argv[3]});
  console.log('PASS: five-level rating choices selectable, no overflow at 320/375/480px');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
