(() => {
  const thaiDigits = '๐๑๒๓๔๕๖๗๘๙';
  const toArabicDigits = (value) => String(value ?? '').replace(/[๐-๙]/g, (digit) => String(thaiDigits.indexOf(digit)));

  function convert(root) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let node;
    while ((node = walker.nextNode())) {
      if (!node.parentElement?.closest('script, style, textarea')) nodes.push(node);
    }
    nodes.forEach((textNode) => { textNode.nodeValue = toArabicDigits(textNode.nodeValue); });
    root.querySelectorAll?.('input, textarea, [placeholder]').forEach((field) => {
      if ('value' in field) field.value = toArabicDigits(field.value);
      if (field.hasAttribute('placeholder')) field.setAttribute('placeholder', toArabicDigits(field.getAttribute('placeholder')));
    });
  }

  function start() {
    convert(document.body);
    new MutationObserver(() => convert(document.body)).observe(document.body, { childList: true, subtree: true, characterData: true });
  }

  if (document.body) start();
  else document.addEventListener('DOMContentLoaded', start, { once: true });
})();
