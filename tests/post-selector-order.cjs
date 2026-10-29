const { chromium } = require('playwright');
const assert = require('node:assert/strict');

// Run against a local test copy. TEST_AUTH_PATH may point to a local fixture
// that changes the saved order before redirecting to the application.
(async () => {
  const base = process.env.TEST_BASE_URL || 'http://127.0.0.1:8080';
  assert(['localhost', '127.0.0.1'].includes(new URL(base).hostname), 'Use a local test server');
  const browser = await chromium.launch({ headless: true, channel: process.env.TEST_BROWSER_CHANNEL || 'msedge' });
  try {
    const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
    await page.goto(base + (process.env.TEST_AUTH_PATH || '/index.php?page=login'));
    if (await page.locator('.login-submit').count()) {
      await page.locator('[name=username]').fill(process.env.TEST_USERNAME || 'admin');
      await page.locator('[name=password]').fill(process.env.TEST_PASSWORD || 'admin123');
      await Promise.all([page.waitForNavigation(), page.locator('.login-submit').click()]);
    }
    await page.goto(base + '/index.php?page=posts');
    const posts = await page.locator('.post-order li').evaluateAll(items => items.map(item => ({
      id: Number(item.querySelector('input').value),
      label: [...item.querySelector('b').childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join('').trim()
        + ' · ' + item.querySelector('small').textContent.trim()
    })));
    assert(posts.length > 1, 'Fixture needs multiple posts');
    const order = posts.map(post => post.id);
    const checkOrder = (ids, label) => {
      assert(ids.every(id => order.includes(id)), `${label}: unknown post`);
      assert.deepEqual(ids, order.filter(id => ids.includes(id)), `${label}: differs from saved order`);
    };
    let selectors = 0;
    const routes = ['standards', 'standards&section=suzhi', 'questions', 'interviews&new=1',
      'preregister', 'talent&tab=arrange', 'talent&tab=final', 'talent&tab=hired', 'talent_pool'];
    if (process.env.TEST_LEGACY_ROUTES === '1') routes.push('standards&legacy=1', 'preregister&legacy=1');
    let talentEdit;
    for (const route of routes) {
      await page.goto(base + '/index.php?page=' + route);
      assert(await page.locator('.workspace').count(), `${route}: missing admin page`);
      const selects = page.locator('select[name=post_id],select[name=basic_post],select[name=intent_post_id]');
      for (let i = 0; i < await selects.count(); i++) {
        const select = selects.nth(i);
        const before = await select.inputValue();
        const ids = await select.locator('option').evaluateAll(options => options.map(o => Number(o.value)).filter(id => id > 0));
        assert(ids.length > 0, `${route}: fixture needs selectable posts`);
        checkOrder(ids, `${route} selector ${i}`);
        assert.equal(await select.inputValue(), before, 'Selection changed');
        selectors++;
      }
      if (route === 'talent_pool') talentEdit = await page.locator('a.talent-edit').first().getAttribute('href');
    }
    assert(talentEdit, 'Fixture needs an assessed candidate to exercise the talent editor');
    await page.goto(new URL(talentEdit, base + '/index.php').href);
    const intent = page.locator('select[name=intent_post_id]');
    checkOrder(await intent.locator('option').evaluateAll(options => options.map(o => Number(o.value)).filter(id => id > 0)), 'Talent editor');
    selectors++;

    // This dynamically built selector is fed by paper_list, not a post query.
    await page.goto(base + '/index.php?page=questions');
    await page.evaluate(() => initPublishedPapers());
    const filter = page.locator('select[aria-label="按岗位筛选已发布试卷"]');
    await filter.locator('option').nth(1).waitFor({ state: 'attached' });
    checkOrder(await filter.locator('option').evaluateAll(options => options.map(o => Number(o.value)).filter(id => id > 0)), 'Dynamic paper filter');
    selectors++;

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base + '/h5.php?m=apply&t=unified');
    const mobile = await page.locator('.candidates a').evaluateAll(links => links.map(a => a.querySelector('b').textContent.trim() + ' · ' + a.querySelector('small').textContent.trim()));
    assert(mobile.length > 0, 'Fixture needs published recruiting posts');
    assert.deepEqual(mobile, posts.map(p => p.label).filter(label => mobile.includes(label)), 'Mobile post order differs');
    console.log(`PASS: ${selectors} rendered post selectors, dynamic paper filter, talent editor and mobile assessment follow the saved order`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
