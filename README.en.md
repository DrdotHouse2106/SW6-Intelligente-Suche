# Nova Search for Shopware 6

🇩🇪 [Deutsche Version](README.md)

Fast **search window** for Shopware 6.7: clicking the search field opens a large window with product images, filters and sorting – no external search service, no running costs.

## Features

- Start view: popular searches and highlight products (slider)
- While typing: product cards with images, brand filter with counts, price slider, sorting, matching categories, infinite scroll
- Mobile: full-screen window, two columns, collapsible filters
- Highlighted search words
- Keyboard: ↓/↑ through results, Esc closes, Enter opens the full result page
- Mobile: full-width panel, two product columns
- Uses Shopware's own product search (MySQL or OpenSearch), computes only the brand and price facets, HTTP-cacheable, ~15 KB JavaScript

## Learning & Claude

- **Learns from behaviour:** searches, clicks, add-to-cart and purchases after a search are counted anonymously (no IPs, no customer or session IDs). Products that perform well for a term move up for exactly that term (relevance sorting only). Popular searches are determined automatically (min. count + blocklist).
- **Recently viewed** list in the start view (browser localStorage, consent "comfort features" by default).
- **Claude (`claude-haiku-5-5`, off by default):** search terms without results are sent once, in batches, to Claude via the official Anthropic PHP SDK. Every suggested alternative is verified against the real product search; only working rewrites are stored permanently and applied automatically ("Results for …"). Only search terms plus shop/category/brand names are sent – no customer data. Estimated cost: ~2–3 cents per 1,000 new zero-result terms.
- Requires Shopware's scheduled tasks and message queue (or `bin/console nova-search:rewrite` via cron).
- Interface for other plugins: `document` events `nova:search-open`, `nova:search-close`, `nova:search` (`{ term, total }`), `nova:products-rendered` (`{ container }`); cards carry `data-product-information`, lists `data-nova-list-id`/`data-nova-list-name`.

## Installation

```bash
cd <shopware-root>/custom/plugins
git clone https://github.com/DrdotHouse2106/SW6-Intelligente-Suche.git NovaSearch
cd <shopware-root>
bin/console plugin:refresh
bin/console plugin:install --activate NovaSearch
bin/console theme:compile
bin/console cache:clear
```

Configure it under *Extensions → My extensions → Nova Search → Configure*.


## Store API for apps

All routes are `POST` with a JSON body and the usual `sw-access-key` / `sw-context-token` headers.

- `POST /store-api/nova-search/search` – `{ search, p?, limit? (24, max 48), order?, properties?, manufacturer? }` → `{ ids, total, page, limit, sorting, availableSortings: [{ key, label }], rewrite: null | { original, term } }`. Uses the learned ranking and stored Claude rewrites. Does **not** count the search (the app reports each final search once via `track`); zero-result terms are only queued for rewriting. Missing/too short `search` → `400`.
- `POST /store-api/nova-search/popular` – `{ limit? (10, max 20) }` → `{ terms: [...] }`.
- `POST /store-api/nova-search/track` – `{ events: [{ type: "search"|"click"|"cart"|"purchase", term, productId?, total?, quantity? }] }` → `204`. Max. 20 events per call and 300 per context token and hour. `purchase` only counts if the logged-in customer actually ordered the product within 48 hours, once per token and product. Only term, product ID and counters are stored.

Shopware's own `/store-api/search` also uses the learned ranking and rewrites (no counting).

With the "Dynamic monthly delivery dates" plugin and its "show in listings" option enabled, search cards show the next shipping date.

## Exporting search data

`bin/console nova-search:export` writes `suchbegriffe.csv` (terms with searches, hits, clicks, carts, orders, CTR), `suchbegriffe-produkte.csv` (term × product) and `claude-umschreibungen.csv` (Claude rewrites). Options: `--type=terms|products|rewrites`, `--sales-channel=<id>`, `--days=N`, `--min-searches=N`, `--output=<dir>`, `--delimiter=;`. UTF-8 with BOM, formula-like values are escaped, no customer data.

## Compatibility

Shopware 6.7.x (checked against 6.7.15). License: MIT.

## Troubleshooting

Known pitfalls from real installations. **Please add new ones** (or open an issue).

- **Nothing changes after install:** the search window is off by default – enable it per sales channel.
- **No styling:** run `theme:compile` and `cache:clear`.
- **Two search overlays:** disable other search extensions in that sales channel.
- **Composer / "Class Anthropic\Client not found":** run `composer require anthropic-ai/sdk` in the shop root; everything except the Claude stage works without it.
- **Claude does nothing:** API key, Claude switch, scheduled tasks and message queue must run; test with `bin/console nova-search:rewrite`.
- **No typo tolerance:** use OpenSearch, or rely on the Claude stage.

