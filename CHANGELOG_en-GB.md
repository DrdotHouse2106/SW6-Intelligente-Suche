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
