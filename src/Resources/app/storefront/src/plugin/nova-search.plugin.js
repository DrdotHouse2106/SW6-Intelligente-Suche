const { PluginBaseClass } = window;

/**
 * Sofortsuche: zeigt beim Tippen ein Ergebnisfenster mit Produkten, Kategorien
 * und Herstellerfilter unter dem Suchfeld.
 *
 * - Anfragen werden verzögert (debounce), veraltete Anfragen abgebrochen und
 *   Antworten im Browser zwischengespeichert.
 * - Bedienbar per Tastatur: ↓/↑ durch die Treffer, Esc schließt, Enter sucht.
 * - Übernimmt außerdem die Aufgaben von Shopwares Such-Plugin (Mindestlänge,
 *   Suchbegriff auf der Ergebnisseite, Schließen-Button, Fokus auf dem Handy).
 */
export default class NovaSearchPlugin extends PluginBaseClass {
    static options = {
        url: '',
        minChars: 3,
        debounce: 180,
        inputSelector: 'input[type=search]',
        closeButtonSelector: '.js-search-close-btn',
        toggleButtonSelector: '.js-search-toggle-btn',
        collapseSelector: '#searchCollapse',
        focusableSelector: '.nova-search-link, .nova-search-chip',
    };

    init() {
        this._input = this.el.querySelector(this.options.inputSelector);
        if (!this._input || !this.options.url) {
            return;
        }

        this._cache = new Map();
        this._manufacturers = new Set();
        this._request = null;
        this._timer = null;
        this._lastQuery = '';

        this._createPanel();
        this._restoreSearchTerm();
        this._registerEvents();
    }

    /* ---------- Aufbau ---------- */

    _createPanel() {
        const id = `nova-search-panel-${Math.random().toString(36).slice(2, 8)}`;

        this._backdrop = document.createElement('div');
        this._backdrop.className = 'nova-search-backdrop';
        this._backdrop.hidden = true;

        this._panel = document.createElement('div');
        this._panel.id = id;
        this._panel.className = 'nova-search-panel';
        this._panel.hidden = true;

        document.body.append(this._backdrop, this._panel);

        this._input.setAttribute('aria-controls', id);
        this._input.setAttribute('aria-expanded', 'false');
    }

    _registerEvents() {
        this._input.addEventListener('input', () => this._schedule());
        this._input.addEventListener('focus', () => this._onFocus());
        this._input.addEventListener('keydown', (event) => this._onInputKeydown(event));
        this.el.addEventListener('submit', (event) => this._onSubmit(event));

        this._panel.addEventListener('keydown', (event) => this._onPanelKeydown(event));
        this._panel.addEventListener('click', (event) => this._onPanelClick(event));
        this._backdrop.addEventListener('click', () => this.close());

        document.addEventListener('click', (event) => {
            if (!this.el.contains(event.target) && !this._panel.contains(event.target)) {
                this.close();
            }
        });

        const closeButton = this.el.querySelector(this.options.closeButtonSelector);
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                this._input.value = '';
                this._input.focus();
                this.close();
            });
        }

        // Handy: Suchfeld nach dem Aufklappen fokussieren
        const collapse = document.querySelector(this.options.collapseSelector);
        if (collapse) {
            collapse.addEventListener('shown.bs.collapse', () => this._input.focus());
            collapse.addEventListener('hidden.bs.collapse', () => this.close());
        }

        const reposition = () => this._isOpen() && this._position();
        window.addEventListener('resize', reposition, { passive: true });
        window.addEventListener('scroll', reposition, { passive: true });
    }

    /** Suchbegriff auf der Ergebnisseite wieder ins Feld schreiben (Header kommt per ESI) */
    _restoreSearchTerm() {
        if (this._input.value !== '' || window.activeRoute !== 'frontend.search.page') {
            return;
        }

        const term = new URLSearchParams(window.location.search).get('search');
        if (term) {
            this._input.value = term;
        }
    }

    /* ---------- Eingabe ---------- */

    _term() {
        return this._input.value.trim();
    }

    _schedule() {
        window.clearTimeout(this._timer);

        if (this._term().length < this.options.minChars) {
            this._abort();
            this.close();
            return;
        }

        // Neuer Suchbegriff → Herstellerfilter zurücksetzen
        if (this._term() !== this._lastTerm) {
            this._manufacturers.clear();
        }

        this._timer = window.setTimeout(() => this._load(), this.options.debounce);
    }

    _onFocus() {
        // Fokus kam per Esc aus dem Fenster zurück → nicht sofort wieder öffnen
        if (this._returningFocus) {
            this._returningFocus = false;
            return;
        }

        if (this._term().length >= this.options.minChars && this._panel.innerHTML !== '') {
            this.open();
        }
    }

    _onSubmit(event) {
        if (this._term().length < this.options.minChars) {
            event.preventDefault();
            event.stopPropagation();
        }
    }

    _onInputKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        } else if (event.key === 'ArrowDown' && this._isOpen()) {
            event.preventDefault();
            this._focusItem(0);
        }
    }

    /* ---------- Laden & Anzeigen ---------- */

    _query() {
        const params = new URLSearchParams({ search: this._term() });
        if (this._manufacturers.size > 0) {
            params.set('manufacturer', [...this._manufacturers].sort().join('|'));
        }
        return params.toString();
    }

    async _load() {
        const query = this._query();
        this._lastTerm = this._term();

        if (query === this._lastQuery && this._isOpen()) {
            return;
        }

        if (this._cache.has(query)) {
            this._render(this._cache.get(query), query);
            return;
        }

        this._abort();
        this._request = new AbortController();
        this._panel.classList.add('is-loading');

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

            // Nur anzeigen, wenn der Begriff inzwischen nicht geändert wurde
            if (query === this._query()) {
                this._render(html, query);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.close();
            }
        } finally {
            this._panel.classList.remove('is-loading');
        }
    }

    _abort() {
        if (this._request) {
            this._request.abort();
            this._request = null;
        }
    }

    _render(html, query) {
        this._panel.innerHTML = html;
        this._lastQuery = query;
        this._highlight();
        this.open();
        this.$emitter.publish('novaSearch/render', { term: this._term(), panel: this._panel });
    }

    /** Suchwörter in Namen fett hervorheben (ohne innerHTML – kein XSS-Risiko) */
    _highlight() {
        const words = this._term()
            .split(/\s+/)
            .filter((word) => word.length >= 2)
            .map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

        if (words.length === 0) {
            return;
        }

        const pattern = new RegExp(`(${words.join('|')})`, 'gi');

        this._panel.querySelectorAll('.nova-search-hl').forEach((element) => {
            const text = element.textContent;
            const parts = text.split(pattern);
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

    /* ---------- Fenster ---------- */

    _isOpen() {
        return !this._panel.hidden;
    }

    /** Fenster und Abdunklung direkt unter dem Suchfeld beginnen lassen – das Feld bleibt bedienbar */
    _position() {
        const top = Math.round(this.el.getBoundingClientRect().bottom + 8);
        this._panel.style.top = `${top}px`;
        this._panel.style.maxHeight = `${Math.max(200, window.innerHeight - top - 16)}px`;
        this._backdrop.style.top = `${top - 8}px`;
    }

    open() {
        this._position();
        this._panel.hidden = false;
        this._backdrop.hidden = false;
        this._input.setAttribute('aria-expanded', 'true');
        document.body.classList.add('nova-search-open');
    }

    close() {
        if (!this._isOpen()) {
            return;
        }

        this._panel.hidden = true;
        this._backdrop.hidden = true;
        this._input.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('nova-search-open');
    }

    /* ---------- Bedienung im Fenster ---------- */

    _onPanelClick(event) {
        const chip = event.target.closest('[data-nova-search-manufacturer]');
        if (!chip) {
            return;
        }

        const id = chip.getAttribute('data-nova-search-manufacturer');
        if (this._manufacturers.has(id)) {
            this._manufacturers.delete(id);
        } else {
            this._manufacturers.add(id);
        }

        this._load().then(() => {
            const again = this._panel.querySelector(`[data-nova-search-manufacturer="${id}"]`);
            if (again) {
                again.focus();
            }
        });
    }

    _items() {
        return [...this._panel.querySelectorAll(this.options.focusableSelector)];
    }

    _focusItem(index) {
        const items = this._items();
        if (items.length === 0) {
            return;
        }
        items[Math.max(0, Math.min(index, items.length - 1))].focus();
    }

    _onPanelKeydown(event) {
        const items = this._items();
        const index = items.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            this._returningFocus = true;
            this._input.focus();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            this._focusItem(index + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (index <= 0) {
                this._input.focus();
            } else {
                this._focusItem(index - 1);
            }
        }
    }
}
