const { chromium } = require('playwright');
const { readFileSync } = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({headless:true,...(process.env.TEST_BROWSER_CHANNEL?{channel:process.env.TEST_BROWSER_CHANNEL}:{})});
  try {
    const page = await browser.newPage();
    const css = ['public/assets/app.css','public/assets/extra.css'].map(p=>readFileSync(p,'utf8')).join('\n');
    for (const width of [320,375,480,768]) {
      await page.setViewportSize({width,height:900});
      for (const title of ['您已被录用，无需再次测评','暂不能重复测评']) {
        await page.setContent(`<style>${css}</style><body class="h5-body"><main class="h5"><header class="h5-brand"><i>任</i><b>任职管理系统</b></header><script></script><section class="h5-card assessment-notice"><h2>${title}</h2><p>本次答题不会保存，也不会进入待安排面试。可再次测评时间：2026-10-01 16:00:00。</p></section></main></body>`);
        const layout = await page.evaluate(() => {
          const brand = document.querySelector('.h5-brand').getBoundingClientRect();
          const card = document.querySelector('.assessment-notice').getBoundingClientRect();
          return {gap:card.top-brand.bottom,overflow:document.documentElement.scrollWidth>innerWidth};
        });
        assert(layout.gap>=18,JSON.stringify({width,title,...layout}));
        assert(!layout.overflow,`horizontal overflow at ${width}`);
      }
    }
    console.log('PASS: both assessment notices clear the header at 320/375/480/768px, no horizontal overflow');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
