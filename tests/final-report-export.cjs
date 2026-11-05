const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
(async()=>{
 const markup=execFileSync('php',['-c','php.ini','-r',"define('FINAL_REVIEW_VISUAL',true);$_GET['view']='report';require 'tests/final-review.php';echo '<!doctype html><html lang=\"zh-CN\"><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><link rel=\"stylesheet\" href=\"/assets/app.css\"><link rel=\"stylesheet\" href=\"/assets/extra.css\"><body class=\"final-report-page\"><main class=\"final-report-document\">'.$html.'</main></body></html>';"],{encoding:'utf8'});
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},acceptDownloads:true});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://report.test/**',route=>{
   const url=new URL(route.request().url());
   if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:markup});
   assert(url.pathname.startsWith('/assets/'));
   const file=path.join(process.cwd(),'public',url.pathname);
   return route.fulfill({contentType:file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'image/png',body:fs.readFileSync(file)});
  });
  await page.goto('http://report.test/');await page.locator('#final-report-export-pdf').waitFor();
  await page.evaluate(()=>{window.print=()=>window.__printCalls=(window.__printCalls||0)+1;});
  await page.getByRole('button',{name:'打印评审报告'}).click();assert.equal(await page.evaluate(()=>window.__printCalls),1);
  fs.mkdirSync('output/pdf',{recursive:true});
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});
   await page.screenshot({path:`output/pdf/final-report-screen-${width}.png`,fullPage:true});
   const downloading=page.waitForEvent('download',{timeout:120000});await page.locator('#final-report-export-pdf').click();
   const download=await downloading;assert.equal(download.suggestedFilename(),'示例候选人-GEO建设专员 · 示例公司-人才报告.pdf');
   await download.saveAs(`output/pdf/final-report-${width}.pdf`);await page.getByText('PDF 已生成',{exact:true}).waitFor();
   assert.equal(await page.evaluate(()=>window.__printCalls),1,'Export must not open print dialog');
   assert(await page.locator('#final-report-export-pdf').isEnabled());
   assert.equal(await page.locator('body > div[style*="-100000px"]').count(),0);
  }
  await page.setViewportSize({width:1440,height:1000});
  await page.evaluate(()=>{document.querySelector('.answer-preview-value p').textContent=Array.from({length:100},(_,i)=>`第${i+1}条长回答：完整展示测评经历、岗位工作成果与后续改进计划。`).join('\n');document.querySelector('.answer-preview-value p').style.whiteSpace='pre-wrap';});
  const longDownload=page.waitForEvent('download',{timeout:120000});await page.locator('#final-report-export-pdf').click();await (await longDownload).saveAs('output/pdf/final-report-long.pdf');await page.getByText('PDF 已生成',{exact:true}).waitFor();
  await page.evaluate(()=>window.html2pdf=undefined);await page.locator('#final-report-export-pdf').click();await page.getByText('导出失败，请刷新页面后重试。',{exact:true}).waitFor();assert(await page.locator('#final-report-export-pdf').isEnabled());
  assert.deepEqual(errors,[]);console.log('PASS: real PDF downloads at desktop/mobile widths, filename, print separation, cleanup and error recovery');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
