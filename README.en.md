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
- Uses Shopware's own product search (MySQL or OpenSearch), computes only the brand and price facets, HTTP-cacheable, ~9.5 KB JavaScript

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
