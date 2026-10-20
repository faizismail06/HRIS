const dashboard = document.querySelector('[data-dashboard]');

if (dashboard) {
    const controls = dashboard.querySelector('[data-filter-controls]');
    const rows = [...dashboard.querySelectorAll('[data-task]')];
    const categories = [...dashboard.querySelectorAll('[data-category]')].filter((item) => item.tagName === 'BUTTON');
    const search = dashboard.querySelector('[data-task-search]');
    const branch = dashboard.querySelector('[data-branch-filter]');
    const urgent = dashboard.querySelector('[data-urgent-filter]');
    const resultCount = dashboard.querySelector('[data-result-count]');
    const emptyState = dashboard.querySelector('[data-empty-state]');
    let category = 'all';

    // Progressive enhancement: fixtures and native disclosures work without JS.
    controls.hidden = false;

    function filterTasks() {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        let visible = 0;

        for (const row of rows) {
            const matches = (category === 'all' || row.dataset.category === category)
                && (branch.value === 'all' || row.dataset.branch === branch.value)
                && (!urgent.checked || row.dataset.priority === 'urgent')
                && row.dataset.search.toLocaleLowerCase('id-ID').includes(query);
            row.hidden = !matches;
            if (!matches) row.open = false;
            if (matches) visible += 1;
        }

        resultCount.textContent = `Menampilkan ${visible} dari ${rows.length} tugas`;
        emptyState.hidden = visible !== 0;
        dashboard.querySelector('.task-columns').hidden = visible === 0;
    }

    categories.forEach((button) => button.addEventListener('click', () => {
        category = button.dataset.category;
        categories.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        filterTasks();
    }));
    search.addEventListener('input', filterTasks);
    branch.addEventListener('change', filterTasks);
    urgent.addEventListener('change', filterTasks);
    dashboard.querySelector('[data-reset-filters]').addEventListener('click', () => {
        category = 'all';
        search.value = '';
        branch.value = 'all';
        urgent.checked = false;
        categories.forEach((item) => item.setAttribute('aria-pressed', String(item.dataset.category === 'all')));
        filterTasks();
        search.focus();
    });

    const navigationLinks = [...dashboard.querySelectorAll('.workspace-nav a')];

    function syncCurrentSection() {
        const hash = window.location.hash || '#ringkasan';
        navigationLinks.forEach((item) => {
            const current = item.getAttribute('href') === hash;
            item.classList.toggle('is-current', current);
            if (current) item.setAttribute('aria-current', 'location');
            else item.removeAttribute('aria-current');
        });
    }

    syncCurrentSection();
    window.addEventListener('hashchange', syncCurrentSection);

    dashboard.querySelectorAll('.workspace-nav a, .brand').forEach((link) => link.addEventListener('click', () => {
        const target = dashboard.querySelector(link.getAttribute('href'));
        target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
    }));
}
