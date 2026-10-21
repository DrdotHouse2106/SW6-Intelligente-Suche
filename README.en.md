# Nova Search for Shopware 6

🇩🇪 [Deutsche Version](README.md)

Fast **instant search** for Shopware 6.7: while typing, a results panel opens below the search field with product images, prices, matching categories and a brand filter – no external search service, no extra cost.

## Features

- Results panel while typing (image, name, variant, price incl. "from" and list price)
- Matching categories with their path
- Brand filter chips
- Highlighted search words
- Keyboard: ↓/↑ through results, Esc closes, Enter opens the full result page
- Mobile: full-width panel, two product columns
- Uses Shopware's own product search (MySQL or OpenSearch), computes only the brand facet, HTTP-cacheable, ~6 KB JavaScript

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
