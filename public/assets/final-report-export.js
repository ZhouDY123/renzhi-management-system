/* Render the same screen DOM and CSS; no separate PDF typography/template. */
(() => {
  const button = document.getElementById('final-report-export-pdf');
  const source = document.querySelector('#final-review-report');
  const status = document.getElementById('final-report-export-status');
  if (!button || !source) return;
  async function download() {
    if (button.disabled) return;
    button.disabled = true;
    button.textContent = '正在导出…';
    status.textContent = '';
    let staging;
    try {
      if (typeof html2pdf !== 'function') throw new Error('PDF library unavailable');
      await document.fonts.ready;
      // Keep the detail page's desktop typography and full table layout.
      // Use the report's full desktop layout even when exporting from a phone.
      const width = 1100;
      const clone = source.cloneNode(true);
      clone.style.width = width + 'px';
      clone.style.maxWidth = 'none';
      clone.style.margin = '0';
      clone.style.overflow = 'visible';
      clone.querySelector('.final-report-profile').style.padding = '28px 36px';
      clone.querySelector('.final-report-content').style.padding = '30px 36px';
      clone.querySelector('.final-report-metrics').style.gridTemplateColumns = 'repeat(4,minmax(0,1fr))';
      clone.querySelector('.final-report-nav').style.padding = '18px 36px';
      clone.querySelectorAll('.answer-preview-profile').forEach(el => el.style.gridTemplateColumns = 'repeat(3,minmax(0,1fr))');
      clone.querySelectorAll('.final-score-scroll').forEach(el => el.style.overflow = 'visible');
      clone.querySelectorAll('.final-score-table').forEach(el => { el.style.minWidth = '0'; el.style.tableLayout = 'fixed'; });
      clone.querySelectorAll('.final-score-table th,.final-score-table td').forEach(el => el.style.overflowWrap = 'anywhere');
      clone.style.paddingTop = '0';
      clone.style.paddingBottom = '0';
      staging = document.createElement('div');
      staging.style.cssText = 'position:fixed;left:-100000px;top:0;pointer-events:none';
      staging.style.width = width + 'px';
      staging.append(clone);
      document.body.append(staging);
      // Long answers can exceed one page. Preserve the browser's existing
      // line wraps, then let the paginator break between whole text lines.
      clone.querySelectorAll('.answer-preview-value p,.final-reviewer dd,.final-history-item p').forEach(paragraph => {
        if (paragraph.getBoundingClientRect().height >= 400 && [...paragraph.childNodes].every(node => node.nodeType === 3 || node.nodeName === 'BR')) {
          const plain = [...paragraph.childNodes].map(node => node.nodeName === 'BR' ? '\n' : node.textContent).join('');
          paragraph.textContent = plain; paragraph.style.whiteSpace = 'pre-wrap';
        }
        if (paragraph.getBoundingClientRect().height < 400 ||
            paragraph.childNodes.length !== 1 || paragraph.firstChild.nodeType !== 3) return;
        const text = paragraph.firstChild;
        const range = document.createRange();
        const lines = [];
        let top = null;
        for (let i = 0; i < text.length;) {
          const length = text.textContent.codePointAt(i) > 0xffff ? 2 : 1;
          range.setStart(text, i);
          range.setEnd(text, i + length);
          const rect = range.getBoundingClientRect();
          if (top === null || Math.abs(rect.top - top) > 2) {
            lines.push('');
            top = rect.top;
          }
          lines[lines.length - 1] += text.textContent.slice(i, i + length);
          i += length;
        }
        const lineHeight = getComputedStyle(paragraph).lineHeight;
        paragraph.replaceChildren(...lines.map(text => {
          const line = document.createElement('span');
          line.className = 'answer-pdf-text-line';
          line.style.cssText = 'display:block;white-space:pre;min-height:' + lineHeight;
          line.textContent = text.replace(/[\r\n]+$/, '');
          return line;
        }));
      });
      const freezeStyles = element => {
        const entries = [element, ...element.querySelectorAll('*')].map(node => {
          const css = getComputedStyle(node);
          return [node, [...css].filter(key => /^(font|line-height|letter-spacing|color$|background|border|padding|margin|white-space|word-break|overflow-wrap|text-)/.test(key)).map(key => `${key}:${css.getPropertyValue(key)};`).join('')];
        });
        entries.forEach(([node, css]) => node.style.cssText += ';' + css);
      };
      // Keep each section heading with its first question on the next page.
      clone.querySelectorAll('.answer-preview-section').forEach((section, index) => {
        const heading = section.firstElementChild;
        const first = heading && heading.nextElementSibling;
        if (!first) return;
        const original = source.querySelectorAll('.answer-preview-section')[index].children[1];
        first.style.paddingBottom = getComputedStyle(original).paddingBottom;
        const group = document.createElement('div');
        group.className = 'answer-pdf-section-start';
        freezeStyles(heading);freezeStyles(first);
        section.insertBefore(group, heading);
        group.append(heading, first);
      });
      clone.querySelectorAll('.final-report-section').forEach(section => {
        const heading = section.firstElementChild, next = heading?.nextElementSibling;
        if (!heading || !next) return;
        const group = document.createElement('div');group.className = 'answer-pdf-section-start';
        freezeStyles(heading);freezeStyles(next);
        section.insertBefore(group, heading);group.append(heading, next);
      });
      await html2pdf().set({
        filename: button.dataset.filename.replace(/[\\/:*?"<>|\x00-\x1f]/g, '_'),
        margin: [18, 0, 18, 0],
        enableLinks: false,
        image: {type: 'png'},
        html2canvas: {scale: 2, backgroundColor: '#ffffff', logging: false,
          windowWidth: 1440, scrollY: 0},
        jsPDF: {unit: 'pt', format: [width * 0.75, width * 0.75 * 297 / 210],
          orientation: 'portrait', compress: true},
        pagebreak: {mode: ['css'], avoid: ['.answer-pdf-text-line', '.answer-pdf-section-start', '.answer-preview-heading',
          '.final-report-profile', '.final-report-metrics', '.final-report-nav', '.final-score-table', '.final-score-table tr', '.final-reviewer', '.final-reviewer header', '.final-session-heading', '.answer-preview-profile', '.answer-question-group',
          '.answer-preview-question', 'h2', 'h3', '.answer-bonus-line']}
      }).from(clone).toCanvas().get('canvas').then(function(canvas) {
        // html2pdf keeps the pre-pagination container height; remove surplus
        // blank canvas at the end so long answers do not add empty PDF pages.
        const context = canvas.getContext('2d');
        let bottom = canvas.height;
        scan: for (let y = canvas.height - 1; y >= 0; y--) {
          const row = context.getImageData(0, y, canvas.width, 1).data;
          for (let x = 0; x < row.length; x += 4) {
            if (row[x + 3] && (row[x] < 245 || row[x + 1] < 245 || row[x + 2] < 245)) {
              bottom = Math.min(canvas.height, y + 1);
              break scan;
            }
          }
        }
        if (bottom === canvas.height) return;
        const trimmed = document.createElement('canvas');
        trimmed.width = canvas.width;
        trimmed.height = bottom;
        trimmed.getContext('2d').drawImage(canvas, 0, 0);
        return this.set({canvas: trimmed});
      }).toPdf().get('pdf').then(function(pdf) {
        pdf.setDisplayMode('fullwidth', 'continuous', 'UseNone');
        pdf.setPage(1);
      }).save();
      status.textContent = 'PDF 已生成';
    } catch (error) {
      console.error('Final report PDF export failed', error);
      status.textContent = '导出失败，请刷新页面后重试。';
    } finally {
      if (staging) staging.remove();
      button.disabled = false;
      button.textContent = '导出 PDF';
    }
  }
  button.addEventListener('click', download);
})();

