# 1.4.0
- New: checkbox "Exact part number only" in the search layer – shows only products with exactly this number; Store API parameter exactNumber

# 1.3.0
- New: number search – product, manufacturer, EAN and OE numbers (custom fields, properties) regardless of spaces, dots, dashes and slashes; also by prefix
- Number index is updated when products are saved, fully rebuilt hourly or via bin/console nova-search:numbers

# 1.2.0
- New: synonyms (one group per line) for search layer, search page and app; multi-word searches stay precise
- New: redirects for search terms – Enter goes straight to the target, the layer shows a link, the Store API returns `redirect`
- New: excluded products – closeout without stock, products, categories, tags or a yes/no custom field

# 1.1.0
- Monthly cost limit for the Claude stage (default 5 USD, 0 = off) with warning threshold (default 80 %)
- Usage is calculated per month and model from the reported tokens and stored
- Before every call the maximum cost must fit into the remaining budget; once the limit is reached terms stay open
- New command bin/console nova-search:claude-usage, export type claude-usage

# 1.0.0
- First release: search window with images, filters (brands, price), sorting and infinite scroll
- Learns from searches, clicks, cart and purchases (no personal data)
- Automatic popular searches, recently viewed in the start view
- Claude (Haiku 5.5) permanently improves searches without results – each term is sent only once
- Store API for apps (search, popular searches, tracking)
- CSV export of search data: bin/console nova-search:export
- The search window is off after installation and is enabled per sales channel
