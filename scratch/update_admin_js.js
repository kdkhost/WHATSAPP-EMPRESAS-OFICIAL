const fs = require('fs');
let code = fs.readFileSync('public/assets/js/admin.js', 'utf8');
const appendCode = `
    // ============================================================
    // 8) TOOLTIPS GLOBAIS (Bootstrap 5)
    // ============================================================
    function initGlobalTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;

        function applyToNode(node) {
            if (node.nodeType !== 1) return;
            if (node.hasAttribute('title') && node.getAttribute('title').trim() !== '' && !node.hasAttribute('data-bs-toggle') && !node.classList.contains('swal2-input') && !node.classList.contains('swal2-select') && !node.classList.contains('swal2-textarea')) {
                node.setAttribute('data-bs-toggle', 'tooltip');
                try { new bootstrap.Tooltip(node, { boundary: document.body }); } catch(e) {}
            }
            const children = node.querySelectorAll('[title]:not([data-bs-toggle="tooltip"]):not(.swal2-input):not(.swal2-select):not(.swal2-textarea)');
            children.forEach(el => {
                if (el.getAttribute('title').trim() !== '') {
                    el.setAttribute('data-bs-toggle', 'tooltip');
                    try { new bootstrap.Tooltip(el, { boundary: document.body }); } catch(e) {}
                }
            });
        }

        applyToNode(document.body);

        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(applyToNode);
                } else if (mutation.type === 'attributes' && mutation.attributeName === 'title') {
                    const el = mutation.target;
                    if (el.hasAttribute('title') && el.getAttribute('title').trim() !== '' && !el.classList.contains('swal2-input') && !el.classList.contains('swal2-select') && !el.classList.contains('swal2-textarea')) {
                        if (!el.hasAttribute('data-bs-toggle')) {
                            el.setAttribute('data-bs-toggle', 'tooltip');
                            try { new bootstrap.Tooltip(el, { boundary: document.body }); } catch(e) {}
                        } else {
                            const tip = bootstrap.Tooltip.getInstance(el);
                            if (tip) {
                                el.setAttribute('data-bs-original-title', el.getAttribute('title'));
                                el.removeAttribute('title');
                            }
                        }
                    }
                }
            });
        });

        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['title'] });
    }

    document.addEventListener('DOMContentLoaded', initGlobalTooltips);
})();
`;
code = code.replace(/}\s*};\s*}\)\(\);\s*$/, '} };' + appendCode);
fs.writeFileSync('public/assets/js/admin.js', code);
console.log('Appended successfully');
