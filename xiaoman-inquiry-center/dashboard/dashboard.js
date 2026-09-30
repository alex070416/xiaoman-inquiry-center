(function () {
    'use strict';
    const root = document.querySelector('.uid-dashboard');
    if (!root) return;
    // WordPress moves third-party notices after the first heading. Keep them accessible
    // without allowing that global behavior to split the dashboard header layout.
    function tidyNotices() {
        const notices = Array.from(root.querySelectorAll('.uid-header .notice, .uid-header .updated, .uid-header .error'));
        if (!notices.length) return;
        let details = root.querySelector('.uid-admin-notices');
        if (!details) {
            details = document.createElement('details');
            details.className = 'uid-admin-notices';
            details.appendChild(document.createElement('summary'));
            root.appendChild(details);
        }
        notices.forEach(notice => details.appendChild(notice));
        details.querySelector('summary').textContent = '系统提示（' + (details.children.length - 1) + '）';
    }
    if (document.readyState === 'complete') tidyNotices();
    else window.addEventListener('load', tidyNotices, { once: true });
    const header = root.querySelector('.uid-header');
    if (header) new MutationObserver(tidyNotices).observe(header, { childList: true, subtree: true });
    const toggle = root.querySelector('.uid-advanced-toggle');
    if (toggle) toggle.addEventListener('click', function () {
        const panel = root.querySelector('#uid-advanced');
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        panel.hidden = expanded;
    });
    const bulk = root.querySelector('.uid-bulk');
    if (!bulk) return;
    const all = bulk.querySelector('.uid-select-all');
    const items = Array.from(bulk.querySelectorAll('input[name="ids[]"]'));
    const summary = bulk.querySelector('.uid-selected');
    function update() {
        const count = items.filter(item => item.checked).length;
        all.checked = items.length > 0 && count === items.length;
        all.indeterminate = count > 0 && count < items.length;
        summary.textContent = '已选择 ' + count + ' 条';
    }
    all.addEventListener('change', function () { items.forEach(item => { item.checked = all.checked; }); update(); });
    items.forEach(item => item.addEventListener('change', update));
    bulk.addEventListener('submit', function (event) {
        const count = items.filter(item => item.checked).length;
        if (!count) { event.preventDefault(); summary.textContent = '请先勾选询盘。'; return; }
        const action = event.submitter && event.submitter.value;
        // Read/unread/spam/unspam act immediately, as requested. Only destructive admin actions confirm.
        if (action === 'delete' && !window.confirm('永久删除选中的 ' + count + ' 条网站询盘？此操作无法恢复，小满已有线索保留。')) event.preventDefault();
        if (action === 'trash' && !window.confirm('将选中的 ' + count + ' 条询盘移入回收站？未同步任务会暂停，小满已有线索保留。')) event.preventDefault();
    });
}());
