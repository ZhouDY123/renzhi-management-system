/* Shared, sanitized Markdown rendering for practical questions and editor preview. */
window.practicalImageSize = {
  read(src) {
    const match = /#practical-size=(\d{1,4})x(\d{1,4})$/.exec(src || '');
    return match ? { width: Math.max(32, Math.min(2000, +match[1])), height: Math.max(32, Math.min(2000, +match[2])) } : null;
  },
  apply(img, size) {
    const styles = size ? { width: `${size.width}px`, height: 'auto', aspectRatio: `${size.width} / ${size.height}`, maxWidth: '100%', maxHeight: 'none', objectFit: 'fill' } : { width: '', height: '', aspectRatio: '', maxWidth: '', maxHeight: '', objectFit: '' };
    for (const [key, value] of Object.entries(styles)) if (img.style[key] !== value) img.style[key] = value;
  }
};
window.practicalMarkdown = (element, source) => {
  if (!window.marked || !window.DOMPurify) { element.textContent = source; return; }
  const html = marked.parse(source, { gfm: true, breaks: true, async: false });
  element.innerHTML = DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['p','br','strong','em','del','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','pre','code','hr','a','img','table','thead','tbody','tr','th','td'],
    ALLOWED_ATTR: ['href','src','alt','title','start'],
    ALLOW_DATA_ATTR: false
  });
  element.querySelectorAll('a').forEach(a => {
    if (!/^(https?:\/\/|mailto:|\/[^/]|#)/i.test(a.getAttribute('href') || '')) a.removeAttribute('href');
    a.rel = 'noopener noreferrer';
  });
  element.querySelectorAll('img').forEach(img => {
    const src = img.getAttribute('src') || '';
    if (!/^(https?:\/\/|\/[^/])/i.test(src)) { img.replaceWith(document.createTextNode(img.alt)); return; }
    img.loading = 'lazy'; img.referrerPolicy = 'no-referrer';
    practicalImageSize.apply(img, practicalImageSize.read(src));
  });
};
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-markdown-source]').forEach(el => practicalMarkdown(el, el.dataset.markdownSource));
});
