document.addEventListener('DOMContentLoaded', () => {
  initNotifications();
  initConditionOrdering();
  document.querySelectorAll('.session-qr-links').forEach(group => {
    const links = group.querySelectorAll('a');
    if (links[0]) links[0].textContent = '评分入口';
    if (links[1]) links[1].textContent = '评分码';
    if (links[2]) links[2].textContent = '问卷入口';
    if (links[3]) links[3].textContent = '问卷码';
  });
  document.querySelectorAll('.questionnaire-preview dl dd').forEach((value, index) => {
    if (index === 2) value.textContent = value.textContent.trim().slice(0, 10);
  });
  document.querySelectorAll('a[href*="page=interview_questionnaire"]').forEach(link => {
    const url = new URL(link.href, window.location.href);
    url.searchParams.set('standalone', '1');
    link.href = url.toString();
    link.target = '_blank';
    link.rel = 'noopener';
  });
  document.querySelectorAll('.interview-score-grid tbody tr').forEach(row => {
    const scoreId = row.querySelector('form[action*="score_reset"] input[name="score_id"]')?.value;
    const actions = row.lastElementChild;
    if (!scoreId || !actions || actions.querySelector('[data-review-jump]')) return;
    const link = document.createElement('a');
    link.className = 'table-action';
    link.dataset.reviewJump = '1';
    link.href = `/index.php?page=talent&tab=final&score_id=${encodeURIComponent(scoreId)}`;
    link.textContent = '去审核';
    actions.prepend(link);
  });
  if (document.querySelector('.interview-score-grid')) {
    document.querySelectorAll('.interview-score-grid form[action*="score_reset"]').forEach(form => {
      form.dataset.confirmMessage = '确认重置该条面试评分？当前面试官的评分记录将被清除，需要重新提交评分。';
    });
    fetch('/index.php?page=interview_results&action=review_states', { credentials: 'same-origin' })
      .then(response => response.ok ? response.json() : [])
      .then(rows => {
        const states = new Map(rows.map(row => [String(row.id), row.review_status]));
        document.querySelectorAll('[data-review-jump]').forEach(link => {
          const scoreId = new URL(link.href, window.location.href).searchParams.get('score_id');
          if (!['final_pass', 'final_reject'].includes(states.get(String(scoreId)))) return;
          const disabled = document.createElement('span');
          disabled.className = 'table-action is-disabled';
          disabled.textContent = '已审核';
          disabled.title = '该员工已完成终审';
          link.replaceWith(disabled);
        });
      }).catch(() => {});
  }
  document.querySelectorAll('form').forEach(form => { form.noValidate = true; });
  initFullMobileNumbers(); initLoginFields(); initSearchFields(); initPasswordToggles(); initSidebarGroups(); initSidebarScrollPosition(); initStandardRuleDefaults(); initFormValidation(); initConfirmations(); initInterviewRegistrationActions(); initInterviewRegistrationStatuses(); initAssessmentBulkRegistration(); initReviewActions(); initReviewStableScroll(); initInterviewStableScroll(); initInterviewSessionControls(); initInterviewSessionPagination(); initRegistrationPaginationFooter(); initInterviewRecommendationTags(); initTalentResumePreview(); initTalentEditLinks(); initStandardEditModals(); initGroupedStandardTiers(); initStandardCardLayout(); initStandardTabs(); initStandardDimensionSearch(); initStandardDimensionCreate(); initQuestionPaperBuilder(); initSearchablePostSelects(); initQuestionArchiveLink(); initPaperArchive(); initDirectQrActions(); initSelectedFields(); initQuestionEditorOptions(); initFormModals(); initPostDuplicateConfirmation(); initQrModals(); initTablePagination(); initQualityDetails(); initUserAccountActions(); initInterviewerProfileFields(); initAuditLog(); initStandardViewState();
});

function initConditionOrdering() {
  const panel = document.querySelector('#standard-conditions');
  const cards = [...(panel?.querySelectorAll('.dimension-score-card') || [])];
  if (!cards.length) return;
  const box = document.createElement('details'); box.className = 'condition-order panel';
  box.innerHTML = '<summary>调整基本条件显示顺序</summary><p>调整全部基本条件的顺序。拖动条目或使用上移、下移，保存后同步到答题页。</p><ol></ol><footer><button type="button" class="btn primary">保存顺序</button><span role="status" aria-live="polite"></span></footer>';
  const intro=panel.querySelector('.grouped-standard-intro');if(intro)intro.after(box);else panel.prepend(box);
  const list = box.querySelector('ol'), save = box.querySelector('footer button'), status = box.querySelector('[role=status]');
  const map = new Map(cards.map(card => [card.querySelector('[name=dim_code]').value, card]));
  let dragging = null, saving = false;
  const changed = () => { status.textContent = '顺序已调整，点击保存生效'; };
  const move = (li, direction) => {
    if (saving) return;
    const items=[...list.children].filter(item=>!item.hidden),index=items.indexOf(li);
    const sibling=items[index+direction];
    if (sibling) { direction < 0 ? list.insertBefore(li, sibling) : list.insertBefore(sibling, li); changed(); }
  };
  for (const [code, card] of map) {
    const li = document.createElement('li'); li.dataset.code = code; li.draggable = true;
    li.innerHTML = '<span class="condition-drag" aria-hidden="true">⠿</span><b></b><button type="button" data-up>上移</button><button type="button" data-down>下移</button>';
    const name = card.querySelector('h2').textContent; li.querySelector('b').textContent = name;
    li.querySelector('[data-up]').setAttribute('aria-label', '上移'+name);
    li.querySelector('[data-down]').setAttribute('aria-label', '下移'+name);
    li.querySelector('[data-up]').addEventListener('click', () => move(li, -1));
    li.querySelector('[data-down]').addEventListener('click', () => move(li, 1));
    li.addEventListener('dragstart', e => { if(saving){e.preventDefault();return;} dragging = li; e.dataTransfer.setData('text/plain', code); e.dataTransfer.effectAllowed = 'move'; li.classList.add('is-dragging'); });
    li.addEventListener('dragover', e => { if(!dragging||dragging===li||saving)return; e.preventDefault(); const after=e.clientY>li.getBoundingClientRect().top+li.offsetHeight/2; list.insertBefore(dragging,after?li.nextSibling:li); changed(); });
    li.addEventListener('drop', e => e.preventDefault());
    li.addEventListener('dragend', () => {li.classList.remove('is-dragging');dragging=null;});
    list.append(li);
  }
  save.addEventListener('click', async () => {
    if(saving)return; saving=true; save.disabled=true; box.querySelectorAll('li button').forEach(b=>b.disabled=true);status.textContent='正在保存…';
    const data=new URLSearchParams({csrf:cards[0].querySelector('[name=csrf]').value});
    const order=[...list.children].map(li=>li.dataset.code);order.forEach(code=>data.append('order[]',code));
    try{
      const response=await fetch('?page=standards&action=condition_order_save',{method:'POST',credentials:'same-origin',body:data});
      const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'保存失败，请重试');
      order.forEach(code=>panel.append(map.get(code)));status.textContent='';showNotification('顺序已保存，答题页将按此顺序显示');
    }catch(error){status.textContent='';showNotification(error.message||'网络异常，未保存，请重试','error');}
    finally{saving=false;save.disabled=false;box.querySelectorAll('li button').forEach(b=>b.disabled=false);}
  });
}

function initInterviewStableScroll() {
  if (new URLSearchParams(location.search).get('page') !== 'interviews') return;
  const key = 'interview-assignment-position';
  document.querySelectorAll('.assign-row form[action*="action=assign_interviewer"], .assign-row form[action*="action=assign_candidate"]').forEach(form => {
    form.addEventListener('submit', () => {
      const card = form.closest('.session-card');
      if (card?.id) sessionStorage.setItem(key, JSON.stringify({id: card.id, top: card.getBoundingClientRect().top}));
    });
  });
  const saved = sessionStorage.getItem(key); if (!saved) return;
  sessionStorage.removeItem(key);
  let position; try { position = JSON.parse(saved); } catch { return; }
  const restore = () => {
    const card = document.getElementById(position.id); if (!card) return;
    window.scrollBy({top: card.getBoundingClientRect().top - Number(position.top || 0), left: 0, behavior: 'auto'});
    if (location.hash) history.replaceState(null, '', location.pathname + location.search);
  };
  requestAnimationFrame(() => requestAnimationFrame(restore));
  setTimeout(restore, 160);
}

function initReviewStableScroll() {
  if (new URLSearchParams(location.search).get('page') !== 'talent') return;
  const key = 'talent-review-position';
  document.querySelectorAll('form.review-form input[name="stage"][value="final"]').forEach(stage => {
    stage.form?.addEventListener('submit', () => sessionStorage.setItem(key, String(window.scrollY)));
  });
  const saved = sessionStorage.getItem(key); if (saved === null) return;
  sessionStorage.removeItem(key);
  const top = Math.max(0, Number(saved) || 0);
  const restore = () => window.scrollTo({top, left: 0, behavior: 'auto'});
  requestAnimationFrame(() => requestAnimationFrame(restore));
  setTimeout(restore, 160);
}

function initFullMobileNumbers() {
  const mobiles = Array.isArray(window.__displayMobiles) ? window.__displayMobiles.map(String) : [];
  if (!mobiles.length) return;
  const resolve = (prefix, suffix) => mobiles.find(mobile => mobile.startsWith(prefix) && mobile.endsWith(suffix)) || '';
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const nodes = []; let node;
  while ((node = walker.nextNode())) nodes.push(node);
  nodes.forEach(textNode => {
    const value = textNode.nodeValue;
    if (!/\d{3}\s*\*{4}\s*\d{4}/.test(value)) return;
    textNode.nodeValue = value.replace(/(\d{3})\s*\*{4}\s*(\d{4})/g, (matched, prefix, suffix) => resolve(prefix, suffix) || matched);
  });
}

function initLoginFields() {
  const form = document.querySelector('form.login-card');
  if (!form) return;
  ['username', 'password'].forEach(name => { const field = form.elements[name]; if (field) field.value = ''; });
}

function initAuditLog() {
  if (new URLSearchParams(location.search).get('page') !== 'logs') return;
  const table = document.querySelector('.table-wrap table');
  if (!table) return;
  const actionLabels = {user_update:'编辑用户', user_delete:'删除用户', interviewer_assign:'分配面试官', candidate_assign:'分配候选人', interview_session_create:'创建面试场次', interview_score_reset:'重置面试评分', review_first:'人才初审', review_final:'人才终审'};
  const roleLabels = {admin:'系统管理员', hr:'人力资源审核员', leader:'分管领导', interviewer:'面试官'};
  const outcomeLabels = {first_pass:'初审通过', first_reject:'初审不通过', final_pass:'终审录用', final_reject:'终审不录用'};
  const targetLabels = {user:'用户账号', result:'测评结果', session:'面试场次', score:'面试评分', detail:'答题明细', registration:'测评登记'};
  const headers = table.tHead?.rows[0]?.cells;
  if (headers) { if (headers[3]) headers[3].textContent = '关联记录'; if (headers[4]) headers[4].textContent = '操作说明'; }
  const labelTarget = value => { const [type, id] = String(value || '').split(':'); return targetLabels[type] && id ? `${targetLabels[type]} #${id}` : (value || '—'); };
  const labelDetail = value => {
    if (!value || value === '[]' || value === '{}') return '无附加信息';
    try {
      const detail = JSON.parse(value); const parts = [];
      if (detail.role) parts.push(`角色调整为${roleLabels[detail.role] || detail.role}`);
      if (detail.password_changed) parts.push('已重置密码');
      if (detail.op) parts.push(`审核结论：${outcomeLabels[detail.op] || detail.op}`);
      if (detail.registration_id) parts.push(`来源测评登记 #${detail.registration_id}`);
      if (detail.user_id) parts.push(`关联用户 #${detail.user_id}`);
      if (detail.post_id) parts.push(`关联岗位 #${detail.post_id}`);
      if (detail.score !== undefined) parts.push(`评分：${detail.score}`);
      return parts.join('；') || '已记录操作详情';
    } catch (_) { return value; }
  };
  [...table.tBodies[0].rows].forEach(row => {
    if (row.cells.length < 6) return;
    const action = row.cells[2].textContent.trim(); row.cells[2].textContent = actionLabels[action] || action;
    row.cells[3].textContent = labelTarget(row.cells[3].textContent.trim());
    row.cells[4].textContent = labelDetail(row.cells[4].textContent.trim());
  });
}

const focusables = 'a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
let activeOverlay = null, previousFocus = null;

function openDialog(overlay, preferred) {
  previousFocus = document.activeElement; activeOverlay = overlay;
  overlay.classList.add('open'); overlay.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modal-open'); document.querySelector('.shell')?.setAttribute('inert', '');
  requestAnimationFrame(() => (preferred || overlay.querySelector(focusables))?.focus({preventScroll:true}));
}
function closeDialog(overlay) {
  overlay.classList.remove('open'); overlay.setAttribute('aria-hidden', 'true');
  if (!document.querySelector('.modal-overlay.open')) { document.body.classList.remove('modal-open'); document.querySelector('.shell')?.removeAttribute('inert'); }
  activeOverlay = document.querySelector('.modal-overlay.open'); previousFocus?.focus({preventScroll:true});
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

function initSidebarGroups() {
  const nav = document.querySelector('.sidebar nav'); if (!nav) return;
  [...nav.querySelectorAll(':scope > label')].forEach((label, index) => {
    const group = document.createElement('section'); group.className = 'nav-group';
    const button = document.createElement('button'); button.type = 'button'; button.className = 'nav-group-toggle';
    button.innerHTML = `<span>${label.textContent}</span><i aria-hidden="true">⌄</i>`;
    label.replaceWith(group); group.append(button);
    let item = group.nextElementSibling;
    while (item?.matches('a')) { const next = item.nextElementSibling; group.append(item); item = next; }
    const key = `sidebar-group-${index}`;
    const stored = sessionStorage.getItem(key);
    const open = stored === null ? (index === 0 || !!group.querySelector('a.active')) : stored === 'open';
    group.classList.toggle('is-collapsed', !open); button.setAttribute('aria-expanded', String(open));
    button.addEventListener('click', () => {
      const collapsed = group.classList.toggle('is-collapsed');
      button.setAttribute('aria-expanded', String(!collapsed)); sessionStorage.setItem(key, collapsed ? 'closed' : 'open');
    });
  });
}

function initSidebarScrollPosition() {
  const sidebar = document.querySelector('.sidebar');
  const nav = sidebar?.querySelector('nav');
  if (!nav) return;
  const key = 'sidebar-scroll-position';
  const remember = () => {
    try { sessionStorage.setItem(key, JSON.stringify({nav: nav.scrollTop, sidebar: sidebar.scrollTop})); } catch {}
  };
  // Restore after menu groups are built, so their collapsed heights are final.
  try {
    const saved = JSON.parse(sessionStorage.getItem(key));
    if (saved) {
      if (Number.isFinite(saved.nav)) nav.scrollTop = saved.nav;
      if (Number.isFinite(saved.sidebar)) sidebar.scrollTop = saved.sidebar;
    }
  } catch {}
  nav.addEventListener('scroll', remember, {passive: true});
  sidebar.addEventListener('scroll', remember, {passive: true});
  sidebar.addEventListener('click', remember, true);
  window.addEventListener('pagehide', remember);
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
      const isLogin = form.classList.contains('login-card');
      if (isLogin) {
        invalid.forEach(field => field.setAttribute('aria-invalid', 'true'));
        const notice = document.createElement('div'); notice.className = 'form-error login-validation'; notice.setAttribute('role', 'alert');
        notice.textContent = invalid.length > 1 ? '请输入登录账号和密码后再继续。' : '请填写登录信息后再继续。';
        form.querySelector('.login-card-head')?.insertAdjacentElement('afterend', notice);
        invalid[0].focus({preventScroll: true}); return;
      }
      invalid.forEach((field, i) => {
        const id = field.id || `field-${Date.now()}-${i}`; field.id = id; field.setAttribute('aria-invalid', 'true');
        const msg = document.createElement('small'); msg.className = 'field-error'; msg.id = `${id}-error`;
        msg.textContent = field.validity.valueMissing ? '请填写此项后再继续。' : '填写格式不正确，请检查后重试。';
        field.setAttribute('aria-describedby', msg.id); field.insertAdjacentElement('afterend', msg);
      }); if (new URLSearchParams(location.search).get('page') === 'standards') showNotification('请检查标出的必填项或填写格式。','error'); invalid[0].focus(); return;
    }
    const btn = e.submitter;
    if (form.querySelector('[data-save-continue]')) {
      let intent = form.querySelector('input[name="save_continue"]');
      if (!intent) { intent = document.createElement('input'); intent.type = 'hidden'; intent.name = 'save_continue'; form.append(intent); }
      intent.value = btn?.hasAttribute('data-save-continue') ? '1' : '0';
    }
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
  document.querySelectorAll('form[data-confirm-message],form:has(button[data-confirm-message])').forEach(form => {
    form.addEventListener('submit', e => {
      const description = e.submitter?.dataset.confirmMessage || form.dataset.confirmMessage;
      if (!description || form.dataset.confirmed === '1') return; e.preventDefault(); e.stopImmediatePropagation();
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

function initInterviewRegistrationStatuses() {
  document.querySelectorAll('form[action*="action=interview_pre_update"]').forEach(form => {
    const row = form.closest('tr');
    if (row?.querySelector('.badge')?.textContent.trim() === '已答题') form.remove();
  });
  document.querySelectorAll('.table-wrap tr').forEach(row => {
    if (row.querySelector('.badge')?.textContent.trim() !== '已答题') return;
    const cell = row.cells?.[5]; if (cell && !cell.querySelector('form')) cell.textContent = '测评已完成';
  });
}

function initUserAccountActions() {
  const currentName = document.querySelector('.account b')?.textContent?.trim();
  if (new URLSearchParams(location.search).get('page') === 'interviewers') document.querySelector('[data-user-modal-open="password"]')?.remove();
  document.querySelectorAll('.user-table-panel tbody tr').forEach(row => {
    const marker = row.querySelector('.muted');
    if (marker?.textContent.trim() === '当前账号' && currentName && row.cells?.[0]?.textContent.trim() !== currentName) marker.textContent = '仅系统管理员可管理';
  });
  const open = modal => { if (!modal) return; modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('modal-open'); modal.querySelector('input:not([type="hidden"])')?.focus(); };
  const close = modal => { if (!modal) return; modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); if (!document.querySelector('.modal-overlay.open')) document.body.classList.remove('modal-open'); };
  document.querySelectorAll('[data-user-modal-open]').forEach(button => button.addEventListener('click', () => open(document.querySelector(`#user-${button.dataset.userModalOpen}-modal`))));
  document.querySelectorAll('[data-user-modal-close]').forEach(button => button.addEventListener('click', () => close(button.closest('.user-modal'))));
  document.querySelectorAll('.user-modal').forEach(modal => modal.addEventListener('click', event => { if (event.target === modal) close(modal); }));
  const editModal = document.querySelector('#user-edit-modal'); const editForm = editModal?.querySelector('form[action*="action=user_update"]');
  document.querySelectorAll('[data-user-edit]').forEach(button => button.addEventListener('click', () => {
    if (!editForm) return;
    try {
      const user = JSON.parse(button.dataset.userEdit || '{}');
      ['id', 'real_name', 'username', 'mobile', 'role'].forEach(name => { const field = editForm.elements[name]; if (field) field.value = user[name] ?? ''; });
      if (editForm.elements.password) editForm.elements.password.value = '';
      open(editModal);
    } catch (_) { window.location.reload(); }
  }));
}

function initInterviewerProfileFields() {
  if (new URLSearchParams(location.search).get('page') !== 'interviewers') return;

  const endpoint = '?page=interviewers&action=interviewer_detail&id=';
  const pageDescription = document.querySelector('.page-head p');
  if (pageDescription) pageDescription.textContent = '集中维护参与面试评分的人员资料；人力资源审核员仅可管理面试官。';
  const toolbarTitle = document.querySelector('.user-table-toolbar b');
  const toolbarHint = document.querySelector('.user-table-toolbar small');
  if (toolbarTitle) toolbarTitle.textContent = '面试官名单';
  if (toolbarHint) toolbarHint.textContent = toolbarHint.textContent.replace(/可编辑、删除和重置面试官登录密码/, '可新增、编辑、停用或删除面试官');
  const toolbar = document.querySelector('.user-table-toolbar');
  const interviewerRows = [...document.querySelectorAll('.user-table-panel tbody tr')];
  if (toolbar && interviewerRows.length && !toolbar.querySelector('.interviewer-search')) {
    const search = document.createElement('label');
    search.className = 'interviewer-search';
    search.innerHTML = '<span class="sr-only">搜索面试官姓名</span><input type="search" placeholder="搜索面试官姓名" aria-label="搜索面试官姓名"><button type="button" class="btn primary">搜索</button><button type="button" class="btn secondary">重置</button>';
    const input = search.querySelector('input');
    const empty = document.createElement('p');
    empty.className = 'interviewer-search-empty';
    empty.textContent = '未找到匹配的面试官';
    empty.hidden = true;
    toolbar.append(search);
    toolbar.insertAdjacentElement('afterend', empty);
    const runSearch = () => {
      const keyword = input.value.trim();
      let visible = 0;
      interviewerRows.forEach(row => {
        const matched = !keyword || row.cells?.[0]?.textContent.includes(keyword);
        row.hidden = !matched;
        if (matched) visible++;
      });
      empty.hidden = visible > 0;
    };
    search.querySelector('button').addEventListener('click', runSearch);
    search.querySelector('button.secondary').addEventListener('click', () => { input.value = ''; runSearch(); input.focus(); });
    input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); runSearch(); } });
  }
  const configureForm = (form, action) => {
    if (!form) return;
    form.setAttribute('action', action);
    form.querySelectorAll('label').forEach(label => {
      const text = label.childNodes[0]?.textContent?.trim() || '';
      if (/^(登录账号|初始密码|新密码|角色)/.test(text)) label.remove();
    });
    const submit = form.querySelector('button.btn');
    [['部门/公司', 'company'], ['岗位', 'job_title']].forEach(([title, name]) => {
      if (form.elements[name]) return;
      const label = document.createElement('label');
      label.textContent = title;
      const input = document.createElement('input');
      input.name = name;
      input.required = true;
      label.append(input);
      if (submit) form.insertBefore(label, submit); else form.append(label);
    });
  };

  const createForm = document.querySelector('#user-create-modal form');
  const editForm = document.querySelector('#user-edit-modal form');
  configureForm(createForm, '?page=interviewers&action=user_save_interviewer');
  configureForm(editForm, '?page=interviewers&action=user_update_interviewer');

  const table = document.querySelector('.user-table-panel table');
  if (table) {
    const headers = table.querySelectorAll('thead th');
    if (headers[1]) headers[1].textContent = '部门/公司';
    if (headers[3]) headers[3].textContent = '岗位';
  }
  document.querySelectorAll('[data-user-edit]').forEach(button => {
    button.addEventListener('click', async () => {
      try {
        const user = JSON.parse(button.dataset.userEdit || '{}');
        const profile = await fetch(endpoint + encodeURIComponent(user.id), { credentials: 'same-origin' }).then(response => response.json());
        if (editForm) {
          if (editForm.elements.company) editForm.elements.company.value = profile.company || '';
          if (editForm.elements.job_title) editForm.elements.job_title.value = profile.job_title || '';
        }
        const row = button.closest('tr');
        if (row?.cells) {
          if (row.cells[1]) row.cells[1].textContent = profile.company || '—';
          if (row.cells[3]) row.cells[3].textContent = profile.job_title || '—';
        }
      } catch (_) { /* 保持已打开的编辑窗口，用户可重新填写资料。 */ }
    });
  });
  document.querySelectorAll('.user-table-panel tbody tr').forEach(async row => {
    const button = row.querySelector('[data-user-edit]');
    if (!button) return;
    try {
      const user = JSON.parse(button.dataset.userEdit || '{}');
      const profile = await fetch(endpoint + encodeURIComponent(user.id), { credentials: 'same-origin' }).then(response => response.json());
      if (row.cells?.[1]) row.cells[1].textContent = profile.company || '—';
      if (row.cells?.[3]) row.cells[3].textContent = profile.job_title || '—';
    } catch (_) { /* 旧数据没有补充资料时保留空占位。 */ }
  });
}

function initAssessmentBulkRegistration() {
  const form = document.querySelector('form[action*="action=interview_pre_save"]');
  const select = form?.querySelector('select[name="candidate_id"]');
  if (!form || !select || select.dataset.bulkReady === '1') return;
  select.dataset.bulkReady = '1';
  const selectedId = String(select.dataset.selected || select.value || '');
  const label = select.closest('label');
  [...(label?.childNodes || [])].filter(node => node.nodeType === Node.TEXT_NODE).forEach(node => node.remove());
  const title = document.createElement('span'); title.className = 'bulk-candidate-title'; title.textContent = '选择人才（可多选）';
  const hint = document.createElement('small'); hint.className = 'bulk-candidate-hint'; hint.textContent = '可搜索人才；勾选后将为所有人员登记同一个测评岗位';
  const postSelect = form.querySelector('select[name="post_id"]');
  const postLabel = postSelect?.closest('label');
  if (postLabel && label) label.before(postLabel);
  const scopeTools = document.createElement('div'); scopeTools.className = 'bulk-candidate-scope';
  const matchScope = document.createElement('button'); matchScope.type = 'button'; matchScope.textContent = '意向职位匹配';
  const allScope = document.createElement('button'); allScope.type = 'button'; allScope.textContent = '全部人员';
  scopeTools.append(matchScope, allScope);
  const toolbar = document.createElement('div'); toolbar.className = 'bulk-candidate-tools';
  const search = document.createElement('input'); search.type = 'search'; search.placeholder = '搜索姓名、手机号或专业'; search.setAttribute('aria-label', '搜索人才');
  const selectPage = document.createElement('button'); selectPage.type = 'button'; selectPage.className = 'table-action'; selectPage.textContent = '全选当前结果';
  const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'table-action'; clear.textContent = '清空选择';
  toolbar.append(search, selectPage, clear);
  const list = document.createElement('div'); list.className = 'bulk-candidate-list';
  const footer = document.createElement('div'); footer.className = 'bulk-candidate-footer';
  const summary = document.createElement('small'); const more = document.createElement('button'); more.type = 'button'; more.className = 'table-action'; more.textContent = '加载更多'; more.hidden = true; footer.append(summary, more);
  const chosen = new Map(); if (selectedId) { const option = [...select.options].find(item => item.value === selectedId); if (option) chosen.set(selectedId, option.textContent); }
  let page = 1, hasMore = false, timer, scope = 'match';
  const syncScope = () => { matchScope.classList.toggle('is-active', scope === 'match'); allScope.classList.toggle('is-active', scope === 'all'); };
  const syncSummary = () => { summary.textContent = `已选择 ${chosen.size} 人${scope === 'match' ? ' · 仅意向职位匹配' : ' · 显示全部人员'}`; };
  const render = items => { list.replaceChildren(); if (!items.length) { list.innerHTML = '<span class="bulk-candidate-empty">未找到可登记人才</span>'; return; } items.forEach(person => { const item = document.createElement('label'); item.className = 'bulk-candidate-item'; const input = document.createElement('input'); input.type = 'checkbox'; input.value = String(person.id); input.checked = chosen.has(String(person.id)); const text = document.createElement('span'); text.textContent = `${person.name} · ${person.mobile} · ${person.major || '专业待补充'}`; input.addEventListener('change', () => { if (input.checked) chosen.set(input.value, text.textContent); else chosen.delete(input.value); syncSummary(); }); item.append(input, text); list.append(item); }); };
  const load = (next = false) => { if (!next) page = 1; const params = new URLSearchParams({q: search.value.trim(), p: String(page), post_id: String(postSelect?.value || ''), scope}); fetch(`?page=preregister&action=assessment_candidates&${params}`, {credentials: 'same-origin'}).then(response => response.ok ? response.json() : {items: [], has_more: false}).then(data => { const previous = next ? [...list.children] : []; render(data.items || []); if (next) list.prepend(...previous); hasMore = !!data.has_more; more.hidden = !hasMore; syncScope(); syncSummary(); }).catch(() => { list.innerHTML = '<span class="bulk-candidate-empty">人才列表加载失败，请刷新后重试</span>'; }); };
  selectPage.addEventListener('click', () => { list.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = true; const text = input.parentElement?.querySelector('span')?.textContent || ''; chosen.set(input.value, text); }); syncSummary(); });
  clear.addEventListener('click', () => { chosen.clear(); list.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = false; }); syncSummary(); });
  matchScope.addEventListener('click', () => { scope = 'match'; load(false); });
  allScope.addEventListener('click', () => { scope = 'all'; load(false); });
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(false), 180); });
  postSelect?.addEventListener('change', () => { scope = 'match'; load(false); });
  more.addEventListener('click', () => { if (!hasMore) return; page += 1; load(true); });
  form.addEventListener('submit', () => { form.querySelectorAll('input[name="candidate_ids[]"]').forEach(input => input.remove()); chosen.forEach((text, id) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'candidate_ids[]'; input.value = id; form.append(input); }); });
  select.remove();
  label?.prepend(title); label?.append(hint, scopeTools, toolbar, list, footer); load(false);
  const button = form.querySelector('button.btn.primary'); if (button) button.textContent = '确认批量测评登记';
}

function initReviewActions() {
  document.querySelectorAll('form.review-form').forEach(form => {
    let operation = form.querySelector('input.review-operation');
    if (!operation) {
      operation = document.createElement('input');
      operation.type = 'hidden'; operation.name = 'op'; operation.className = 'review-operation';
      form.append(operation);
    }
    form.querySelectorAll('button[name="op"]').forEach(button => button.addEventListener('click', () => {
      operation.value = button.value;
    }));
    form.addEventListener('submit', event => {
      if (!operation.value && event.submitter?.name === 'op') operation.value = event.submitter.value;
    });
  });
}

function initInterviewSessionControls() {
  document.querySelectorAll('.session-members > div').forEach(group => {
    if (!group.querySelector('span')) {
      const empty = document.createElement('small');
      empty.className = 'session-empty';
      empty.textContent = '暂未分配';
      group.append(empty);
    }
  });
  document.querySelectorAll('.session-state form[action*="action=session_status"]').forEach(form => {
    const host = form.closest('.session-state'); const id = form.querySelector('input[name="id"]')?.value;
    if (host && id) {
      host.dataset.sessionId = id;
      const csrf = form.querySelector('input[name="csrf"]')?.value;
      const isPending = host.querySelector('.badge')?.textContent.trim() === '待处理';
      if (isPending && csrf && !host.querySelector('.session-cancel')) {
        const cancel = document.createElement('form');
        cancel.method = 'post'; cancel.action = '?page=interviews&action=session_cancel'; cancel.className = 'session-cancel';
        cancel.innerHTML = `<input type="hidden" name="csrf" value="${csrf}"><input type="hidden" name="id" value="${id}"><button type="submit">取消场次</button>`;
        cancel.addEventListener('submit', event => {
          if (cancel.dataset.confirmed === '1') return;
          event.preventDefault();
          showConfirm({
            title: '取消面试场次', description: '取消后无法恢复，确认继续？', actionLabel: '确认取消',
            onConfirm: () => { cancel.dataset.confirmed = '1'; cancel.requestSubmit(); }
          });
        });
        host.append(cancel);
      }
    }
    form.remove();
  });
  document.querySelectorAll('.session-state form[action*="action=session_rotate"]').forEach(form => form.remove());
  document.querySelectorAll('.session-card').forEach(card => {
    const state = card.querySelector('.session-state .badge')?.textContent.trim();
    if (!['已完成', '已取消'].includes(state)) return;
    card.classList.add('is-closed');
    card.querySelectorAll('select, input:not([type="hidden"]), button').forEach(control => {
      control.disabled = true;
      control.title = state === '已取消' ? '该面试场次已取消，不能再修改' : '该面试场次已完成，不能再修改';
    });
  });
  fetch('?page=interviews&action=session_progress', {credentials: 'same-origin'})
    .then(response => response.ok ? response.json() : [])
    .then(rows => rows.forEach(progress => {
      const host = document.querySelector(`.session-state[data-session-id="${progress.id}"]`); if (!host) return;
      const expected = Number(progress.interviewer_count) * Number(progress.candidate_count);
      const complete = expected > 0 && Number(progress.completed_count) >= expected;
      const status = document.createElement('small'); status.className = `session-progress${complete ? ' is-complete' : ''}`;
      status.textContent = expected ? (complete ? `评分已全部完成（${progress.completed_count} / ${expected}）` : `评分进度：${progress.completed_count} / ${expected}`) : '请先分配面试官和候选人';
      host.prepend(status);
    }))
    .catch(() => {});
  const candidateForms = [...document.querySelectorAll('.assign-row form[action*="action=assign_candidate"]')];
  if (!candidateForms.length) return;
  candidateForms.forEach(form => form.action = '?page=interviews&action=assign_candidate_v2');
  fetch('?page=interviews&action=eligible_candidates', {credentials:'same-origin'})
    .then(response => response.ok ? response.json() : [])
    .then(rows => candidateForms.forEach(form => {
      const select = form.querySelector('select[name="answer_id"]'); if (!select) return;
      select.replaceChildren();
      if (!rows.length) { const option = new Option('暂无已答题且已推荐面试的候选人', ''); option.disabled = true; option.selected = true; select.append(option); return; }
      rows.forEach(row => select.append(new Option(`${row.name} · ${row.post_name}`, row.id)));
    })).catch(() => {});
}

function initInterviewSessionPagination() {
  const list = document.querySelector('.session-list');
  if (!list) return;
  const cards = [...list.querySelectorAll(':scope > .session-card')], size = 6;
  if (cards.length <= size) return;
  const pages = Math.ceil(cards.length / size);
  const params = new URLSearchParams(location.search);
  let page = Math.min(Math.max(1, Number(params.get('sp')) || 1), pages);
  const targetId = decodeURIComponent(location.hash.slice(1));
  const targetIndex = cards.findIndex(card => card.id === targetId);
  if (targetIndex >= 0) page = Math.floor(targetIndex / size) + 1;
  const nav = document.createElement('nav'); nav.className = 'table-pagination session-pagination'; nav.setAttribute('aria-label', '面试场次分页');
  nav.innerHTML = '<span></span><div><button type="button" class="btn secondary" data-prev>上一页</button><b></b><button type="button" class="btn secondary" data-next>下一页</button></div>';
  list.insertAdjacentElement('afterend', nav);
  const render = () => {
    const start = (page - 1) * size;
    cards.forEach((card, index) => { card.hidden = index < start || index >= start + size; });
    nav.querySelector('span').textContent = `第 ${start + 1}–${Math.min(start + size, cards.length)} 个场次，共 ${cards.length} 个`;
    nav.querySelector('b').textContent = `${page} / ${pages}`;
    nav.querySelector('[data-prev]').disabled = page === 1; nav.querySelector('[data-next]').disabled = page === pages;
    const next = new URLSearchParams(location.search); if (page === 1) next.delete('sp'); else next.set('sp', String(page));
    history.replaceState(null, '', `${location.pathname}${next.size ? `?${next}` : ''}${location.hash}`);
  };
  nav.querySelector('[data-prev]').addEventListener('click', () => { page--; render(); list.scrollIntoView({behavior: 'smooth', block: 'start'}); });
  nav.querySelector('[data-next]').addEventListener('click', () => { page++; render(); list.scrollIntoView({behavior: 'smooth', block: 'start'}); });
  render();
  if (targetIndex >= 0) requestAnimationFrame(() => cards[targetIndex].scrollIntoView({block: 'center'}));
}

function initRegistrationPaginationFooter() {
  if (new URLSearchParams(location.search).get('page') !== 'preregister') return;
  const actions = document.querySelector('.page-actions');
  const summary = actions?.querySelector('.pagination-summary');
  const table = document.querySelector('.table-wrap');
  if (!summary || !table) return;
  const pager = document.createElement('nav'); pager.className = 'table-pagination'; pager.setAttribute('aria-label', '测评登记分页');
  const controls = document.createElement('div');
  pager.append(summary, controls);
  [...actions.querySelectorAll('a.btn')].filter(link => /上一页|下一页/.test(link.textContent)).forEach(link => controls.append(link));
  table.append(pager);
}

function initInterviewRecommendationTags() {
  if (new URLSearchParams(location.search).get('page') !== 'interview_results') return;
  document.querySelectorAll('.table-wrap table').forEach(table => {
    const headers = [...table.querySelectorAll('thead th')];
    const index = headers.findIndex(header => header.textContent.trim() === '录用建议');
    if (index < 0) return;
    table.querySelectorAll('tbody tr').forEach(row => {
      const cell = row.cells[index]; if (!cell || cell.querySelector('.recommendation-tag')) return;
      const value = cell.textContent.trim(); if (!value) return;
      const tag = document.createElement('span'); tag.className = 'recommendation-tag';
      if (value.includes('不推荐')) tag.classList.add('is-reject');
      else if (value.includes('待定') || value.includes('复试')) tag.classList.add('is-pending');
      else tag.classList.add('is-recommend');
      tag.textContent = value; cell.replaceChildren(tag);
    });
  });
}

function initTalentResumePreview() {
  document.querySelectorAll('.talent-card .resume-preview, .final-profile-actions .resume-preview').forEach(link => { link.addEventListener('click', async event => {
    event.preventDefault();
    const candidateId = new URL(link.href).searchParams.get('resume_id');
    if (!candidateId) return;
    try {
      const response = await fetch(`?page=talent_pool&resume_id=${encodeURIComponent(candidateId)}`, {credentials:'same-origin'});
      if (!response.ok) throw new Error();
      const resume = await response.json(); if (resume.error) throw new Error();
      showTalentResume(resume);
    } catch { location.href = link.href; }
  }); });
}

function initTalentEditLinks() {
  document.querySelectorAll('.talent-card .resume-preview').forEach(link => {
    const id = new URL(link.href).searchParams.get('resume_id'); if (!id || link.parentElement?.querySelector('.talent-edit')) return;
    const edit = document.createElement('a'); edit.className = 'talent-edit'; edit.href = `?page=talent_pool&edit=${encodeURIComponent(id)}`; edit.textContent = '编辑档案'; link.insertAdjacentElement('afterend', edit);
  });
}

function showTalentResume(resume) {
  const score = value => Number(value || 0).toFixed(1);
  const age = resume.age !== null && resume.age !== undefined ? Number(resume.age) : resume.birth_date ? Math.max(0, new Date().getFullYear() - Number(String(resume.birth_date).slice(0, 4))) : '—';
  const overlay = document.createElement('div'); overlay.className = 'modal-overlay resume-overlay'; overlay.setAttribute('aria-hidden', 'true');
  overlay.innerHTML = '<section class="modal-dialog resume-dialog" role="dialog" aria-modal="true" aria-labelledby="resume-title"><div class="modal-top"><div><h2 id="resume-title"></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="关闭预览">×</button></div><div class="resume-body"><header class="resume-hero"><div><span></span><small></small></div><strong><em></em><small>综合评分</small></strong></header><section class="resume-score-grid"></section><section class="resume-section"><h3>基本资料</h3><div class="resume-facts basic"></div></section><section class="resume-section"><h3>教育与职业</h3><div class="resume-facts career"></div></section><section class="resume-section"><h3>履历与能力</h3><div class="resume-facts ability"></div></section></div></section>';
  const text = (selector, value) => { overlay.querySelector(selector).textContent = value || '—'; };
  text('#resume-title', `${resume.name || '人才'}的简历`); text('.resume-hero span', `应聘岗位：${resume.post_name || '—'}`); text('.resume-hero small', resume.mobile || '—'); text('.resume-hero strong em', score(resume.total_score));
  const hasValue = value => value !== null && value !== undefined && String(value).trim() !== '' && String(value).trim() !== '—';
  const years = value => Number(value) > 0 ? `${value} 年` : null;
  const makeFacts = (target, items) => {
    const container = overlay.querySelector(target);
    items.filter(([,value]) => hasValue(value)).forEach(([label, value]) => {
      const item = document.createElement('div'), name = document.createElement('span'), detail = document.createElement('b');
      name.textContent = label; detail.textContent = value;
      if (String(value).length > 30 || String(value).includes('\n')) item.classList.add('resume-fact-wide');
      item.append(name, detail); container.append(item);
    });
    if (!container.children.length) container.closest('.resume-section')?.remove();
  };
  makeFacts('.resume-score-grid', [['基本条件', `${score(resume.eval_score)} / 50`], ['基本素质', `${score(resume.suzhi_score)} / 25`], ['专业能力', `${score(resume.postq_score)} / 25`]]);
  makeFacts('.resume-facts.basic', [['性别', resume.gender], ['年龄', Number(age) > 0 ? `${age} 岁` : null], ['出生日期', resume.birth_date], ['健康状况', resume.health], ['政治面貌', resume.politics]]);
  makeFacts('.resume-facts.career', [['学历', resume.edu], ['毕业学校', resume.school_name], ['院校层次', resume.school_tier], ['所学专业', resume.major], ['意向职位', resume.intent_post_name], ['职称', resume.title], ...(Number(resume.work_years)>0 ? [['工作年限', `${resume.work_years} 年`]] : []), ['从事专业年限', years(resume.prof_years)]]);
  makeFacts('.resume-facts.ability', [['集团公司工作年限', years(resume.group_co_years)], ['上市公司工作年限', years(resume.listed_co_years)], ['非上市公司工作年限', years(resume.private_co_years)], ['工作背景', resume.work_bg], ['计算机能力', resume.computer_skill], ['外语能力', resume.language]]);
  document.body.append(overlay);
  const close = () => { closeDialog(overlay); setTimeout(() => overlay.remove(), 180); };
  overlay.querySelector('[data-dialog-close]').addEventListener('click', close); overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
  openDialog(overlay, overlay.querySelector('[data-dialog-close]'));
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
      const close = () => { closeDialog(overlay); setTimeout(() => overlay.remove(),180); }; form.querySelector('[data-question-cancel]')?.addEventListener('click', close);
    top.querySelector('[data-dialog-close]').addEventListener('click', close); overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
      source.addEventListener('submit', () => { const tier=source.querySelector('[name="tier_label"]'),value=source.querySelector('[name="rule_value"]'),type=source.querySelector('[name="match_type"]'); if(type?.value!=='range'&&!value?.value.trim())value.value=tier?.value.trim()||''; });
      openDialog(overlay, source.querySelector('select, input, textarea'));
    } catch { location.href = link.href; }
  }));
}

function initGroupedStandardTiers() {
  document.querySelectorAll('form.dimension-score-card').forEach(form => {
    const list = form.querySelector('.dimension-score-list'), footer = form.querySelector('footer'); if (!list || !footer) return;
    form.querySelector('[data-dimension-name-edit]')?.addEventListener('click', event => {
      const editor=form.querySelector('.dimension-name-editor'), input=editor.querySelector('input');
      const opening=editor.hidden;editor.hidden=!opening;input.disabled=!opening;
      event.currentTarget.textContent=opening?'取消编辑':'编辑';
      event.currentTarget.setAttribute('aria-expanded',String(opening));
      if(opening){input.focus();input.select();}else input.value=input.defaultValue;
    });
    const saveButton = footer.querySelector('.btn.primary'); if (saveButton) saveButton.textContent = '保存';
    if ((form.querySelector('[name="dim_code"]')?.value || '').startsWith('custom_')) form.action='?page=standards&action=standard_custom_group_save';
    form.querySelector('button[name="op"][value="publish"]')?.setAttribute('formaction','?page=standards&action=standard_dimension_publish');
    const actions = form.querySelector('.dimension-actions');
    if (actions?.querySelector('.badge.green') && !actions.querySelector('[data-disable-dimension]')) { const disable = document.createElement('button'); disable.type='submit'; disable.className='btn secondary disable-dimension'; disable.textContent='停用'; disable.dataset.disableDimension='1'; disable.setAttribute('formaction','?page=standards&action=standard_dimension_disable'); actions.append(disable); }
    const numericDimension = form.dataset.numeric === '1'; const add = document.createElement('button'); add.type='button'; add.className='btn secondary add-tier'; add.textContent=numericDimension?'＋ 新增数值区间':'＋ 新增评分档位'; footer.prepend(add);
    list.querySelectorAll('.dimension-score-row').forEach(row => addRemove(row, false));
    list.querySelectorAll('[data-tier-edit]').forEach(button=>button.addEventListener('click',()=>{
      const fields=button.closest('.tier-caption').querySelector('.tier-edit-fields'),opening=fields.hidden;fields.hidden=!opening;
      fields.querySelectorAll('input').forEach(input=>{input.disabled=!opening;if(!opening)input.value=input.defaultValue;});
      button.textContent=opening?'取消编辑':'编辑';button.setAttribute('aria-expanded',String(opening));if(opening)fields.querySelector('input').focus();
    }));
    let followupIndex = 0;
    const bindFollowup = row => {
      const details = row.querySelector('.tier-followup'); if (!details) return;
      const panel=details.querySelector('div'),text=panel.querySelector('textarea');
      const toggle=document.createElement('button');toggle.type='button';toggle.className='tier-followup';toggle.textContent=text.value.trim()?'已设追问':'＋追问';toggle.setAttribute('aria-expanded','false');
      panel.className='tier-followup-panel';panel.hidden=true;details.replaceWith(toggle,panel);
      toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;toggle.setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden)text.focus({preventScroll:true});});
      text.addEventListener('input', () => { toggle.textContent = text.value.trim() ? '已设追问' : '＋追问'; });
    };
    list.querySelectorAll('.dimension-score-row').forEach(bindFollowup);

    add.addEventListener('click', () => { const row=document.createElement('div'); row.className='dimension-score-row new-tier-row'; row.innerHTML=numericDimension?'<div class="range-inputs"><label class="range-title">档位标题（可自定义）<input name="new_label[]" placeholder="填写区间后自动生成"></label><label>最小值<input name="new_min[]" type="number" required></label><label>最大值（不含）<input name="new_max[]" type="number" required></label></div><label>分值<input name="new_value[]" type="number" min="0" step="0.01" value="0"></label>':'<div><label>答案或条件<input name="new_label[]" placeholder="例如：优秀（未填写则不新增）"></label></div><label>分值<input name="new_value[]" type="number" min="0" step="0.01" value="0"></label>'; const index=followupIndex++;const followup=document.createElement('details');followup.className='tier-followup';followup.innerHTML=`<summary>＋追问</summary><div><label>追问内容 <small>选择此项后显示，不计分；留空则取消</small><textarea name="new_text_question[${index}]" rows="2" maxlength="500" placeholder="请输入追问内容"></textarea></label><label class="tier-followup-required"><input type="checkbox" name="new_text_required[${index}]" value="1"> 必填</label></div>`;row.append(followup);row.dataset.followupIndex=String(index);list.append(row);bindFollowup(row); addRemove(row, true); row.querySelector('input')?.focus(); });
    form.addEventListener('submit', () => { list.querySelectorAll('.new-tier-row').forEach((row,index) => { const text=row.querySelector('.tier-followup-panel textarea'), required=row.querySelector('.tier-followup-panel input'); if(text)text.name=`new_text_question[${index}]`;if(required)required.name=`new_text_required[${index}]`; }); });
    if (numericDimension) form.addEventListener('submit', () => { const code=form.querySelector('[name="dim_code"]')?.value; const unit=code==='age'?'岁':(code?.includes('co')||code?.includes('years')?'年':''); const suffix={group_co:'集团公司工作履历',listed_co:'上市公司工作履历',private_co:'非上市公司工作履历',work_years:'参加工作年限',prof_years:'从事专业年限'}[code]||''; list.querySelectorAll('.new-tier-row').forEach(row => { const min=row.querySelector('[name="new_min[]"]')?.value, max=row.querySelector('[name="new_max[]"]')?.value, label=row.querySelector('[name="new_label[]"]'); if(min!==''&&max!==''&&label&&!label.value.trim())label.value=`${min}至${max}${unit}${suffix}`; }); });
    form.addEventListener('submit', () => {
      form.querySelectorAll('[name="tier_order[]"]').forEach(input => input.remove());
      let newIndex=0;
      list.querySelectorAll('.dimension-score-row').forEach(row => {
        if(row.classList.contains('is-removed'))return;
        const id=row.querySelector('[name="rule_id[]"]')?.value;
        const input=document.createElement('input');input.type='hidden';input.name='tier_order[]';input.value=id?`existing:${id}`:`new:${newIndex++}`;form.append(input);
      });
    });
    function addRemove(row, isNew) {
      const grip=document.createElement('span');grip.className='tier-drag-handle';grip.tabIndex=0;grip.setAttribute('role','button');grip.setAttribute('aria-label','拖动调整档位顺序，也可使用上下方向键');grip.title='拖动排序';grip.textContent='⠿';row.prepend(grip);
      grip.addEventListener('keydown',event=>{
        if(!['ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();
        const rows=[...list.querySelectorAll('.dimension-score-row:not(.is-removed)')],index=rows.indexOf(row),other=rows[index+(event.key==='ArrowUp'?-1:1)];
        if(other){if(event.key==='ArrowUp')list.insertBefore(row,other);else list.insertBefore(other,row);grip.focus();}
      });
      grip.addEventListener('pointerdown',event=>{
        if(event.button!==0)return;event.preventDefault();
        const originalNext=row.nextSibling, pointerId=event.pointerId;
        let y=event.clientY, frame=0, active=true;
        grip.setPointerCapture(pointerId);row.classList.add('is-tier-dragging');document.body.classList.add('tier-sorting');grip.setAttribute('aria-pressed','true');
        const reorder=()=>{
          const siblings=[...list.querySelectorAll('.dimension-score-row:not(.is-removed)')].filter(item=>item!==row);
          const before=siblings.find(item=>{const box=item.getBoundingClientRect();return y<box.top+box.height/2;});
          if(before){if(row.nextElementSibling!==before)list.insertBefore(row,before);}else if(list.lastElementChild!==row)list.append(row);
          grip.setPointerCapture(pointerId);
        };
        const tick=()=>{if(!active)return;const edge=70,speed=y<edge?-10:(y>innerHeight-edge?10:0);if(speed){window.scrollBy(0,speed);reorder();}frame=requestAnimationFrame(tick);};
        const move=e=>{if(e.pointerId!==pointerId)return;y=e.clientY;reorder();};
        const finish=e=>{if(e.pointerId!==pointerId)return;active=false;cancelAnimationFrame(frame);
          if(e.type==='pointercancel'){if(originalNext?.parentNode===list)list.insertBefore(row,originalNext);else list.append(row);}
          row.classList.remove('is-tier-dragging');document.body.classList.remove('tier-sorting');grip.setAttribute('aria-pressed','false');
          grip.removeEventListener('pointermove',move);grip.removeEventListener('pointerup',finish);grip.removeEventListener('pointercancel',finish);grip.removeEventListener('lostpointercapture',finish);
          if(grip.hasPointerCapture(pointerId))grip.releasePointerCapture(pointerId);grip.focus({preventScroll:true});
        };
        grip.addEventListener('pointermove',move);grip.addEventListener('pointerup',finish);grip.addEventListener('pointercancel',finish);grip.addEventListener('lostpointercapture',finish);frame=requestAnimationFrame(tick);
      });
 const button=document.createElement('button'); button.type='button'; button.className='remove-tier'; button.textContent='删除'; button.addEventListener('click', () => { if (isNew) { row.remove(); return; } const id=row.querySelector('[name="rule_id[]"]')?.value; if (id && !row.querySelector('[name="remove_id[]"]')) { const marker=document.createElement('input'); marker.type='hidden'; marker.name='remove_id[]'; marker.value=id; row.append(marker); } row.classList.add('is-removed'); }); row.append(button); }
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
      trigger.hidden = isSuzhi;
      trigger.textContent = trigger.dataset.defaultLabel;
    });
    const form = document.querySelector('form[action*="standard_dimension_create"]');
    if (form) form.querySelector('[name="standard_scope"]')?.setAttribute('value', 'conditions');
    document.querySelectorAll('[data-basic-rating-open]').forEach(button => { button.hidden = target !== 'suzhi'; });
  }));
  const initialSection = new URLSearchParams(location.search).get('section') || tabs.find(tab => tab.classList.contains('is-active'))?.dataset.standardShow;
  if (initialSection === 'suzhi') tabs.find(tab => tab.dataset.standardShow === 'suzhi')?.click();
}

function initStandardDimensionSearch() {
 document.querySelectorAll('[data-standard-search]').forEach(input=>{
  const scope=input.dataset.standardSearch,panel=document.querySelector(`[data-standard-panel="${scope}"]`);if(!panel)return;
  const cards=[...panel.querySelectorAll('.dimension-score-card')],clear=document.querySelector(`[data-standard-search-clear="${scope}"]`),result=document.querySelector(`[data-standard-search-result="${scope}"]`);
  const empty=document.createElement('p');empty.className='condition-category-empty';empty.hidden=true;panel.append(empty);
  const apply=()=>{const keyword=input.value.trim().toLocaleLowerCase('zh-CN');let visible=0;
   cards.forEach(card=>{const matched=!keyword||card.textContent.toLocaleLowerCase('zh-CN').includes(keyword);card.hidden=!matched;if(matched)visible++;});
   if(clear)clear.hidden=!input.value;if(result)result.textContent=keyword?`找到 ${visible} 个匹配维度`:`共 ${cards.length} 个维度`;
   empty.hidden=visible>0;empty.textContent=keyword?'暂无匹配维度':'暂无维度，可点击“新增基本条件维度”添加。';
  };
  input.addEventListener('input',apply);clear?.addEventListener('click',()=>{input.value='';apply();input.focus();});apply();
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
    const inactiveRows = [];
    libraryList?.querySelectorAll('.question').forEach(row => {
      const id = row.querySelector('input[name="id"]')?.value;
      const actions = row.querySelector('.question-actions');
      if (id && actions) actionsByQuestionId.set(id, actions.cloneNode(true));
      if (row.textContent.includes('已停用')) inactiveRows.push(row.cloneNode(true));
    });
    if (!select) return;
    const requestedPostId = new URLSearchParams(window.location.search).get('publish_post_id');
    if (requestedPostId && [...select.options].some(option => option.value === requestedPostId)) select.value = requestedPostId;
    if (!form.id) form.id = 'question-paper-publish-form';
    const tools = document.createElement('div'); tools.className = 'question-picker-tools';
    const searchWrap = document.createElement('label'); searchWrap.className = 'question-picker-search'; searchWrap.setAttribute('aria-label', '搜索岗位专业题');
    const search = document.createElement('input'); search.type = 'search'; search.placeholder = '搜索题干或关键词'; search.autocomplete = 'off'; search.setAttribute('aria-label', '搜索岗位专业题');
    const searchClear = document.createElement('button'); searchClear.type = 'button'; searchClear.className = 'question-picker-clear'; searchClear.textContent = '×'; searchClear.setAttribute('aria-label', '清除题目搜索'); searchClear.hidden = true;
    searchWrap.append(search, searchClear);
    const selectVisible = document.createElement('button'); selectVisible.type = 'button'; selectVisible.className = 'btn secondary question-picker-bulk'; selectVisible.textContent = '全选当前结果';
    const clearSelected = document.createElement('button'); clearSelected.type = 'button'; clearSelected.className = 'question-picker-link'; clearSelected.textContent = '清空已选';
    tools.append(searchWrap, selectVisible, clearSelected);
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
      inactiveRows.forEach(row => {
        const meta = row.querySelector('small')?.textContent.trim() || '';
        const options = [...select.options];
        const post = options.find(option => meta.startsWith(`${option.textContent.trim()} ·`)) || options.find(option => {
          const postName = option.textContent.trim().split(' · ')[0];
          return meta.startsWith(`${postName} ·`);
        });
        if (!post) return;
        row.classList.add('question-pick', 'is-inactive');
        row.dataset.questionPost = post.value;
        row.setAttribute('aria-label', '已停用的岗位专业题，可编辑、启用或删除');
        pickList.append(row);
      });
      libraryList.replaceChildren(pickHead, tools, pickList);
      select.closest('.question-post-select')?.after(basket);
    } else if (pickHead) { pickHead.after(tools); tools.after(basket); }
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
      const basicCount = form.querySelector('[data-basic-post-counts]');
      if (basicCount) basicCount.querySelector('b').textContent = String(JSON.parse(basicCount.dataset.basicPostCounts)[postId] || 0);
      const items = [...pickerRoot.querySelectorAll('[data-question-post]')];
      const keyword = search.value.trim().toLowerCase();
      let available = 0, selected = 0, visible = 0;
      items.forEach(item => {
        const match = item.dataset.questionPost === postId;
        const checkbox = item.querySelector('input[type="checkbox"]');
        const text = item.textContent.toLowerCase();
        const shown = match && (!keyword || text.includes(keyword));
        item.hidden = !shown;
        if (checkbox) checkbox.disabled = !match;
        if (match && checkbox) { available += 1; if (shown) visible += 1; if (checkbox.checked) selected += 1; }
      });
      if (count) count.textContent = !postId ? '请先选择发布岗位' : (available ? `已选 ${selected} / ${available} 道专业题` : '该岗位暂未启用专业题');
      if (summary) { const number = document.createElement('b'); number.textContent = String(selected); summary.replaceChildren(number, document.createTextNode(' 道已选专业题')); }
      searchClear.hidden = !search.value; selectVisible.disabled = visible === 0; selectVisible.textContent = visible ? `全选当前结果（${visible}）` : '无匹配题目'; clearSelected.disabled = selected === 0;
      renderBasket();
    };
    const preservePublishPost = () => {
      const url = new URL(window.location.href);
      if (select.value) url.searchParams.set('publish_post_id', select.value);
      else url.searchParams.delete('publish_post_id');
      window.history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
      document.querySelectorAll('.question-actions a[href*="edit="]').forEach(link => {
        const editUrl = new URL(link.href, window.location.href);
        if (select.value) editUrl.searchParams.set('publish_post_id', select.value);
        else editUrl.searchParams.delete('publish_post_id');
        link.href = `${editUrl.pathname}${editUrl.search}${editUrl.hash}`;
      });
    };
    select.addEventListener('change', () => { preservePublishPost(); sync(); });
    pickerRoot.querySelectorAll('input[type="checkbox"]').forEach(input => input.addEventListener('change', sync));
    search.addEventListener('input', sync);
    searchClear.addEventListener('click', () => { search.value = ''; sync(); search.focus(); });
    selectVisible.addEventListener('click', () => { pickerRoot.querySelectorAll('[data-question-post]').forEach(item => { if (!item.hidden && item.dataset.questionPost === select.value) { const checkbox = item.querySelector('input[type="checkbox"]'); if (checkbox) checkbox.checked = true; } }); sync(); });
    clearSelected.addEventListener('click', () => { pickerRoot.querySelectorAll('[data-question-post]').forEach(item => { if (item.dataset.questionPost === select.value) { const checkbox = item.querySelector('input[type="checkbox"]'); if (checkbox) checkbox.checked = false; } }); sync(); });
    pickerRoot.querySelectorAll('.question-pick').forEach(item => item.addEventListener('click', event => {
      const checkbox = item.querySelector('input[type="checkbox"]');
      if (!checkbox || checkbox.disabled || event.target === checkbox || event.target.closest('a,button,form')) return;
      event.preventDefault(); checkbox.checked = !checkbox.checked; checkbox.dispatchEvent(new Event('change', {bubbles:true}));
    }));
    preservePublishPost();
    sync();
  });
}

function initSearchablePostSelects() {
  document.querySelectorAll('.question-editor select[name="post_id"],.basic-rating-editor select[name="post_id"]').forEach(select => {
    const host = document.createElement('span'); host.className = 'searchable-post-select'; host.dataset.searchablePostSelect = '';
    select.before(host); host.append(select);
  });
  document.querySelectorAll('[data-searchable-post-select]').forEach((host, index) => {
    const select = host.querySelector('select'); if (!select || host.dataset.enhanced) return;
    host.dataset.enhanced = '1';
    const options = [...select.options].filter(option => option.value && !option.disabled).map(option => ({value: option.value, label: option.textContent.trim()}));
    const listId = `searchable-post-list-${index}`;
    const control = document.createElement('div'); control.className = 'searchable-post-control';
    const input = document.createElement('input'); input.type = 'search'; input.autocomplete = 'off'; input.placeholder = '输入岗位名称、部门或公司搜索'; input.value = select.value ? (select.selectedOptions[0]?.textContent.trim() || '') : ''; input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-expanded', 'false'); input.setAttribute('aria-controls', listId); input.setAttribute('aria-label', '搜索并选择岗位'); input.required = select.required; select.tabIndex = -1;
    const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'searchable-post-clear'; clear.textContent = '×'; clear.setAttribute('aria-label', '清除岗位搜索'); clear.hidden = true;
    const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'searchable-post-toggle'; toggle.setAttribute('aria-label', '展开岗位列表'); toggle.setAttribute('aria-expanded', 'false'); toggle.innerHTML = '<span aria-hidden="true">⌄</span>';
    const list = document.createElement('div'); list.className = 'searchable-post-list'; list.id = listId; list.setAttribute('role', 'listbox'); list.hidden = true;
    control.append(input, clear, toggle, list); host.append(control); select.classList.add('searchable-post-native');
    let activeIndex = -1, open = false;
    const selectedLabel = () => select.value ? (select.selectedOptions[0]?.textContent.trim() || '') : '';
    const visibleOptions = () => {
      const keyword = input.value.trim().toLocaleLowerCase('zh-CN');
      return options.filter(option => !keyword || option.label.toLocaleLowerCase('zh-CN').includes(keyword));
    };
    const setOpen = next => {
      open = next; list.hidden = !next; input.setAttribute('aria-expanded', String(next)); toggle.setAttribute('aria-expanded', String(next));
      if (!next) { activeIndex = -1; input.removeAttribute('aria-activedescendant'); }
    };
    const choose = option => {
      if (!option) return;
      select.value = option.value;
      select.dispatchEvent(new Event('change', {bubbles:true}));
      input.value = option.label; input.setCustomValidity('');
      setOpen(false);
      render();
    };
    const render = () => {
      const matches = visibleOptions(); list.replaceChildren();
      if (!matches.length) { const empty = document.createElement('p'); empty.className = 'searchable-post-empty'; empty.textContent = '未找到匹配岗位'; list.append(empty); activeIndex = -1; input.removeAttribute('aria-activedescendant'); }
      matches.forEach((option, optionIndex) => {
        const item = document.createElement('button'); item.type = 'button'; item.tabIndex = -1; item.className = 'searchable-post-option'; item.id = `${listId}-${optionIndex}`; item.setAttribute('role', 'option'); item.setAttribute('aria-selected', String(option.value === select.value)); item.textContent = option.label;
        item.addEventListener('mousedown', event => event.preventDefault()); item.addEventListener('click', () => choose(option)); list.append(item);
      });
      clear.hidden = !input.value;
      if (activeIndex >= matches.length) activeIndex = matches.length - 1;
      [...list.querySelectorAll('.searchable-post-option')].forEach((item, itemIndex) => {
        item.classList.toggle('is-active', itemIndex === activeIndex);
        if (itemIndex === activeIndex) { input.setAttribute('aria-activedescendant', item.id); item.scrollIntoView({block:'nearest'}); }
      });
      if (activeIndex < 0) input.removeAttribute('aria-activedescendant');
      return matches;
    };
    const openList = (showAll = false) => { if (showAll) input.value = ''; setOpen(true); render(); };
    input.addEventListener('focus', () => { input.select(); openList(true); });
    input.addEventListener('input', () => { activeIndex = -1; input.setCustomValidity(select.required ? '请从列表中选择岗位' : ''); openList(false); });
    input.addEventListener('keydown', event => {
      const matches = visibleOptions();
      if (event.key === 'ArrowDown') { event.preventDefault(); if (!open) openList(false); activeIndex = Math.min(activeIndex + 1, matches.length - 1); render(); }
      else if (event.key === 'ArrowUp') { event.preventDefault(); if (!open) openList(false); activeIndex = Math.max(activeIndex - 1, 0); render(); }
      else if (event.key === 'Enter' && open) { event.preventDefault(); choose(matches[activeIndex] || (matches.length === 1 ? matches[0] : null)); }
      else if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); input.value = selectedLabel(); setOpen(false); render(); }
    });
    input.addEventListener('blur', () => window.setTimeout(() => { if (!host.contains(document.activeElement)) { input.value = selectedLabel(); setOpen(false); render(); } }, 120));
    clear.addEventListener('click', () => { input.value = ''; select.value = ''; select.dispatchEvent(new Event('change', {bubbles:true})); input.focus(); openList(false); });
    toggle.addEventListener('click', () => { if (open) { input.value = selectedLabel(); setOpen(false); render(); } else { input.focus(); openList(true); } });
    const syncSelection = () => { input.value = selectedLabel(); input.setCustomValidity(''); setOpen(false); render(); };
    select.addEventListener('change', syncSelection);
    host.closest('form')?.addEventListener('questionDraftCleared', syncSelection);
    document.addEventListener('pointerdown', event => { if (!host.contains(event.target)) syncSelection(); });
    input.addEventListener('invalid', () => input.focus());
    select.addEventListener('invalid', () => input.focus());
    render();
  });
}

function initQuestionArchiveLink() {
  if (!document.querySelector('[data-question-paper-builder]')) return;
  const head = document.querySelector('.page-head'); if (!head) return;
  let actions = head.querySelector('.page-actions');
  if (!actions) { actions = document.createElement('div'); actions.className = 'page-actions'; head.append(actions); }
  if (actions.querySelector('[data-paper-archive-link]')) return;
  const link = document.createElement('a'); link.className = 'btn secondary'; link.href = '?page=paper_archive'; link.dataset.paperArchiveLink = '1'; link.textContent = '查看题卷档案'; actions.append(link);
}

function initPaperArchive() {
  const root = document.querySelector('.paper-archive-layout'); if (!root) return;
  const selects = [...root.querySelectorAll('[data-paper-select]')], records = [...root.querySelectorAll('[data-paper-record]')];
  const index = root.querySelector('.paper-archive-index'), list = index?.querySelector('.paper-archive-nav'), detail = root.querySelector('.paper-archive-detail'); if (!index || !list || !detail) return;
  const search = document.createElement('div'); search.className = 'paper-archive-search';
  const input = document.createElement('input'); input.type = 'search'; input.placeholder = '搜索岗位、部门或版本'; input.autocomplete = 'off'; input.setAttribute('aria-label', '搜索岗位与题卷版本');
  const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'paper-archive-search-clear'; clear.textContent = '×'; clear.hidden = true; clear.setAttribute('aria-label', '清除岗位搜索');
  const summary = document.createElement('p'); summary.className = 'paper-archive-search-summary'; summary.setAttribute('aria-live', 'polite'); search.append(input, clear, summary); list.before(search);
  const pager = document.createElement('nav'); pager.className = 'paper-archive-pagination'; pager.setAttribute('aria-label', '岗位题卷版本分页'); pager.innerHTML = '<button type="button" class="btn secondary" data-prev>上一页</button><b></b><button type="button" class="btn secondary" data-next>下一页</button>'; list.after(pager);
  const noResults = document.createElement('div'); noResults.className = 'paper-archive-no-results'; noResults.hidden = true; noResults.textContent = '未找到匹配的岗位或题卷版本，请清除搜索后重试。'; detail.prepend(noResults);
  const perPage = 6; let page = 1, selectedId = selects.find(item => item.classList.contains('is-active'))?.dataset.paperSelect || selects[0]?.dataset.paperSelect || '', composing = false;
  const show = id => {
    selectedId = id; selects.forEach(item => item.classList.toggle('is-active', item.dataset.paperSelect === id));
    records.forEach(record => { record.hidden = record.dataset.paperRecord !== id; });
  };
  const matches = () => {
    const keyword = input.value.trim().toLocaleLowerCase('zh-CN');
    return selects.filter(item => !keyword || item.textContent.toLocaleLowerCase('zh-CN').includes(keyword));
  };
  const render = ({pickFirst = false} = {}) => {
    const items = matches(), pages = Math.max(1, Math.ceil(items.length / perPage)); page = Math.min(Math.max(page, 1), pages);
    const start = (page - 1) * perPage, visible = items.slice(start, start + perPage);
    selects.forEach(item => { item.hidden = !visible.includes(item); });
    if (pickFirst || !visible.some(item => item.dataset.paperSelect === selectedId)) show(visible[0]?.dataset.paperSelect || '');
    noResults.hidden = items.length > 0;
    clear.hidden = !input.value;
    summary.textContent = items.length ? `显示第 ${start + 1}–${Math.min(start + perPage, items.length)} 条，共 ${items.length} 个版本` : '未找到匹配的岗位或版本';
    pager.hidden = items.length <= perPage; pager.querySelector('b').textContent = `${page} / ${pages}`; pager.querySelector('[data-prev]').disabled = page === 1; pager.querySelector('[data-next]').disabled = page === pages;
  };
  selects.forEach(button => button.addEventListener('click', () => show(button.dataset.paperSelect)));
  input.addEventListener('compositionstart', () => { composing = true; }); input.addEventListener('compositionend', () => { composing = false; page = 1; render({pickFirst:true}); });
  input.addEventListener('input', () => { if (composing) return; page = 1; render({pickFirst:true}); });
  clear.addEventListener('click', () => { input.value = ''; page = 1; render({pickFirst:true}); input.focus(); });
  pager.querySelector('[data-prev]').addEventListener('click', () => { page -= 1; render({pickFirst:true}); }); pager.querySelector('[data-next]').addEventListener('click', () => { page += 1; render({pickFirst:true}); });
  render();
}

function initPublishedPapers() {
  const host = document.querySelector('.question-publish-panel');
  if (!host) return;
  const panel = document.createElement('section'); panel.className = 'published-papers';
  const head = document.createElement('div'); head.className = 'panel-head';
  const title = document.createElement('div'); title.innerHTML = '<h2>已发布试卷</h2><small>查看各岗位正在使用的题卷及历史版本。</small>';
  const filter = document.createElement('select'); filter.setAttribute('aria-label', '按岗位筛选已发布试卷');
  head.append(title, filter); const list = document.createElement('div'); list.className = 'published-paper-list'; panel.append(head, list);
  const stack = document.createElement('div'); stack.className = 'question-publish-stack';
  host.parentElement?.insertBefore(stack, host); stack.append(host, panel);
  const typeLabel = {base:'基本素质题', post:'岗位专业题'};
  fetch('?page=questions&action=paper_list', {credentials:'same-origin'})
    .then(response => response.ok ? response.json() : Promise.reject())
    .then(data => {
      const papers = Array.isArray(data.papers) ? data.papers : [];
      const posts = [...new Map(papers.map(paper => [String(paper.post_id), paper.post_name])).entries()];
      filter.append(new Option('全部岗位', ''));
      posts.forEach(([id, name]) => filter.append(new Option(name, id)));
      const render = () => {
        list.replaceChildren(); const visible = papers.filter(paper => !filter.value || String(paper.post_id) === filter.value);
        if (!visible.length) { const empty = document.createElement('p'); empty.className = 'paper-empty'; empty.textContent = '暂无已发布试卷。发布后将在这里保留版本记录。'; list.append(empty); return; }
        visible.forEach(paper => {
          const card = document.createElement('article'); card.className = `published-paper-card ${paper.status === 'published' ? 'is-current' : ''}`;
          const top = document.createElement('div'); top.className = 'published-paper-top';
          const name = document.createElement('div'); const heading = document.createElement('b'); heading.textContent = `${paper.post_name} · V${paper.version}`;
          const time = document.createElement('small'); time.textContent = paper.published_at ? `发布时间：${paper.published_at}` : '未记录发布时间'; name.append(heading, time);
          const state = document.createElement('span'); state.className = 'paper-state'; state.textContent = paper.status === 'published' ? '当前使用' : '历史版本'; top.append(name, state);
          const items = Array.isArray(paper.items) ? paper.items : [];
          const summary = document.createElement('p'); summary.className = 'paper-summary';
          const base = items.filter(item => item.question_type === 'base').length, post = items.filter(item => item.question_type === 'post').length;
          summary.textContent = `基本条件按启用规则自动评分；基本素质 ${base} 道，岗位专业题 ${post} 道。`;
          const details = document.createElement('details'); const detailsTitle = document.createElement('summary'); detailsTitle.textContent = `查看题目清单（${items.length} 道）`; details.append(detailsTitle);
          const questions = document.createElement('ol'); questions.className = 'published-paper-items';
          items.forEach(item => { const row = document.createElement('li'); const stem = document.createElement('b'); stem.textContent = item.stem_snapshot || '未命名题目'; const meta = document.createElement('small'); meta.textContent = `${typeLabel[item.question_type] || '题目'} · ${item.q_type === 'single' ? '单选题' : item.q_type === 'multi' ? '多选题' : '简答题'} · ${item.score_snapshot ?? 0} 分`; row.append(stem, meta); questions.append(row); });
          details.append(questions); card.append(top, summary, details); list.append(card);
        });
      };
      filter.addEventListener('change', render); render();
    })
    .catch(() => { const message = document.createElement('p'); message.className = 'paper-empty'; message.textContent = '试卷记录加载失败，请刷新后重试。'; list.append(message); });
}

function initQuestionEditorOptions() {
  document.querySelectorAll('form.question-editor').forEach(form => {
    const field = form.querySelector('textarea[name="options"]');
    const label = field?.closest('label') || [...form.querySelectorAll('label')].find(item => item.textContent.includes('选项（'));
    const answerField = form.querySelector('input[name="answer"]');
    const answerLabel = answerField?.closest('label');
    if (!field || !label || !answerField || !answerLabel) return;
    field.classList.add('options-storage');
    const textNode = [...label.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
    if (textNode) textNode.textContent = '选项（单选题、多选题必填）';

    const parseOptions = () => {
      const sample = '["选项 A","选项 B","选项 C"]';
      if (!field.value.trim() || field.value.trim() === sample) return [];
      try { const value = JSON.parse(field.value); if (Array.isArray(value)) return value.map(item => String(item)); } catch (_) {}
      return field.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean);
    };
    // 多选答案由编辑器统一用“|”保存；不能把中文逗号当分隔符，否则单选文本中的正常标点会导致答案无法回显。
    const answerValues = () => answerField.value.split('|').map(value => value.trim()).filter(Boolean);
    const isStoredAnswer = (option, stored) => {
      const normalize = value => String(value).trim().replace(/^[A-D][.、．]\s*/i, '');
      return String(option).trim() === String(stored).trim() || normalize(option) === normalize(stored);
    };
    const renderAnswerPicker = options => {
      answerLabel.querySelector('.question-answer-picker')?.remove();
      const type = form.querySelector('[name="q_type"]')?.value;
      const title = [...answerLabel.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
      if (type === 'short') {
        if (title) title.textContent = '简答评分要点';
        answerField.classList.remove('answer-storage'); answerField.required = true;
        return;
      }
      if (title) title.textContent = type === 'multi' ? '正确答案（可勾选多个选项）' : '正确答案（请选择一个选项）';
      answerField.classList.add('answer-storage'); answerField.required = false;
      const picker = document.createElement('div'); picker.className = `question-answer-picker ${type === 'multi' ? 'is-multi' : 'is-single'}`;
      if (!options.length) { const hint = document.createElement('small'); hint.textContent = '请先新增至少一个选项。'; picker.append(hint); answerLabel.append(picker); return; }
      const selected = answerValues();
      const syncAnswer = () => {
        if (type === 'multi') answerField.value = [...picker.querySelectorAll('input:checked')].map(input => input.value).join('|');
        else answerField.value = picker.querySelector('select')?.value || '';
      };
      if (type === 'multi') {
        options.forEach((option, index) => {
          const choice = document.createElement('label'); choice.className = 'question-answer-choice';
          const input = document.createElement('input'); input.type = 'checkbox'; input.value = option; input.checked = selected.some(value => isStoredAnswer(option, value));
          const text = document.createElement('span'); text.textContent = `选项 ${String.fromCharCode(65 + index)}：${option}`;
          input.addEventListener('change', syncAnswer); choice.append(input, text); picker.append(choice);
        });
      } else {
        const select = document.createElement('select'); const empty = document.createElement('option'); empty.value = ''; empty.textContent = '请选择正确答案'; select.append(empty);
        const matchedAnswer = options.find(option => selected.some(value => isStoredAnswer(option, value))) || '';
        options.forEach((option, index) => { const item = document.createElement('option'); item.value = option; item.textContent = `选项 ${String.fromCharCode(65 + index)}：${option}`; select.append(item); });
        select.value = matchedAnswer;
        select.addEventListener('change', syncAnswer); picker.append(select);
      }
      answerLabel.append(picker); syncAnswer();
    };
    const build = () => {
      label.querySelector('.question-options-editor')?.remove();
      const editor = document.createElement('div'); editor.className = 'question-options-editor';
      const list = document.createElement('div'); list.className = 'question-options-list';
      const sync = () => { const options = [...list.querySelectorAll('input')].map(input => input.value.trim()).filter(Boolean); field.value = JSON.stringify(options); renderAnswerPicker(options); };
      const row = value => {
        const item = document.createElement('div'); item.className = 'question-option-row';
        const input = document.createElement('input'); input.type = 'text'; input.placeholder = '请输入选项内容'; input.value = value || '';
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn text-danger option-remove'; remove.textContent = '删除';
        remove.addEventListener('click', () => { item.remove(); sync(); }); input.addEventListener('input', sync);
        item.append(input, remove); list.append(item);
      };
      const add = document.createElement('button'); add.type = 'button'; add.className = 'btn secondary option-add'; add.textContent = '+ 新增选项';
      add.addEventListener('click', () => { row(''); sync(); list.querySelector('.question-option-row:last-child input')?.focus(); });
      (parseOptions().length ? parseOptions() : ['', '']).forEach(row);
      editor.append(list, add); label.append(editor); sync();
      const type = form.querySelector('[name="q_type"]');
      const toggle = () => { editor.hidden = type?.value === 'short'; renderAnswerPicker([...list.querySelectorAll('input')].map(input => input.value.trim()).filter(Boolean)); };
      if (type && !type.dataset.answerPickerBound) { type.dataset.answerPickerBound = '1'; type.addEventListener('change', () => form.dispatchEvent(new CustomEvent('refreshQuestionOptionsEditor'))); }
      toggle();
    };
    build();
    form.addEventListener('restoreQuestionOptionsEditor', () => { if (!label.querySelector('.question-options-editor')) build(); });
    form.addEventListener('resetQuestionOptionsEditor', build);
    form.addEventListener('refreshQuestionOptionsEditor', build);
  });
}

function initDirectQrActions() {
  document.querySelector('[data-direct-print]')?.addEventListener('click', () => window.print());
  document.querySelector('[data-direct-download]')?.addEventListener('click', () => window.downloadQr?.());
}

function initPostDuplicateConfirmation() {
  const form = document.querySelector('[data-post-create-form]'); if (!form) return;
  const labels = { recruiting:'招聘中', reserve:'储备中', stop:'已停止' };
  form.addEventListener('submit', async event => {
    if (form.dataset.sameNameChecked === '1' || form.querySelector('[name="id"]')?.value) return;
    const name = form.querySelector('[name="name"]')?.value.trim();
    const company = form.querySelector('[name="company"]')?.value.trim();
    if (!name || !company) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    let matches = [];
    try {
      const response = await fetch(`?page=posts&action=post_name_lookup&name=${encodeURIComponent(name)}&company=${encodeURIComponent(company)}`, {credentials:'same-origin'});
      const data = response.ok ? await response.json() : {posts:[]};
      matches = Array.isArray(data.posts) ? data.posts : [];
    } catch (_) {
      window.alert('无法校验同名岗位，请刷新后重试。');
      return;
    }
    if (!matches.length) { form.dataset.sameNameChecked = '1'; form.requestSubmit(); return; }
    const details = matches.map(post => `${post.company || '未填写部门'} · ${labels[post.status] || post.status}`).join('；');
    showConfirm({title:'存在重复岗位',description:`“${company}”下已存在“${name}”（${details}）。是否仍新建一个独立岗位？`,actionLabel:'仍然新建',onConfirm:()=>{form.dataset.sameNameChecked='1'; let input=form.querySelector('[name="same_name_confirmed"]'); if(!input){input=document.createElement('input');input.type='hidden';input.name='same_name_confirmed';form.append(input);} input.value='1'; form.requestSubmit();}});
  }, true);
}

function clearQuestionDraft(form) {
  form.reset();
  for (const field of form.querySelectorAll('input,select,textarea')) {
    if (field.type === 'hidden' || field.readOnly) continue;
    if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
    else field.value = '';
  }
  const id = form.elements.namedItem('id'); if (id) id.value = '0';
  form.dispatchEvent(new CustomEvent('questionDraftCleared'));
  const intent = form.elements.namedItem('save_continue'); if (intent) intent.value = '0';
  form.querySelectorAll('.field-error,.form-error').forEach(node => node.remove());
  form.querySelectorAll('[aria-invalid]').forEach(node => node.removeAttribute('aria-invalid'));
}

function initFormModals() {
  const forms = [...document.querySelectorAll('form.form-panel:not(.settings-form):not(.password-settings-form),form.dimension-form')]; if (!forms.length) return;
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
    const open = () => { if (form.matches('.question-editor') && !Number(form.elements.namedItem('id').value) && !form.elements.namedItem('score').value) form.elements.namedItem('score').value = '10'; openDialog(overlay, form.querySelector(focusables)); };
    const discard = () => { form.reset(); if (form.matches('.question-editor')) { clearQuestionDraft(form); const url = new URL(location.href); url.searchParams.delete('post_id'); history.replaceState(null, '', url); } form.dispatchEvent(new CustomEvent('resetQuestionOptionsEditor')); dirty=false; closeDialog(overlay); };
    const close = () => dirty ? showConfirm({title:'放弃未保存的修改？',description:'关闭后，本次填写的内容不会保存。',actionLabel:'放弃修改',onConfirm:discard}) : discard();
    top.querySelector('[data-dialog-close]').addEventListener('click', close); overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    const params = new URLSearchParams(location.search); const isEditing = params.has('edit'); const autoOpen = i === 0 && (params.has('new') || isEditing);
    const existingTrigger = i === 0 ? [...(actions?.querySelectorAll('a,button') || [])].find(item => item.getAttribute('href')?.includes('new=1')) : null;
    let trigger = existingTrigger;
    if (trigger) {
      trigger.addEventListener('click', event => {
        event.preventDefault();
        if (form.matches('[data-post-create-form]')) {
          form.reset();
          const id = form.querySelector('[name="id"]'); if (id) id.value = '';
          ['name','company','series','duty'].forEach(name => { const field = form.querySelector(`[name="${name}"]`); if (field) field.value = ''; });
          const status = form.querySelector('[name="status"]'); if (status) status.value = 'recruiting';
          delete form.dataset.sameNameChecked;
          form.querySelector('[name="same_name_confirmed"]')?.remove();
          top.querySelector('h2').textContent = '新建岗位';
          form.dispatchEvent(new CustomEvent('resetQuestionOptionsEditor'));
          dirty = false;
        }
        open();
      });
    } else {
      trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'btn primary modal-trigger'; const triggerTitle = isEditing ? title.replace(/^编辑/,'新增') : title; trigger.textContent = triggerTitle.includes('密码') ? `修改${triggerTitle.replace('修改','')}` : `＋ ${triggerTitle}`; trigger.dataset.defaultLabel = trigger.textContent; trigger.addEventListener('click', () => { if (isEditing) { const next = new URLSearchParams(); const currentPage = new URLSearchParams(location.search).get('page') || ''; next.set('page', currentPage); next.set('new','1'); location.href = `${location.pathname}?${next}`; return; } open(); }); actions?.append(trigger);
    }
    if (form.classList.contains('standard-dimension-form')) { const switcher=document.querySelector('.standard-section-switch'); if(switcher&&!switcher.dataset.swapped){const slot=document.createElement('div');slot.className='standard-create-slot';switcher.replaceWith(slot);actions?.append(switcher);slot.append(trigger);switcher.dataset.swapped='1';} }
    if (autoOpen) { open(); params.delete('new'); params.delete('edit'); const query = params.toString(); history.replaceState(null, '', `${location.pathname}${query ? `?${query}` : ''}`); }
  });
  initBasicRatingModal();
}

function initBasicRatingModal() {
  const form = document.querySelector('.basic-rating-editor');
  if (!form) return;
  const trigger = document.createElement('button');
  trigger.type = 'button'; trigger.className = 'btn primary';
  trigger.dataset.basicRatingOpen = ''; trigger.textContent = '＋ 新增基本素质题';
  (document.querySelector('.standard-create-slot') || document.querySelector('.page-actions')).append(trigger);
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay basic-rating-overlay'; overlay.setAttribute('aria-hidden', 'true');
  overlay.innerHTML = '<section class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="basic-rating-title"><div class="modal-top"><div><small>基本素质题库</small><h2 id="basic-rating-title"></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="关闭弹窗">×</button></div></section>';
  const title = overlay.querySelector('h2');
  title.textContent = form.querySelector('.rating-editor-head h2').textContent;
  form.classList.add('modal-form'); overlay.querySelector('.modal-dialog').append(form);
  document.body.append(overlay); form.hidden = false;
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('change', () => { dirty = true; });
  const close = () => {
    const discard = () => {
      clearQuestionDraft(form); dirty = false; closeDialog(overlay);
      const url = new URL(location.href); url.searchParams.delete('basic_edit'); url.searchParams.delete('basic_new');
      history.replaceState(history.state, '', url);
    };
    if (dirty) showConfirm({title:'放弃未保存的修改？',description:'关闭后，本次填写的内容不会保存。',actionLabel:'放弃修改',onConfirm:discard});
    else discard();
  };
  overlay.querySelector('[data-dialog-close]').addEventListener('click', close);
  overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
  form.querySelector('[data-basic-rating-cancel]').addEventListener('click', close);
  trigger.addEventListener('click', () => {
    clearQuestionDraft(form);
    form.querySelectorAll('.field-error').forEach(error => error.remove());
    form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
    title.textContent = '新增基本素质题'; form.querySelector('[data-save-continue]').hidden = false; dirty = false;
    openDialog(overlay, form.elements.namedItem('stem'));
  });
  document.querySelector('[data-standard-show].is-active')?.click();
  if (Number(form.elements.namedItem('id').value) > 0 || new URLSearchParams(location.search).has('basic_new')) openDialog(overlay, form.elements.namedItem('stem'));
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
      const box=overlay.querySelector('.qr-popup-code'); new QRCode(box,{text:value,width:280,height:280,colorDark:'#000',colorLight:'#fff',correctLevel:QRCode.CorrectLevel.M});
      const close=()=>{closeDialog(overlay);setTimeout(()=>overlay.remove(),180);}; overlay.querySelector('[data-dialog-close]').addEventListener('click',close); overlay.addEventListener('click',e=>{if(e.target===overlay)close();});
      overlay.querySelector('.qr-print').addEventListener('click',()=>window.print()); overlay.querySelector('.qr-download').addEventListener('click',()=>{const canvas=box.querySelector('canvas');if(!canvas)return;const a=document.createElement('a');a.download=`${title}-二维码.png`;a.href=canvas.toDataURL('image/png');a.click();}); openDialog(overlay,overlay.querySelector('[data-dialog-close]'));
    } catch { location.href=link.href; }
  }));
}

function initTablePagination() {
  const isInterviewResults = new URLSearchParams(location.search).get('page') === 'interview_results';
  document.querySelectorAll('.table-wrap table').forEach((table, index)=>{
    const rows=[...table.tBodies].flatMap(body=>[...body.rows]), size=isInterviewResults?10:12; if(rows.length<=size)return; let page=1; const pages=Math.ceil(rows.length/size),label=isInterviewResults?'面试官评分明细':'表格',nav=document.createElement('nav');nav.className='table-pagination';nav.setAttribute('aria-label',`${label}分页`);nav.innerHTML='<span></span><div><button type="button" class="btn secondary" data-prev>上一页</button><b></b><button type="button" class="btn secondary" data-next>下一页</button></div>';table.closest('.table-wrap').append(nav);
    const render=()=>{nav.dataset.page=String(page);const start=(page-1)*size;rows.forEach((row,i)=>row.hidden=i<start||i>=start+size);nav.querySelector('span').textContent=`${label}：第 ${start+1}–${Math.min(start+size,rows.length)} 条，共 ${rows.length} 条`;nav.querySelector('b').textContent=`${page} / ${pages}`;nav.querySelector('[data-prev]').disabled=page===1;nav.querySelector('[data-next]').disabled=page===pages;};
    nav.querySelector('[data-prev]').addEventListener('click',()=>{page--;render();table.scrollIntoView({behavior:'smooth',block:'start'});});nav.querySelector('[data-next]').addEventListener('click',()=>{page++;render();table.scrollIntoView({behavior:'smooth',block:'start'});});nav.addEventListener('restore-page',event=>{page=Math.max(1,Math.min(pages,Number(event.detail)||1));render();});render();
  });
}

function initQualityDetails() {
  const query = new URLSearchParams(location.search);
  if (query.get('page') !== 'talent') return;
  const host = document.querySelector('.review-detail .dimensions');
  if (!host) return;
  host.hidden = true;
}

function initStandardCardLayout(){
 document.querySelectorAll('#standard-conditions .dimension-score-card').forEach(form=>{
  form.classList.add('a-layout');
  const header=form.querySelector('header'),actions=form.querySelector('.dimension-actions'),list=form.querySelector('.dimension-score-list');
  const settings=document.createElement('div');settings.className='dimension-settings-panel';settings.hidden=true;
  const toggle=document.createElement('button');toggle.type='button';toggle.className='btn secondary dimension-settings-toggle';toggle.textContent='维度设置';toggle.setAttribute('aria-expanded','false');
  [form.querySelector('[data-dimension-name-edit]'),form.querySelector('.dimension-name-editor'),...actions.querySelectorAll('button'),form.querySelector('footer .danger')].filter(Boolean).forEach(el=>settings.append(el));
  const count=actions.querySelector('span');if(count){header.firstElementChild.append(count);count.classList.add('dimension-count');}
  actions.append(toggle);header.after(settings);
  const bonus=form.querySelector('.condition-bonus-editor');if(bonus)list.after(bonus);
  toggle.addEventListener('click',()=>{settings.hidden=!settings.hidden;toggle.setAttribute('aria-expanded',String(!settings.hidden));});
  const headings=document.createElement('div');headings.className='tier-table-head';headings.innerHTML='<span></span><span>评分档位</span><span>分值</span><span>追问</span><span></span>';list.before(headings);
  function enhance(row){
   if(row.dataset.aLayout)return;row.dataset.aLayout='1';
   const more=document.createElement('button');more.type='button';more.className='tier-more';more.textContent='···';more.setAttribute('aria-label','编辑或删除档位');more.setAttribute('aria-expanded','false');row.append(more);
   more.addEventListener('click',()=>{const open=row.classList.toggle('tier-actions-open');more.setAttribute('aria-expanded',String(open));});
   const follow=row.querySelector('button.tier-followup'),text=row.querySelector('.tier-followup-panel textarea'),required=row.querySelector('.tier-followup-required input');
   const update=()=>{if(follow)follow.textContent=text?.value.trim()?(required?.checked?'已设置 · 必填':'已设置'):'＋ 添加追问';};
   text?.addEventListener('input',update);required?.addEventListener('change',update);update();
   const score=row.querySelector(':scope > label');if(score){score.classList.add('tier-score-cell');const input=score.querySelector('input');if(input)input.setAttribute('aria-label','档位分值');}
  }
  list.querySelectorAll('.dimension-score-row').forEach(enhance);
  new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1&&node.matches('.dimension-score-row'))enhance(node);}))).observe(list,{childList:true});
 });
}
function initPostOrderSorting() {
  document.querySelectorAll('.post-order ol').forEach(list => {
    let drag = null;
    // Pointer sorting avoids native text drags and browser drag-to-search gestures.
    list.addEventListener('dragstart', event => event.preventDefault());
    list.addEventListener('pointerdown', event => {
      const row = event.target.closest('li');
      if (!row || row.parentElement !== list || event.target.closest('button') || event.button !== 0 || !event.isPrimary) return;
      drag = { row, pointerId: event.pointerId, x: event.clientX, y: event.clientY, active: false };
      list.setPointerCapture(event.pointerId);
      event.preventDefault();
    });
    list.addEventListener('pointermove', event => {
      if (!drag || event.pointerId !== drag.pointerId) return;
      event.preventDefault();
      if (!drag.active && Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < 6) return;
      drag.active = true;
      drag.row.classList.add('is-post-dragging');
      const bounds = list.getBoundingClientRect();
      if (event.clientY < bounds.top + 30) list.scrollTop -= 16;
      else if (event.clientY > bounds.bottom - 30) list.scrollTop += 16;
      const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('.post-order li');
      if (!target || target === drag.row || target.parentElement !== list) return;
      const rect = target.getBoundingClientRect();
      const after = event.clientX > rect.left + rect.width / 2;
      list.insertBefore(drag.row, after ? target.nextSibling : target);
    });
    const finish = event => {
      if (!drag || event.pointerId !== drag.pointerId) return;
      const pointerId = drag.pointerId;
      drag.row.classList.remove('is-post-dragging');
      drag = null;
      if (list.hasPointerCapture(pointerId)) list.releasePointerCapture(pointerId);
    };
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(type => list.addEventListener(type, finish));
    list.querySelectorAll('[data-post-move]').forEach(button => button.addEventListener('click', () => {
      const row = button.closest('li');
      const other = button.dataset.postMove === '-1' ? row.previousElementSibling : row.nextElementSibling;
      if (other) button.dataset.postMove === '-1' ? list.insertBefore(row, other) : list.insertBefore(other, row);
    }));
  });
}
document.addEventListener('DOMContentLoaded', initPostOrderSorting);
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.condition-bonus-editor').forEach(editor=>{
  const update=row=>{const question=row.querySelector('textarea').value.trim(),required=row.querySelector('[name="bonus_required[]"]').value==='1';row.querySelector('[data-bonus-followup]').textContent=question?(required?'已设置 · 必填':'已设置追问'):'＋追问';};
  editor.addEventListener('click',event=>{
   const row=event.target.closest('.bonus-edit-row');
   if(event.target.closest('[data-remove-bonus]'))row.remove();
   if(event.target.closest('[data-bonus-followup]')){const panel=row.querySelector('.bonus-followup-editor');panel.hidden=!panel.hidden;row.querySelector('[data-bonus-followup]').setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden)panel.querySelector('textarea').focus();}
   if(event.target.closest('[data-add-bonus]')){const row=editor.querySelector('[data-bonus-template]').content.firstElementChild.cloneNode(true);editor.querySelector('[data-bonus-rows]').append(row);row.querySelector('[name="bonus_label[]"]').focus();}
  });
  ['input','change'].forEach(type=>editor.addEventListener(type,event=>{const row=event.target.closest('.bonus-edit-row');if(row)update(row);}));
 });
});

function showNotification(message, type = 'ok') {
  let stack = document.querySelector('.notification-stack');
  if (!stack) { stack = document.createElement('div'); stack.className = 'notification-stack'; document.body.append(stack); }
  const notice = document.createElement('section'); notice.className = `app-notification${type === 'error' ? ' is-error' : ''}`;
  const content = document.createElement('div'); content.setAttribute('role', type === 'error' ? 'alert' : 'status'); content.setAttribute('aria-atomic', 'true');
  const title = document.createElement('strong'); title.textContent = type === 'error' ? '操作未完成' : '操作成功';
  const text = document.createElement('p'); text.textContent = message; content.append(title, text);
  const close = document.createElement('button'); close.type = 'button'; close.className = 'notification-close'; close.setAttribute('aria-label', '关闭通知'); close.textContent = '×';
  notice.append(content, close); stack.append(notice);
  let timer; const pause = () => clearTimeout(timer), dismiss = () => { pause(); notice.remove(); if (!stack.children.length) stack.remove(); };
  const resume = () => { pause(); if (type !== 'error' && !notice.matches(':hover,:focus-within')) timer = setTimeout(dismiss, 8000); };
  close.addEventListener('click', dismiss); notice.addEventListener('mouseenter', pause); notice.addEventListener('mouseleave', resume);
  notice.addEventListener('focusin', pause); notice.addEventListener('focusout', () => setTimeout(resume, 0)); resume();
}

function initNotifications() {
  document.querySelectorAll('.content > .flash').forEach(flash => {
    showNotification(flash.textContent.trim(), flash.classList.contains('error') ? 'error' : 'ok'); flash.remove();
  });
}

function initStandardViewState() {
  const query = new URLSearchParams(location.search), key = 'standards-return-view';
  const read = () => { try { return JSON.parse(sessionStorage.getItem(key)); } catch { return null; } };
  const clear = () => { try { sessionStorage.removeItem(key); } catch {} };
  if (query.get('page') !== 'standards') { clear(); return; }
  const saved = read(); clear();
  const section = () => document.querySelector('[data-standard-show].is-active')?.dataset.standardShow || 'conditions';
  const snapshot = () => ({time:Date.now(), section:section(), y:window.scrollY,
    filter:document.querySelector('.rating-post-filter select')?.value || '',
    searches:[...document.querySelectorAll('[data-standard-search]')].map(input=>[input.dataset.standardSearch,input.value]),
    pages:[...document.querySelectorAll('.table-pagination')].map(nav=>Number(nav.dataset.page)||1),
    orderingOpen:!!document.querySelector('.condition-order')?.open});
  const remember = () => { const state = snapshot(); try { sessionStorage.setItem(key, JSON.stringify(state)); } catch {} return state; };
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]'); if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0 || link.target === '_blank') return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || url.searchParams.get('page') !== 'standards' || !['edit','basic_edit'].some(name=>url.searchParams.has(name))) return;
    const state = remember(); url.searchParams.set('section', state.section);
    if (state.filter) url.searchParams.set('basic_post', state.filter);
    link.href = url.toString();
  }, true);
  document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;
    const url = new URL(event.submitter?.getAttribute('formaction') || form.action, location.href);
    if (url.origin !== location.origin || url.searchParams.get('page') !== 'standards') return;
    const state = remember();
    for (const [name,value] of [['return_section',state.section],['return_basic_post',state.filter]]) {
      let input = form.elements.namedItem(name); if (!input) { input=document.createElement('input');input.type='hidden';input.name=name;form.append(input); } input.value=value;
    }
  });
  if (!saved || Date.now()-saved.time > 5*60*1000 || !['conditions','suzhi'].includes(saved.section)) return;
  document.querySelector(`[data-standard-show="${saved.section}"]`)?.click();
  for (const [scope,value] of saved.searches || []) {
    const input=[...document.querySelectorAll('[data-standard-search]')].find(input=>input.dataset.standardSearch===scope);
    if (input) { input.value=value; input.dispatchEvent(new Event('input',{bubbles:true})); }
  }
  const ordering=document.querySelector('.condition-order'); if (ordering) ordering.open=!!saved.orderingOpen;
  document.querySelectorAll('.table-pagination').forEach((nav,index)=>nav.dispatchEvent(new CustomEvent('restore-page',{detail:saved.pages?.[index] || 1})));
  const restore = () => window.scrollTo({top:Math.max(0,Number(saved.y)||0),left:0,behavior:'instant'});
  requestAnimationFrame(()=>requestAnimationFrame(restore));
  if (document.readyState !== 'complete') window.addEventListener('load',restore,{once:true});
}