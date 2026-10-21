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

## Compatibility

Shopware 6.7.x (checked against 6.7.15). License: MIT.
