document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form').forEach(form => { form.noValidate = true; });
  initSearchFields(); initPasswordToggles(); initStandardRuleDefaults(); initFormValidation(); initConfirmations(); initInterviewRegistrationActions(); initStandardEditModals(); initGroupedStandardTiers(); initStandardTabs(); initStandardDimensionSearch(); initStandardDimensionCreate(); initQuestionPaperBuilder(); initQuestionEditorOptions(); initDirectQrActions(); initSelectedFields(); initFormModals(); initQrModals(); initTablePagination();
});

const focusables = 'a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
let activeOverlay = null, previousFocus = null;

function openDialog(overlay, preferred) {
  previousFocus = document.activeElement; activeOverlay = overlay;
  overlay.classList.add('open'); overlay.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modal-open'); document.querySelector('.shell')?.setAttribute('inert', '');
  requestAnimationFrame(() => (preferred || overlay.querySelector(focusables))?.focus());
}
function closeDialog(overlay) {
  overlay.classList.remove('open'); overlay.setAttribute('aria-hidden', 'true');
  if (!document.querySelector('.modal-overlay.open')) { document.body.classList.remove('modal-open'); document.querySelector('.shell')?.removeAttribute('inert'); }
  activeOverlay = document.querySelector('.modal-overlay.open'); previousFocus?.focus();
}
function restoreQuestionOptionsField() {
  const form = activeOverlay?.querySelector('form.question-editor');
  if (!form) return;
  form.dispatchEvent(new CustomEvent('restoreQuestionOptionsEditor'));
}
document.addEventListener('keydown', e => {
  if (!activeOverlay) return;
  const editor = activeOverlay.querySelector('form.question-editor');
  const typing = e.target instanceof HTMLElement && e.target.matches('input, textarea, select, [contenteditable="true"]');
  // 浏览器在弹窗空白处执行 Ctrl+Z 会撤销页面 DOM 变动，导致选项输入框被移除。
  // 只拦截此场景；光标位于输入框时仍保留正常的撤销行为。
  if (editor && !typing && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
    e.preventDefault(); restoreQuestionOptionsField(); return;
  }
  if (e.key === 'Escape') { e.preventDefault(); activeOverlay.querySelector('[data-dialog-close]')?.click(); return; }
  if (e.key !== 'Tab') return;
  const items = [...activeOverlay.querySelectorAll(focusables)].filter(el => el.offsetParent !== null);
  if (!items.length) return;
  if (e.shiftKey && document.activeElement === items[0]) { e.preventDefault(); items.at(-1).focus(); }
  if (!e.shiftKey && document.activeElement === items.at(-1)) { e.preventDefault(); items[0].focus(); }
});

function initSearchFields() {
  document.querySelectorAll('.global-search,.filter-bar').forEach(form => {
    const input = form.querySelector('input[name="q"]'); if (!input) return;
    const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'search-clear';
    clear.setAttribute('aria-label', '清除搜索内容'); clear.textContent = '×';
    const sync = () => { clear.hidden = !input.value; };
    clear.addEventListener('click', () => { input.value = ''; sync(); input.focus(); if (form.classList.contains('filter-bar')) form.requestSubmit(); });
    input.addEventListener('input', sync); input.insertAdjacentElement('afterend', clear); sync();
  });
}

function initPasswordToggles() {
  document.querySelectorAll('.password-toggle').forEach(button => {
    const input = button.parentElement?.querySelector('input[type="password"],input[type="text"]');
    if (!input) return;
    button.addEventListener('click', () => {
      const revealed = input.type === 'text';
      input.type = revealed ? 'password' : 'text';
      const label = revealed ? '显示密码' : '隐藏密码';
      button.querySelector('.sr-only')?.replaceChildren(label.replace('密码', ''));
      button.classList.toggle('is-revealed', !revealed);
      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
      button.setAttribute('aria-pressed', String(!revealed));
      input.focus();
    });
  });
}

function initFormValidation() {
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', e => {
    form.querySelectorAll('.field-error').forEach(n => n.remove());
    form.querySelectorAll('[aria-invalid]').forEach(f => f.removeAttribute('aria-invalid'));
    const invalid = [...form.querySelectorAll('input,select,textarea')].filter(f => !f.checkValidity());
    if (invalid.length) {
      e.preventDefault();
      invalid.forEach((field, i) => {
        const id = field.id || `field-${Date.now()}-${i}`; field.id = id; field.setAttribute('aria-invalid', 'true');
        const msg = document.createElement('small'); msg.className = 'field-error'; msg.id = `${id}-error`;
        msg.textContent = field.validity.valueMissing ? '请填写此项后再继续。' : '填写格式不正确，请检查后重试。';
        field.setAttribute('aria-describedby', msg.id); field.insertAdjacentElement('afterend', msg);
      }); invalid[0].focus(); return;
    }
    const btn = e.submitter;
    if (btn && !form.dataset.confirmPending) { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; btn.textContent = '正在处理…'; }
  }));
}

function showConfirm({title, description, actionLabel, onConfirm}) {
  const overlay = document.createElement('div'); overlay.className = 'modal-overlay confirm-overlay'; overlay.setAttribute('aria-hidden', 'true');
  const id = `confirm-${Date.now()}`;
  overlay.innerHTML = `<div class="modal-dialog confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="${id}" aria-describedby="${id}-desc"><div class="confirm-mark" aria-hidden="true">!</div><h2 id="${id}"></h2><p id="${id}-desc"></p><div class="modal-actions"><button type="button" class="btn secondary" data-dialog-close>取消</button><button type="button" class="btn danger-solid" data-confirm></button></div></div>`;
  overlay.querySelector('h2').textContent = title; overlay.querySelector('p').textContent = description; overlay.querySelector('[data-confirm]').textContent = actionLabel;
  document.body.appendChild(overlay); const cancel = overlay.querySelector('[data-dialog-close]');
  const close = () => { closeDialog(overlay); setTimeout(() => overlay.remove(), 180); };
  cancel.addEventListener('click', close); overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  overlay.querySelector('[data-confirm]').addEventListener('click', () => { close(); onConfirm(); }); openDialog(overlay, cancel);
}

function initConfirmations() {
  document.querySelectorAll('form[data-confirm-message]').forEach(form => {
    const description = form.dataset.confirmMessage || '此操作会改变当前数据，是否继续？';
    form.addEventListener('submit', e => {
      if (form.dataset.confirmed === '1') return; e.preventDefault(); e.stopImmediatePropagation();
      const label = e.submitter?.textContent.trim() || '确认操作';
      showConfirm({title: label, description, actionLabel: label, onConfirm: () => { form.dataset.confirmed = '1'; form.dataset.confirmPending = '1'; form.requestSubmit(e.submitter); }});
    }, true);
  });
}

function initInterviewRegistrationActions() {
  document.querySelectorAll('form[action*="action=interview_pre_update"]').forEach(form => {
    let operation = form.querySelector('input[name="op"]');
    if (!operation) { operation = document.createElement('input'); operation.type = 'hidden'; operation.name = 'op'; form.append(operation); }
    form.querySelectorAll('button[name="op"]').forEach(button => button.addEventListener('click', () => { operation.value = button.value; }));
    form.addEventListener('submit', event => { if (!operation.value) { event.preventDefault(); operation.value = 'change_post'; form.requestSubmit(); } });
  });
}

function initStandardRuleDefaults() {
  document.querySelectorAll('form[action*="action=standard_save"]').forEach(form => {
    const tier = form.querySelector('[name="tier_label"]'), value = form.querySelector('[name="rule_value"]'), type = form.querySelector('[name="match_type"]');
    if (!tier || !value || !type) return;
    const syncHint = () => { const range = type.value === 'range'; value.required = !range; value.placeholder = range ? '数值范围请填写下方最低值或最高值' : '不填时将使用“档位说明”'; };
    type.addEventListener('change', syncHint); syncHint();
    form.addEventListener('submit', () => { if (type.value !== 'range' && !value.value.trim()) value.value = tier.value.trim(); });
  });
}

function initStandardEditModals() {
  document.querySelectorAll('a.table-action[href*="page=standards"][href*="edit="]').forEach(link => link.addEventListener('click', async event => {
    event.preventDefault();
    try {
      const response = await fetch(link.href, {credentials:'same-origin'}); if (!response.ok) throw new Error();
      const source = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('form[action*="action=standard_save"]'); if (!source) throw new Error();
      const overlay = document.createElement('div'); overlay.className = 'modal-overlay standard-edit-overlay'; overlay.setAttribute('aria-hidden','true');
      const dialog = document.createElement('div'); dialog.className = 'modal-dialog'; dialog.setAttribute('role','dialog'); dialog.setAttribute('aria-modal','true');
      const top = document.createElement('div'); top.className = 'modal-top'; top.innerHTML = '<div><small>评分规则</small><h2>编辑自动评分条件</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="关闭弹窗">×</button>';
      source.classList.add('modal-form'); source.querySelector('.form-title')?.remove(); source.querySelectorAll('select[data-selected]').forEach(select => { if (select.dataset.selected) select.value = select.dataset.selected; });
      dialog.append(top, source); overlay.append(dialog); document.body.append(overlay);
      const close = () => { closeDialog(overlay); setTimeout(() => overlay.remove(),180); }; top.querySelector('[data-dialog-close]').addEventListener('click', close); overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
      source.addEventListener('submit', () => { const tier=source.querySelector('[name="tier_label"]'),value=source.querySelector('[name="rule_value"]'),type=source.querySelector('[name="match_type"]'); if(type?.value!=='range'&&!value?.value.trim())value.value=tier?.value.trim()||''; });
      openDialog(overlay, source.querySelector('select, input, textarea'));
    } catch { location.href = link.href; }
  }));
}

function initGroupedStandardTiers() {
  document.querySelectorAll('form.dimension-score-card').forEach(form => {
    const list = form.querySelector('.dimension-score-list'), footer = form.querySelector('footer'); if (!list || !footer) return;
    const saveButton = footer.querySelector('.btn.primary'); if (saveButton) saveButton.textContent = '保存';
    if ((form.querySelector('[name="dim_code"]')?.value || '').startsWith('custom_')) form.action='?page=standards&action=standard_custom_group_save';
    form.querySelector('button[name="op"][value="publish"]')?.setAttribute('formaction','?page=standards&action=standard_dimension_publish');
    const actions = form.querySelector('.dimension-actions');
    if (actions?.querySelector('.badge.green') && !actions.querySelector('[data-disable-dimension]')) { const disable = document.createElement('button'); disable.type='submit'; disable.className='btn secondary disable-dimension'; disable.textContent='禁用'; disable.dataset.disableDimension='1'; disable.setAttribute('formaction','?page=standards&action=standard_dimension_disable'); actions.append(disable); }
    const numericDimension = form.dataset.numeric === '1'; const add = document.createElement('button'); add.type='button'; add.className='btn secondary add-tier'; add.textContent=numericDimension?'＋ 新增数值区间':'＋ 新增评分档位'; footer.prepend(add);
    list.querySelectorAll('.dimension-score-row').forEach(row => addRemove(row, false));
    add.addEventListener('click', () => { const row=document.createElement('div'); row.className='dimension-score-row new-tier-row'; row.innerHTML=numericDimension?'<div class="range-inputs"><label class="range-title">档位标题（可自定义）<input name="new_label[]" placeholder="填写区间后自动生成"></label><label>最小值<input name="new_min[]" type="number" required></label><label>最大值（不含）<input name="new_max[]" type="number" required></label></div><label>分值<input name="new_value[]" type="number" min="0" step="0.1" value="0"></label>':'<div><label>答案或条件<input name="new_label[]" placeholder="例如：优秀（未填写则不新增）"></label></div><label>分值<input name="new_value[]" type="number" min="0" step="0.1" value="0"></label>'; list.append(row); addRemove(row, true); row.querySelector('input')?.focus(); });
    if (numericDimension) form.addEventListener('submit', () => { const code=form.querySelector('[name="dim_code"]')?.value; const unit=code==='age'?'岁':(code?.includes('co')||code?.includes('years')?'年':''); const suffix={group_co:'集团公司工作履历',listed_co:'上市公司工作履历',private_co:'非上市公司工作履历',work_years:'参加工作年限',prof_years:'从事专业年限'}[code]||''; list.querySelectorAll('.new-tier-row').forEach(row => { const min=row.querySelector('[name="new_min[]"]')?.value, max=row.querySelector('[name="new_max[]"]')?.value, label=row.querySelector('[name="new_label[]"]'); if(min!==''&&max!==''&&label&&!label.value.trim())label.value=`${min}至${max}${unit}${suffix}`; }); });
    function addRemove(row, isNew) { const button=document.createElement('button'); button.type='button'; button.className='remove-tier'; button.textContent='删除'; button.addEventListener('click', () => { if (isNew) { row.remove(); return; } const id=row.querySelector('[name="rule_id[]"]')?.value; if (id && !row.querySelector('[name="remove_id[]"]')) { const marker=document.createElement('input'); marker.type='hidden'; marker.name='remove_id[]'; marker.value=id; row.append(marker); } row.classList.add('is-removed'); }); row.append(button); }
  });
}

function initStandardTabs() {
  const tabs = [...document.querySelectorAll('[data-standard-show]')];
  const panels = [...document.querySelectorAll('[data-standard-panel]')];
  if (!tabs.length || !panels.length) return;
  tabs.forEach(tab => tab.addEventListener('click', () => {
    const target = tab.dataset.standardShow;
    tabs.forEach(item => { const active = item.dataset.standardShow === target; item.classList.toggle('is-active', active); item.setAttribute('aria-selected', String(active)); });
    panels.forEach(panel => { panel.hidden = panel.dataset.standardPanel !== target; });
    document.querySelectorAll('.standard-create-slot .modal-trigger,.page-actions .modal-trigger').forEach(trigger => {
      if (!trigger.dataset.defaultLabel) trigger.dataset.defaultLabel = trigger.textContent;
      const isSuzhi = target === 'suzhi';
      trigger.textContent = isSuzhi ? '＋ 新增基本素质维度' : trigger.dataset.defaultLabel;
    });
    const form = document.querySelector('form[action*="standard_dimension_create"]');
    if (form) { form.querySelector('[name="standard_scope"]')?.setAttribute('value', target); const type=form.querySelector('[data-dimension-type]'); if(type){const isSuzhi=target==='suzhi';if(isSuzhi)type.value='answer';type.disabled=isSuzhi;type.dispatchEvent(new Event('change'));} const title = form.querySelector('.form-title h2'); if (title) title.textContent = `新增${target === 'suzhi' ? '基本素质' : '基本条件'}维度`; form.closest('.modal-dialog')?.querySelector('.modal-top h2') && (form.closest('.modal-dialog').querySelector('.modal-top h2').textContent = `新增${target === 'suzhi' ? '基本素质' : '基本条件'}维度`); }
  }));
}

function initStandardDimensionSearch() {
  document.querySelectorAll('[data-standard-search]').forEach(input => {
    const scope = input.dataset.standardSearch;
    const panel = document.querySelector(`[data-standard-panel="${scope}"]`);
    const clear = document.querySelector(`[data-standard-search-clear="${scope}"]`);
    const result = document.querySelector(`[data-standard-search-result="${scope}"]`);
    if (!panel) return;
    const apply = () => {
      const keyword = input.value.trim().toLocaleLowerCase('zh-CN');
      const cards = [...panel.querySelectorAll('.dimension-score-card')];
      let visible = 0;
      cards.forEach(card => {
        const matched = !keyword || card.textContent.toLocaleLowerCase('zh-CN').includes(keyword);
        card.hidden = !matched;
        if (matched) visible += 1;
      });
      if (clear) clear.hidden = !input.value;
      if (result) result.textContent = keyword ? `找到 ${visible} 个匹配维度` : `共 ${cards.length} 个维度`;
    };
    input.addEventListener('input', apply);
    clear?.addEventListener('click', () => { input.value = ''; apply(); input.focus(); });
    apply();
  });
}

function initStandardDimensionCreate() {
  document.querySelectorAll('form.standard-dimension-form').forEach(form => {
    const type = form.querySelector('[data-dimension-type]'), list = form.querySelector('[data-new-dimension-tier-list]'), add = form.querySelector('[data-add-initial-tier]');
    if (!type || !list || !add) return;
    const sync = () => list.querySelectorAll('.new-dimension-tier').forEach(row => {
      const range = row.querySelector('.tier-range-fields'), answer = row.querySelector('.tier-answer-field'), correctField=row.querySelector('.tier-correct-field'); const isRange = type.value === 'range', isSuzhi=form.querySelector('[name="standard_scope"]')?.value==='suzhi';
      range.hidden = !isRange; answer.hidden = isRange;
      range.querySelectorAll('input').forEach(input => { input.disabled = !isRange; input.required = isRange; });
      answer.querySelectorAll('input').forEach(input => { input.disabled = isRange; input.required = !isRange; });
      if (correctField) { const correct=correctField.querySelector('select'), score=row.querySelector('[name="tier_value[]"]'); correctField.hidden=!isSuzhi; correct.disabled=!isSuzhi; const syncCorrect=()=>{const wrong=isSuzhi&&correct.value==='0';if(score){score.readOnly=wrong;if(wrong)score.value='0';}}; if(!correct.dataset.bound){correct.addEventListener('change',syncCorrect);correct.dataset.bound='1';}syncCorrect(); }
    });
    const removeButton = row => { if (list.children.length > 1) { const button = document.createElement('button'); button.type = 'button'; button.className = 'remove-initial-tier'; button.textContent = '删除此档位'; button.addEventListener('click', () => row.remove()); row.append(button); } };
    add.addEventListener('click', () => { const row = list.firstElementChild.cloneNode(true); row.querySelectorAll('input').forEach(input => input.value = ''); list.append(row); removeButton(row); sync(); row.querySelector('input:not([disabled])')?.focus(); });
    type.addEventListener('change', sync); sync();
  });
}

function initQuestionPaperBuilder() {
  document.querySelector('.question-library .panel-head small')?.remove();
  document.querySelectorAll('[data-question-paper-builder]').forEach(form => {
    form.action = '?page=questions&action=question_publish_v2';
    const select = form.querySelector('[data-question-post-select]');
    const count = form.querySelector('[data-question-pick-count]');
    const summary = form.querySelector('[data-question-selected-summary]');
    const pickHead = form.querySelector('.question-pick-head');
    const pickList = form.querySelector('.question-pick-list');
    const libraryList = document.querySelector('.question-library .question-list');
    const actionsByQuestionId = new Map();
    libraryList?.querySelectorAll('.question').forEach(row => {
      const id = row.querySelector('input[name="id"]')?.value;
      const actions = row.querySelector('.question-actions');
      if (id && actions) actionsByQuestionId.set(id, actions.cloneNode(true));
    });
    if (!select) return;
    if (!form.id) form.id = 'question-paper-publish-form';
    const tools = document.createElement('div'); tools.className = 'question-picker-tools';
    const searchWrap = document.createElement('label'); searchWrap.className = 'question-picker-search'; searchWrap.setAttribute('aria-label', '搜索岗位专业题');
    const search = document.createElement('input'); search.type = 'search'; search.placeholder = '搜索题干或关键词'; search.autocomplete = 'off'; search.setAttribute('aria-label', '搜索岗位专业题');
    const searchClear = document.createElement('button'); searchClear.type = 'button'; searchClear.className = 'question-picker-clear'; searchClear.textContent = '×'; searchClear.setAttribute('aria-label', '清除题目搜索'); searchClear.hidden = true;
    searchWrap.append(search, searchClear);
    const type = document.createElement('select'); type.className = 'question-picker-type'; type.setAttribute('aria-label', '按题型筛选'); [['','全部题型'],['single','单选题'],['multi','多选题'],['short','简答题']].forEach(([value,label]) => { const option = document.createElement('option'); option.value = value; option.textContent = label; type.append(option); });
    const selectVisible = document.createElement('button'); selectVisible.type = 'button'; selectVisible.className = 'btn secondary question-picker-bulk'; selectVisible.textContent = '全选当前结果';
    const clearSelected = document.createElement('button'); clearSelected.type = 'button'; clearSelected.className = 'question-picker-link'; clearSelected.textContent = '清空已选';
    tools.append(searchWrap, type, selectVisible, clearSelected);
    const basket = document.createElement('section'); basket.className = 'question-selection-basket'; basket.setAttribute('aria-label', '已选专业题');
    let pickerRoot = form;
    if (libraryList && pickHead && pickList) {
      pickerRoot = libraryList;
      pickList.querySelectorAll('input').forEach(input => input.setAttribute('form', form.id));
      pickList.querySelectorAll('.question-pick').forEach(item => {
        const id = item.querySelector('input[name="professional_ids[]"]')?.value;
        const actions = id ? actionsByQuestionId.get(id) : null;
        if (actions) item.append(actions);
      });
      libraryList.replaceChildren(pickHead, tools, pickList);
      select.closest('.question-post-select')?.after(basket);
    } else if (pickHead) { pickHead.after(tools); tools.after(basket); }
    const typeMap = {single:'单选题', multi:'多选题', short:'简答题'};
    const selectedRows = () => [...pickerRoot.querySelectorAll('[data-question-post]')].filter(item => item.dataset.questionPost === select.value && item.querySelector('input[type="checkbox"]')?.checked);
    const renderBasket = () => {
      const rows = selectedRows(); basket.replaceChildren();
      const head = document.createElement('div'); head.className = 'question-basket-head';
      const title = document.createElement('b'); title.textContent = `已选题篮（${rows.length}）`;
      const hint = document.createElement('small'); hint.textContent = rows.length ? '发布时将按下列顺序写入题卷' : '从下方题库勾选本次要发布的专业题'; head.append(title, hint); basket.append(head);
      if (!rows.length) return;
      const list = document.createElement('ol'); list.className = 'question-basket-list';
      rows.forEach(item => { const checkbox = item.querySelector('input[type="checkbox"]'); const itemText = item.querySelector('b')?.textContent || '未命名题目'; const meta = item.querySelector('small')?.textContent || ''; const row = document.createElement('li'); const copy = document.createElement('span'); const name = document.createElement('b'); name.textContent = itemText; const detail = document.createElement('small'); detail.textContent = meta; copy.append(name, detail); const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'question-basket-remove'; remove.textContent = '移除'; remove.setAttribute('aria-label', `从题卷中移除：${itemText}`); remove.addEventListener('click', () => { checkbox.checked = false; checkbox.dispatchEvent(new Event('change', {bubbles:true})); }); row.append(copy, remove); list.append(row); }); basket.append(list);
    };
    const sync = () => {
      const postId = select.value;
      const items = [...pickerRoot.querySelectorAll('[data-question-post]')];
      const keyword = search.value.trim().toLowerCase(); const typeValue = type.value;
      let available = 0, selected = 0, visible = 0;
      items.forEach(item => {
        const match = item.dataset.questionPost === postId;
        const checkbox = item.querySelector('input[type="checkbox"]');
        const text = item.textContent.toLowerCase(); const questionType = Object.entries(typeMap).find(([,label]) => text.includes(label))?.[0] || '';
        const shown = match && (!keyword || text.includes(keyword)) && (!typeValue || questionType === typeValue);
        item.hidden = !shown;
        if (checkbox) checkbox.disabled = !match;
        if (match) { available += 1; if (shown) visible += 1; if (checkbox?.checked) selected += 1; }
      });
      if (count) count.textContent = available ? `已选 ${selected} / ${available} 道专业题` : '该岗位暂未启用专业题';
      if (summary) { const number = document.createElement('b'); number.textContent = String(selected); summary.replaceChildren(number, document.createTextNode(' 道已选专业题')); }
      searchClear.hidden = !search.value; selectVisible.disabled = visible === 0; selectVisible.textContent = visible ? `全选当前结果（${visible}）` : '无匹配题目'; clearSelected.disabled = selected === 0;
      renderBasket();
    };
    select.addEventListener('change', sync);
    pickerRoot.querySelectorAll('input[type="checkbox"]').forEach(input => input.addEventListener('change', sync));
    search.addEventListener('input', sync); type.addEventListener('change', sync);
    searchClear.addEventListener('click', () => { search.value = ''; sync(); search.focus(); });
    selectVisible.addEventListener('click', () => { pickerRoot.querySelectorAll('[data-question-post]').forEach(item => { if (!item.hidden && item.dataset.questionPost === select.value) { const checkbox = item.querySelector('input[type="checkbox"]'); if (checkbox) checkbox.checked = true; } }); sync(); });
    clearSelected.addEventListener('click', () => { pickerRoot.querySelectorAll('[data-question-post]').forEach(item => { if (item.dataset.questionPost === select.value) { const checkbox = item.querySelector('input[type="checkbox"]'); if (checkbox) checkbox.checked = false; } }); sync(); });
    pickerRoot.querySelectorAll('.question-pick').forEach(item => item.addEventListener('click', event => {
      const checkbox = item.querySelector('input[type="checkbox"]');
      if (!checkbox || checkbox.disabled || event.target === checkbox || event.target.closest('a,button,form')) return;
      event.preventDefault(); checkbox.checked = !checkbox.checked; checkbox.dispatchEvent(new Event('change', {bubbles:true}));
    }));
    sync();
  });
}

function initQuestionEditorOptions() {
  document.querySelectorAll('form.question-editor').forEach(form => {
    const field = form.querySelector('textarea[name="options"]');
    const label = field?.closest('label') || [...form.querySelectorAll('label')].find(item => item.textContent.includes('选项（'));
    if (!field || !label) return;
    field.classList.add('options-storage');
    const textNode = [...label.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
    if (textNode) textNode.textContent = '选项（单选题、多选题必填）';

    const parseOptions = () => {
      const sample = '["选项 A","选项 B","选项 C"]';
      if (!field.value.trim() || field.value.trim() === sample) return [];
      try { const value = JSON.parse(field.value); if (Array.isArray(value)) return value.map(item => String(item)); } catch (_) {}
      return field.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean);
    };
    const build = () => {
      label.querySelector('.question-options-editor')?.remove();
      const editor = document.createElement('div'); editor.className = 'question-options-editor';
      const list = document.createElement('div'); list.className = 'question-options-list';
      const sync = () => { field.value = JSON.stringify([...list.querySelectorAll('input')].map(input => input.value.trim()).filter(Boolean)); };
      const row = value => {
        const item = document.createElement('div'); item.className = 'question-option-row';
        const input = document.createElement('input'); input.type = 'text'; input.placeholder = '请输入选项内容'; input.value = value || '';
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn text-danger option-remove'; remove.textContent = '删除';
        remove.addEventListener('click', () => { item.remove(); sync(); }); input.addEventListener('input', sync);
        item.append(input, remove); list.append(item);
      };
      const add = document.createElement('button'); add.type = 'button'; add.className = 'btn secondary option-add'; add.textContent = '+ 新增选项';
      add.addEventListener('click', () => { row(''); sync(); list.querySelector('.question-option-row:last-child input')?.focus(); });
      (parseOptions().length ? parseOptions() : ['']).forEach(row);
      editor.append(list, add); label.append(editor); sync();
      const type = form.querySelector('[name="q_type"]');
      const toggle = () => { editor.hidden = type?.value === 'short'; };
      type?.addEventListener('change', toggle); toggle();
    };
    build();
    form.addEventListener('restoreQuestionOptionsEditor', () => { if (!label.querySelector('.question-options-editor')) build(); });
    form.addEventListener('resetQuestionOptionsEditor', build);
  });
}

function initDirectQrActions() {
  document.querySelector('[data-direct-print]')?.addEventListener('click', () => window.print());
  document.querySelector('[data-direct-download]')?.addEventListener('click', () => window.downloadQr?.());
}

function initFormModals() {
  const forms = [...document.querySelectorAll('form.form-panel:not(.settings-form),form.dimension-form')]; if (!forms.length) return;
  const head = document.querySelector('.page-head'); let actions = head?.querySelector('.page-actions');
  if (head && !actions) { actions = document.createElement('div'); actions.className = 'page-actions'; [...head.children].slice(1).forEach(el => actions.appendChild(el)); head.appendChild(actions); }
  forms.forEach((form, i) => {
    const parent = form.parentElement, split = parent?.closest('.split');
    const title = form.querySelector('.form-title h2')?.textContent.trim() || (form.classList.contains('dimension-form') ? '新增基本素质维度' : '新增或编辑');
    const overlay = document.createElement('div'); overlay.className = 'modal-overlay'; overlay.setAttribute('aria-hidden', 'true');
    const dialog = document.createElement('div'); dialog.className = 'modal-dialog'; dialog.setAttribute('role', 'dialog'); dialog.setAttribute('aria-modal', 'true'); dialog.setAttribute('aria-labelledby', `form-modal-${i}`);
    const top = document.createElement('div'); top.className = 'modal-top'; top.innerHTML = `<div><small>档案操作</small><h2 id="form-modal-${i}"></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="关闭弹窗">×</button>`; top.querySelector('h2').textContent = title;
    dialog.append(top); form.classList.add('modal-form'); dialog.append(form); overlay.append(dialog); document.body.append(overlay);
    if (split) split.classList.add('modalized-layout'); if (parent && !parent.children.length && parent.parentElement?.classList.contains('split')) parent.remove();
    let dirty = false; form.addEventListener('input', () => { dirty = true; });
    const open = () => openDialog(overlay, form.querySelector(focusables));
    const discard = () => { form.reset(); form.dispatchEvent(new CustomEvent('resetQuestionOptionsEditor')); dirty=false; closeDialog(overlay); };
    const close = () => dirty ? showConfirm({title:'放弃未保存的修改？',description:'关闭后，本次填写的内容不会保存。',actionLabel:'放弃修改',onConfirm:discard}) : discard();
    top.querySelector('[data-dialog-close]').addEventListener('click', close); overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    const params = new URLSearchParams(location.search); const isEditing = params.has('edit'); const autoOpen = i === 0 && (params.has('new') || isEditing);
    const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'btn primary modal-trigger'; const triggerTitle = isEditing ? title.replace(/^编辑/,'新增') : title; trigger.textContent = triggerTitle.includes('密码') ? `修改${triggerTitle.replace('修改','')}` : `＋ ${triggerTitle}`; trigger.dataset.defaultLabel = trigger.textContent; trigger.addEventListener('click', () => { if (isEditing) { const next = new URLSearchParams(); const currentPage = new URLSearchParams(location.search).get('page') || ''; next.set('page', currentPage); next.set('new','1'); location.href = `${location.pathname}?${next}`; return; } open(); }); actions?.append(trigger); if (form.classList.contains('standard-dimension-form')) { const switcher=document.querySelector('.standard-section-switch'); if(switcher&&!switcher.dataset.swapped){const slot=document.createElement('div');slot.className='standard-create-slot';switcher.replaceWith(slot);actions?.append(switcher);slot.append(trigger);switcher.dataset.swapped='1';} }
    if (autoOpen) { open(); params.delete('new'); params.delete('edit'); const query = params.toString(); history.replaceState(null, '', `${location.pathname}${query ? `?${query}` : ''}`); }
  });
}

function initSelectedFields() {
  document.querySelectorAll('select[data-selected]').forEach(select => { if (select.dataset.selected) select.value = select.dataset.selected; });
  const groupType = new URLSearchParams(location.search).get('group_type');
  if (groupType) { const select = document.querySelector('select[name="group_type"]'); if (select) select.value = groupType; }
}

function initQrModals() {
  document.querySelectorAll('a[href*="page=qrcode"]').forEach(link => link.addEventListener('click', async e => {
    e.preventDefault();
    try {
      const response = await fetch(link.href,{credentials:'same-origin'}); if(!response.ok) throw new Error();
      const copy = new DOMParser().parseFromString(await response.text(),'text/html'), source = copy.querySelector('.qr-sheet');
      const title=source?.querySelector('h1')?.textContent.trim()||'扫码入口', subtitle=source?.querySelector('p')?.textContent.trim()||'', value=source?.querySelector('.qr-url')?.textContent.trim()||'', purpose=source?.querySelector(':scope > small')?.textContent.trim()||'请使用手机扫码进入'; if(!value) throw new Error();
      const overlay=document.createElement('div'); overlay.className='modal-overlay qr-modal-overlay'; overlay.setAttribute('aria-hidden','true');
      overlay.innerHTML='<div class="modal-dialog qr-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="qr-title"><div class="modal-top"><div><small>二维码中心</small><h2 id="qr-title"></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="关闭二维码弹窗">×</button></div><div class="qr-modal-body"><div class="qr-brand">任职管理系统</div><h2 class="qr-content-title"></h2><p class="qr-subtitle"></p><div class="qr-popup-code" aria-label="二维码图像"></div><div class="qr-url"></div><small class="qr-purpose"></small><div class="qr-actions"><button type="button" class="btn secondary qr-print">打印二维码</button><button type="button" class="btn primary qr-download">下载 PNG</button></div></div></div>';
      overlay.querySelector('#qr-title').textContent=title; overlay.querySelector('.qr-content-title').textContent=title; overlay.querySelector('.qr-subtitle').textContent=subtitle; overlay.querySelector('.qr-purpose').textContent=purpose; overlay.querySelector('.qr-url').textContent=value; document.body.append(overlay);
      const box=overlay.querySelector('.qr-popup-code'); new QRCode(box,{text:value,width:280,height:280,colorDark:'#0D2442',colorLight:'#fff',correctLevel:QRCode.CorrectLevel.H});
      const close=()=>{closeDialog(overlay);setTimeout(()=>overlay.remove(),180);}; overlay.querySelector('[data-dialog-close]').addEventListener('click',close); overlay.addEventListener('click',e=>{if(e.target===overlay)close();});
      overlay.querySelector('.qr-print').addEventListener('click',()=>window.print()); overlay.querySelector('.qr-download').addEventListener('click',()=>{const canvas=box.querySelector('canvas');if(!canvas)return;const a=document.createElement('a');a.download=`${title}-二维码.png`;a.href=canvas.toDataURL('image/png');a.click();}); openDialog(overlay,overlay.querySelector('[data-dialog-close]'));
    } catch { location.href=link.href; }
  }));
}

function initTablePagination() {
  document.querySelectorAll('.table-wrap table').forEach(table=>{
    const rows=[...table.tBodies].flatMap(body=>[...body.rows]), size=12; if(rows.length<=size)return; let page=1; const pages=Math.ceil(rows.length/size),nav=document.createElement('nav');nav.className='table-pagination';nav.setAttribute('aria-label','表格分页');nav.innerHTML='<span></span><div><button type="button" class="btn secondary" data-prev>上一页</button><b></b><button type="button" class="btn secondary" data-next>下一页</button></div>';table.closest('.table-wrap').append(nav);
    const render=()=>{const start=(page-1)*size;rows.forEach((row,i)=>row.hidden=i<start||i>=start+size);nav.querySelector('span').textContent=`第 ${start+1}–${Math.min(start+size,rows.length)} 条，共 ${rows.length} 条`;nav.querySelector('b').textContent=`${page} / ${pages}`;nav.querySelector('[data-prev]').disabled=page===1;nav.querySelector('[data-next]').disabled=page===pages;};
    nav.querySelector('[data-prev]').addEventListener('click',()=>{page--;render();table.scrollIntoView({behavior:'smooth',block:'start'});});nav.querySelector('[data-next]').addEventListener('click',()=>{page++;render();table.scrollIntoView({behavior:'smooth',block:'start'});});render();
  });
}
