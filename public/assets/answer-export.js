/* Render the same screen DOM and CSS; no separate PDF typography/template. */
(() => {
  const button = document.getElementById('answer-export-pdf');
  const source = document.querySelector('.answer-document');
  const status = document.getElementById('answer-export-status');
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
      // Use the actual preview width, including responsive layout. Keeping the
      // PDF's CSS pixel width identical avoids reflow and font substitution.
      const width = source.getBoundingClientRect().width;
      const clone = source.cloneNode(true);
      clone.style.width = width + 'px';
      clone.style.maxWidth = 'none';
      clone.style.margin = '0';
      const padding = getComputedStyle(source);
      clone.style.paddingTop = '0';
      clone.style.paddingBottom = '0';
      staging = document.createElement('div');
      staging.style.cssText = 'position:fixed;left:-100000px;top:0;pointer-events:none';
      staging.style.width = width + 'px';
      staging.append(clone);
      document.body.append(staging);
      // Long answers can exceed one page. Preserve the browser's existing
      // line wraps, then let the paginator break between whole text lines.
      clone.querySelectorAll('.answer-preview-value p').forEach(paragraph => {
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
      // Keep each section heading with its first question on the next page.
      clone.querySelectorAll('.answer-preview-section').forEach((section, index) => {
        const heading = section.firstElementChild;
        const first = heading && heading.nextElementSibling;
        if (!first) return;
        const original = source.querySelectorAll('.answer-preview-section')[index].children[1];
        first.style.paddingBottom = getComputedStyle(original).paddingBottom;
        const group = document.createElement('div');
        group.className = 'answer-pdf-section-start';
        section.insertBefore(group, heading);
        group.append(heading, first);
      });
      await html2pdf().set({
        filename: button.dataset.filename.replace(/[\\/:*?"<>|\x00-\x1f]/g, '_'),
        margin: [parseFloat(padding.paddingTop) * 0.75, 0,
          parseFloat(padding.paddingBottom) * 0.75, 0],
        enableLinks: false,
        image: {type: 'png'},
        html2canvas: {scale: 2, backgroundColor: '#ffffff', logging: false,
          windowWidth: window.innerWidth, scrollY: 0},
        jsPDF: {unit: 'pt', format: [width * 0.75, width * 0.75 * 297 / 210],
          orientation: 'portrait', compress: true},
        pagebreak: {mode: ['css'], avoid: ['.answer-pdf-text-line', '.answer-pdf-section-start', '.answer-preview-heading',
          '.answer-score-summary', '.answer-preview-profile', '.answer-question-group',
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
      }).save();
      status.textContent = 'PDF 已生成';
    } catch (error) {
      console.error('Answer PDF export failed', error);
      status.textContent = '导出失败，请刷新页面后重试。';
    } finally {
      if (staging) staging.remove();
      button.disabled = false;
      button.textContent = '导出 PDF';
    }
  }
  button.addEventListener('click', download);
  if (button.dataset.autoExport === '1') download();
})();

