document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('globalSearchInput');
    const results = document.getElementById('globalSearchResults');
    if (!input?.dataset.documentSearchUrl || !results) return;
    let timer;
    let controller;
    let generation = 0;
    function close() {
        results.classList.remove('show'); input.setAttribute('aria-expanded', 'false');
    }
    function note(text) {
        const element = document.createElement('div'); element.className = 'px-3 py-3 text-meta'; element.textContent = text; results.appendChild(element);
    }
    input.addEventListener('input', () => {
        clearTimeout(timer); controller?.abort();
        const current = ++generation;
        const query = input.value.trim();
        results.replaceChildren(); close();
        if (query.length < 2) return;
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const url = new URL(input.dataset.documentSearchUrl); url.searchParams.set('q', query);
                const response = await fetch(url, {signal: controller.signal, headers: {Accept: 'application/json'}});
                if (!response.ok || response.redirected) throw new Error('Search unavailable');
                const data = await response.json();
                if (current !== generation) return;
                results.replaceChildren();
                const groups = [['employees', 'Employees'], ['documents', 'Documents'], ['tasks', 'Tasks']];
                let found = false;
                groups.forEach(([key, heading]) => {
                    const items = data[key] || [];
                    if (!items.length) return;
                    found = true;
                    const label = document.createElement('h6'); label.className = 'dropdown-header'; label.textContent = heading; results.appendChild(label);
                    items.forEach(item => {
                        const link = window.document.createElement('a'); link.className = 'dropdown-item'; link.href = item.url; link.textContent = item.title; results.appendChild(link);
                    });
                });
                if (!found) note('No matching results.');
                results.classList.add('show'); input.setAttribute('aria-expanded', 'true');
            } catch (error) {
                if (error.name === 'AbortError' || current !== generation) return;
                results.replaceChildren(); note('Document search is unavailable. Please try again.');
                results.classList.add('show'); input.setAttribute('aria-expanded', 'true');
            }
        }, 200);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') { ++generation; clearTimeout(timer); controller?.abort(); close(); }
        if (event.key === 'ArrowDown') { event.preventDefault(); results.querySelector('a')?.focus(); }
    });
    results.addEventListener('keydown', event => {
        const links = [...results.querySelectorAll('a')]; const index = links.indexOf(document.activeElement);
        if (event.key === 'ArrowDown') { event.preventDefault(); links[(index + 1) % links.length]?.focus(); }
        if (event.key === 'ArrowUp') { event.preventDefault(); if (index <= 0) input.focus(); else links[index - 1].focus(); }
        if (event.key === 'Escape') { close(); input.focus(); }
    });
    document.addEventListener('click', event => {
        if (event.target !== input && !results.contains(event.target)) { ++generation; clearTimeout(timer); controller?.abort(); close(); }
    });
});
