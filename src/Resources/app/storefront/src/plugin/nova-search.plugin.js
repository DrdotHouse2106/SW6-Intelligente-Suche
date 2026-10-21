const { PluginBaseClass } = window;

/**
 * Suchfenster im Vollbild – öffnet sich beim Klick ins Suchfeld.
 *
 * Ohne Suchbegriff: beliebte Suchanfragen und Highlight-Produkte.
 * Mit Suchbegriff:  Filter (Marken, Preis), Sortierung, passende Kategorien und
 *                   Produktkarten, die beim Scrollen nachgeladen werden.
 *
 * Alle Inhalte kommen als HTML vom Server (Route frontend.nova-search.instant).
 * Anfragen werden verzögert, veraltete abgebrochen und Antworten zwischengespeichert.
 */
export default class NovaSearchPlugin extends PluginBaseClass {
    static options = {
        url: '',
        searchUrl: '/search',
        minChars: 3,
        debounce: 180,
        inputSelector: 'input[type=search]',
        toggleButtonSelector: '.js-search-toggle-btn',
        focusableSelector: '.nova-search-focusable',
    };

    init() {
        this._headerInput = this.el.querySelector(this.options.inputSelector);
        this._template = this.el.querySelector('[data-nova-search-template]');
        if (!this._headerInput || !this._template || !this.options.url) {
            return;
        }

        this._cache = new Map();
        this._request = null;
        this._timer = null;
        this._state = this._emptyState('');

        this._restoreSearchTerm();
        this._registerTriggers();
    }

    _emptyState(term) {
        return { term, manufacturers: new Set(), min: null, max: null, order: '', page: 1 };
    }

    /* ---------- Öffnen über das Suchfeld im Header ---------- */

    _registerTriggers() {
        const openFromHeader = (event) => {
            event.preventDefault();
            this.open(this._headerInput.value);
        };

        this._headerInput.addEventListener('focus', openFromHeader);
        this._headerInput.addEventListener('click', openFromHeader);

        // Handy: Lupen-Button öffnet direkt das Fenster statt das Suchfeld aufzuklappen
        document.addEventListener('click', (event) => {
            const toggle = event.target.closest(this.options.toggleButtonSelector);
            if (toggle) {
                event.preventDefault();
                event.stopImmediatePropagation();
                this.open(this._headerInput.value);
            }
        }, true);
    }

    /** Suchbegriff auf der Ergebnisseite wieder ins Feld schreiben (Header kommt per ESI) */
    _restoreSearchTerm() {
        if (this._headerInput.value === '' && window.activeRoute === 'frontend.search.page') {
            this._headerInput.value = new URLSearchParams(window.location.search).get('search') || '';
        }
    }

    /* ---------- Fenster ---------- */

    _build() {
        const fragment = this._template.content.cloneNode(true);
        this._backdrop = fragment.querySelector('.nova-search-backdrop');
        this._layer = fragment.querySelector('.nova-search-layer');
        this._input = fragment.querySelector('.nova-search-input');
        this._body = fragment.querySelector('.nova-search-body');
        document.body.append(fragment);

        this._input.addEventListener('input', () => this._onInput());
        this._input.addEventListener('keydown', (event) => this._onInputKeydown(event));
        this._layer.querySelector('.nova-search-close').addEventListener('click', () => this.close());
        this._backdrop.addEventListener('click', () => this.close());
        this._layer.addEventListener('keydown', (event) => this._onLayerKeydown(event));

        // Esc schließt immer – auch wenn der Fokus gerade nicht im Fenster liegt (z. B. nach einer Auswahlliste)
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.isOpen() && !this._layer.contains(event.target)) {
                this.close();
            }
        });
        this._body.addEventListener('click', (event) => this._onBodyClick(event));
        this._body.addEventListener('change', (event) => this._onBodyChange(event));
        this._body.addEventListener('input', (event) => this._onBodyInput(event));

        // Weitere Produkte laden, sobald das Ende der Liste sichtbar wird
        this._observer = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    this._loadMore();
                }
            }, { root: this._layer, rootMargin: '400px 0px' })
            : null;
    }

    isOpen() {
        return !!this._layer && this._layer.classList.contains('is-open');
    }

    open(term = '') {
        if (!this._layer) {
            this._build();
        }

        if (!this.isOpen()) {
            this._lastFocus = document.activeElement;
            this._layer.classList.add('is-open');
            this._backdrop.classList.add('is-open');
            document.documentElement.classList.add('nova-search-locked');
        }

        this._input.value = term.trim();
        this._input.focus();
        this._state = this._emptyState(this._input.value.trim());
        this._load();
    }

    close() {
        if (!this.isOpen()) {
            return;
        }

        this._abort();
        this._layer.classList.remove('is-open');
        this._backdrop.classList.remove('is-open');
        document.documentElement.classList.remove('nova-search-locked');
        this._headerInput.value = this._input.value;

        // Fokus nicht ins Header-Suchfeld zurückgeben – das würde das Fenster sofort wieder öffnen
        if (this._lastFocus && this._lastFocus !== this._headerInput && document.contains(this._lastFocus)) {
            this._lastFocus.focus();
        }
    }

    /* ---------- Eingabe ---------- */

    _onInput() {
        window.clearTimeout(this._timer);
        const term = this._input.value.trim();

        // Neuer Suchbegriff → Filter zurücksetzen
        this._state = this._emptyState(term);
        this._timer = window.setTimeout(() => this._load(), this.options.debounce);
    }

    _onInputKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const term = this._input.value.trim();
            if (term.length >= this.options.minChars) {
                window.location.href = `${this.options.searchUrl}?search=${encodeURIComponent(term)}`;
            }
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            this._focusables()[0]?.focus();
        }
    }

    _onLayerKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }

        // Fokus im Fenster halten
        if (event.key === 'Tab') {
            const items = [this._input, ...this._focusables(), this._layer.querySelector('.nova-search-close')];
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
            return;
        }

        if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && event.target.matches(this.options.focusableSelector)
            && event.target.tagName !== 'SELECT' && event.target.type !== 'range' && event.target.type !== 'number') {
            event.preventDefault();
            const items = this._focusables();
            const index = items.indexOf(event.target) + (event.key === 'ArrowDown' ? 1 : -1);
            (index < 0 ? this._input : items[Math.min(index, items.length - 1)]).focus();
        }
    }

    _focusables() {
        return [...this._body.querySelectorAll(this.options.focusableSelector)];
    }

    /* ---------- Filter, Sortierung, Startansicht ---------- */

    _onBodyClick(event) {
        const target = event.target;

        const term = target.closest('[data-nova-search-term]');
        if (term) {
            this._input.value = term.getAttribute('data-nova-search-term');
            this._state = this._emptyState(this._input.value);
            this._input.focus();
            this._load();
            return;
        }

        const slide = target.closest('[data-nova-search-slide]');
        if (slide) {
            const track = slide.parentElement.querySelector('.nova-search-slider-track');
            track.scrollBy({ left: Number(slide.getAttribute('data-nova-search-slide')) * track.clientWidth * 0.8, behavior: 'smooth' });
            return;
        }

        if (target.closest('[data-nova-search-reset]')) {
            this._state = this._emptyState(this._state.term);
            this._load();
            return;
        }

        if (target.closest('[data-nova-search-expand]')) {
            target.closest('.nova-search-facet').querySelector('.is-collapsed')?.classList.remove('is-collapsed');
            target.remove();
            return;
        }

        const filterToggle = target.closest('[data-nova-search-filter-toggle]');
        if (filterToggle) {
            const open = this._body.querySelector('.nova-search-layout').classList.toggle('show-filters');
            filterToggle.setAttribute('aria-expanded', String(open));
            return;
        }

        if (target.closest('[data-nova-search-loadmore]')) {
            this._loadMore();
        }
    }

    _onBodyChange(event) {
        const target = event.target;

        if (target.matches('[data-nova-search-manufacturer]')) {
            target.checked ? this._state.manufacturers.add(target.value) : this._state.manufacturers.delete(target.value);
            this._reload();
        } else if (target.matches('[data-nova-search-sort]')) {
            this._state.order = target.value;
            this._reload();
        } else if (target.matches('[data-nova-search-range], [data-nova-search-price-input]')) {
            this._applyPrice(target.closest('[data-nova-search-price]'));
        }
    }

    /** Schieberegler und Eingabefelder für den Preis synchron halten */
    _onBodyInput(event) {
        const target = event.target;
        if (!target.matches('[data-nova-search-range]')) {
            return;
        }

        const box = target.closest('[data-nova-search-price]');
        const [from, to] = box.querySelectorAll('[data-nova-search-range]');
        if (Number(from.value) > Number(to.value)) {
            target === from ? (from.value = to.value) : (to.value = from.value);
        }
        box.querySelector('[data-nova-search-price-input="from"]').value = from.value;
        box.querySelector('[data-nova-search-price-input="to"]').value = to.value;
    }

    _applyPrice(box) {
        const lower = Number(box.getAttribute('data-min'));
        const upper = Number(box.getAttribute('data-max'));
        let from = Number(box.querySelector('[data-nova-search-price-input="from"]').value);
        let to = Number(box.querySelector('[data-nova-search-price-input="to"]').value);

        if (from > to) {
            [from, to] = [to, from];
        }

        this._state.min = from > lower ? from : null;
        this._state.max = to < upper ? to : null;
        this._reload();
    }

    _reload() {
        this._state.page = 1;
        this._load();
    }

    /* ---------- Laden ---------- */

    _query(state) {
        const params = new URLSearchParams();
        if (state.term.length >= this.options.minChars) {
            params.set('search', state.term);
            if (state.manufacturers.size) params.set('manufacturer', [...state.manufacturers].sort().join('|'));
            if (state.min !== null) params.set('min-price', state.min);
            if (state.max !== null) params.set('max-price', state.max);
            if (state.order) params.set('order', state.order);
            if (state.page > 1) params.set('p', state.page);
        }
        return params.toString();
    }

    async _fetch(query) {
        if (this._cache.has(query)) {
            return this._cache.get(query);
        }

        this._abort();
        this._request = new AbortController();
        this._layer.classList.add('is-loading');

        try {
            const response = await fetch(`${this.options.url}?${query}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: this._request.signal,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const html = await response.text();
            this._cache.set(query, html);
            return html;
        } finally {
            this._layer.classList.remove('is-loading');
        }
    }

    async _load() {
        const query = this._query(this._state);

        try {
            const html = await this._fetch(query);
            // Inzwischen weitergetippt? Dann diese Antwort verwerfen
            if (query !== this._query(this._state)) {
                return;
            }
            const filtersOpen = this._body.querySelector('.nova-search-layout.show-filters') !== null;
            this._body.innerHTML = html;
            if (filtersOpen) {
                this._body.querySelector('.nova-search-layout')?.classList.add('show-filters');
                this._body.querySelector('[data-nova-search-filter-toggle]')?.setAttribute('aria-expanded', 'true');
            }
            this._afterRender();
        } catch (error) {
            if (error.name !== 'AbortError') {
                this._body.innerHTML = '';
            }
        }
    }

    async _loadMore() {
        const grid = this._body.querySelector('[data-nova-search-grid]');
        if (!grid || grid.getAttribute('data-nova-search-has-more') !== '1' || this._loadingMore) {
            return;
        }

        this._loadingMore = true;
        const state = { ...this._state, page: this._state.page + 1 };
        const query = this._query(state);

        try {
            const html = await this._fetch(query);
            if (this._query({ ...this._state, page: state.page }) !== query) {
                return;
            }
            const page = document.createElement('div');
            page.innerHTML = html;
            const list = page.querySelector('.nova-search-page');
            grid.append(...list.children);
            grid.setAttribute('data-nova-search-has-more', list.getAttribute('data-nova-search-has-more'));
            this._state.page = state.page;
            if (list.getAttribute('data-nova-search-has-more') !== '1') {
                this._body.querySelector('[data-nova-search-sentinel]')?.remove();
            }
            this._highlight(grid);
        } catch (error) {
            // Nachladen fehlgeschlagen – Button bleibt zum erneuten Versuch stehen
        } finally {
            this._loadingMore = false;
        }
    }

    _afterRender() {
        this._highlight(this._body);
        this._observer?.disconnect();
        const sentinel = this._body.querySelector('[data-nova-search-sentinel]');
        if (sentinel && this._observer) {
            this._observer.observe(sentinel);
        }
        this.$emitter.publish('novaSearch/render', { term: this._state.term, body: this._body });
    }

    _abort() {
        if (this._request) {
            this._request.abort();
            this._request = null;
        }
    }

    /** Suchwörter fett hervorheben (ohne innerHTML – kein XSS-Risiko) */
    _highlight(root) {
        const words = this._state.term
            .split(/\s+/)
            .filter((word) => word.length >= 2)
            .map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        if (words.length === 0) {
            return;
        }

        const pattern = new RegExp(`(${words.join('|')})`, 'gi');
        root.querySelectorAll('.nova-search-hl:not([data-hl])').forEach((element) => {
            element.setAttribute('data-hl', '');
            const parts = element.textContent.split(pattern);
            if (parts.length === 1) {
                return;
            }
            element.textContent = '';
            parts.forEach((part, index) => {
                if (index % 2 === 1) {
                    const mark = document.createElement('mark');
                    mark.textContent = part;
                    element.append(mark);
                } else if (part) {
                    element.append(part);
                }
            });
        });
    }
}
