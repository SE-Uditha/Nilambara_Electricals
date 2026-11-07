// Progressive enhancement: the existing select remains the submitted source of truth.
let sequence = 0;

function enhanceSelect(select) {
    if (select.dataset.searchEnhanced || select.multiple) return;
    select.dataset.searchEnhanced = 'true';
    const id = `search-select-${++sequence}`;
    const wrapper = document.createElement('div');
    wrapper.className = 'search-select';
    const input = document.createElement('input');
    input.type = 'search';
    input.id = `${id}-input`;
    input.className = 'form-input';
    input.autocomplete = 'off';
    input.placeholder = select.dataset.searchPlaceholder || 'Type at least 2 characters to search';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-haspopup', 'listbox');
    input.setAttribute('aria-controls', `${id}-results`);
    input.setAttribute('aria-expanded', 'false');
    const label = select.labels?.[0];
    input.setAttribute('aria-label', select.dataset.searchLabel || label?.textContent.trim().split('\n')[0] || 'Search and select');
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'search-select-toggle';
    toggle.textContent = '⌄';
    toggle.setAttribute('aria-label', select.dataset.searchBrowseLabel || 'Browse options');
    toggle.setAttribute('aria-controls', `${id}-results`);
    toggle.setAttribute('aria-expanded', 'false');
    const list = document.createElement('div');
    list.id = `${id}-results`;
    list.className = 'search-select-results';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    const status = document.createElement('p');
    status.className = 'search-select-status';
    status.setAttribute('role', 'status');
    select.before(wrapper);
    wrapper.append(input, toggle, select, list, status);
    select.classList.add('search-select-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    if (label?.htmlFor === select.id && select.id) label.htmlFor = input.id;
    if (!Array.from(select.options).some(option => option.value === '')) {
        const empty = new Option('', '');
        empty.hidden = true;
        select.append(empty);
    }
    let active = -1;
    let matches = [];
    const minimum = Number(select.dataset.searchMinLength || 2);
    const sync = () => {
        input.value = select.value ? select.selectedOptions[0]?.textContent.trim() || '' : '';
        input.disabled = toggle.disabled = select.disabled;
        input.setAttribute('aria-required', String(select.required));
    };
    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const choose = option => {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        sync();
        status.textContent = '';
        close();
        input.focus();
    };
    const highlight = index => {
        active = Math.max(0, Math.min(index, matches.length - 1));
        Array.from(list.querySelectorAll('[role=option]')).forEach((button, i) => {
            button.setAttribute('aria-selected', String(i === active));
            if (i === active) {
                input.setAttribute('aria-activedescendant', button.id);
                button.scrollIntoView({ block: 'nearest' });
            }
        });
    };
    const render = (browse = false) => {
        const term = input.value.trim().toLocaleLowerCase();
        if (!browse && term.length < minimum) {
            close();
            status.textContent = term ? (select.dataset.searchMinMessage || `Type at least ${minimum} characters to search.`) : '';
            return;
        }
        matches = Array.from(select.options).filter(option => option.value && !option.disabled && !option.hidden
            && (browse || option.textContent.toLocaleLowerCase().includes(term))).slice(0, 60);
        list.replaceChildren();
        matches.forEach((option, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.tabIndex = -1;
            button.id = `${id}-option-${index}`;
            button.textContent = option.textContent.trim();
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false');
            button.addEventListener('mousedown', event => event.preventDefault());
            button.addEventListener('click', () => choose(option));
            button.addEventListener('mouseenter', () => highlight(index));
            list.append(button);
        });
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.textContent = select.dataset.searchNoResults || 'No results found.';
            list.append(empty);
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-expanded', 'true');
        status.textContent = matches.length === 60 ? (select.dataset.searchMoreMessage || 'Showing the first 60 matches. Type more to narrow the search.') : '';
        if (matches.length) highlight(0);
    };
    input.addEventListener('input', () => {
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        render();
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) render(true);
            else if (matches.length) highlight(active + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            if (matches[active]) choose(matches[active]);
        } else if (event.key === 'Escape' || event.key === 'Tab') {
            if (event.key === 'Escape' && !list.hidden) { event.preventDefault(); event.stopPropagation(); }
            close();
        }
    });
    toggle.addEventListener('click', () => { if (list.hidden) render(true); else close(); input.focus(); });
    wrapper.addEventListener('focusout', () => setTimeout(() => { if (!wrapper.contains(document.activeElement)) close(); }, 0));
    document.addEventListener('pointerdown', event => { if (!wrapper.contains(event.target)) close(); });
    select.addEventListener('change', event => { if (event.isTrusted) sync(); });
    select.addEventListener('invalid', event => { event.preventDefault(); input.focus(); status.textContent = select.dataset.searchRequiredMessage || 'Select a matching option from the list.'; });
    select.form?.addEventListener('reset', () => setTimeout(() => { sync(); close(); status.textContent = ''; }, 0));
    new MutationObserver(sync).observe(select, { attributes: true, attributeFilter: ['disabled'], childList: true });
    sync();
}

// Adds the same keyboard behavior to existing remote lookup result lists.
export function keyboardResults(input, list) {
    if (!input || !list || input.dataset.keyboardResults) return;
    input.dataset.keyboardResults = 'true';
    if (!list.id) list.id = `lookup-results-${++sequence}`;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    list.setAttribute('role', 'listbox');
    let index = -1;
    const options = () => Array.from(list.querySelectorAll('button')).filter(button => !button.disabled);
    const expanded = () => !list.hidden && !list.classList.contains('hidden') && list.childElementCount > 0;
    const refresh = () => {
        index = -1;
        input.setAttribute('aria-expanded', String(expanded()));
        input.removeAttribute('aria-activedescendant');
        options().forEach((option, i) => {
            option.id ||= `${list.id}-option-${i}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.tabIndex = -1;
        });
    };
    const close = () => { list.classList.add('hidden'); refresh(); };
    input.addEventListener('keydown', event => {
        const choices = options();
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && expanded() && choices.length) {
            event.preventDefault();
            index = Math.max(0, Math.min(index + (event.key === 'ArrowDown' ? 1 : -1), choices.length - 1));
            choices.forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
            input.setAttribute('aria-activedescendant', choices[index].id);
            choices[index].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && expanded()) {
            event.preventDefault();
            choices[index < 0 ? 0 : index]?.click();
        } else if (event.key === 'Escape' || event.key === 'Tab') {
            if (event.key === 'Escape' && expanded()) { event.preventDefault(); event.stopPropagation(); }
            close();
        }
    });
    list.addEventListener('mousedown', event => event.preventDefault());
    input.addEventListener('blur', () => setTimeout(close, 150));
    new MutationObserver(refresh).observe(list, { childList: true, attributes: true, attributeFilter: ['class', 'hidden'] });
    refresh();
}

function initialize(root = document) {
    root.querySelectorAll('select[data-searchable]').forEach(enhanceSelect);
    if (root.matches?.('select[data-searchable]')) enhanceSelect(root);
    keyboardResults(document.getElementById('customer-search'), document.getElementById('customer-results'));
    keyboardResults(document.getElementById('job-search'), document.getElementById('job-results'));
    root.querySelectorAll('[data-item-row]').forEach(row => keyboardResults(row.querySelector('.part-search'), row.querySelector('.part-results')));
}

initialize();
new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
    if (node instanceof Element) initialize(node);
}))).observe(document.body, { childList: true, subtree: true });
