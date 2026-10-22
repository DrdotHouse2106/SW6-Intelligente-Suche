# Nova Suche für Shopware 6

🇬🇧 [English version](README.en.md)

Schnelles **Suchfenster** für Shopware 6.7: Ein Klick ins Suchfeld öffnet ein großes Fenster mit Produktbildern, Filtern und Sortierung – ohne externen Suchdienst.

Die Suche **lernt dabei mit**: Was Kunden suchen, anklicken, in den Warenkorb legen und kaufen, verbessert die Reihenfolge der Treffer. Suchbegriffe ohne Treffer (z. B. „bremsbelag ente“) wertet auf Wunsch **Claude** einmalig aus – die geprüfte Umschreibung („bremsbelag 2cv“) wird dauerhaft gespeichert und gilt ab dann für alle Kunden, ohne weitere KI-Kosten.

## Funktionen

**Beim Öffnen (noch nichts getippt)**
- **Beliebte Suchanfragen** – automatisch aus echten Suchen ermittelt (und/oder von Hand gepflegt); ein Klick startet die Suche
- **Zuletzt angesehen** – die Produkte, die der Besucher zuletzt angeschaut hat
- **Aktuelle Highlights** – ausgewählte Produkte als Slider mit Bild und Preis

**Beim Tippen**
- **Produktkarten mit Bild**, Name, Variante, Preis (inkl. „Ab“-Preis, Streichpreis und Rabatt-Markierung)
- **Filter „Marken“** mit Trefferanzahl und **Preisfilter** mit Schieberegler und Eingabefeldern
- **Sortierung** (Relevanz, Preis, Name … – wie in Shopware eingestellt)
- **Passende Kategorien** als Schnellzugriff, z. B. „Bremsanlage 2CV6“
- **Automatisches Nachladen** weiterer Produkte beim Scrollen
- Suchwörter werden hervorgehoben

**Bedienung**
- Enter öffnet die volle Ergebnisseite, Esc oder ✕ schließt das Fenster
- ↓/↑ durch die Treffer, Fokus bleibt im Fenster
- **Smartphone:** Fenster über den ganzen Bildschirm, zwei Produktspalten, Filter hinter einem „Filter“-Button, Lupen-Symbol öffnet das Fenster direkt
- Passt sich automatisch an das Theme an (Farben, Eckenradien)

**Dynamische Lieferzeiten:** Ist das Plugin „Dynamische Monats-Lieferzeiten“ installiert und dort „Versandtermin in Produkt-Listings anzeigen“ aktiv, zeigt jede Suchkarte kompakt den nächsten Versandtermin („Versand am Mo, 02.11.2026“). Ohne das Plugin passiert nichts.

## Schnell – auch ohne OpenSearch

- Nutzt Shopwares eigene Produktsuche (MySQL oder – falls vorhanden – OpenSearch)
- Berechnet nur Marken- und Preisfilter; Eigenschafts-, Bewertungs- und Versandfilter werden für das Fenster abgeschaltet
- Beim Nachladen werden gar keine Filter berechnet
- Antworten sind **HTTP-Cache-fähig** und werden zusätzlich im Browser zwischengespeichert
- Anfragen werden beim Tippen gebündelt (Verzögerung einstellbar), veraltete Anfragen abgebrochen
- Ca. 15 KB JavaScript, Inhalte werden erst beim ersten Öffnen geladen

## Lernen aus dem Kundenverhalten

| Signal | Wie erfasst | Wirkung |
|---|---|---|
| Suche | Suchfenster (nach einer Tipp-Pause) und Suchergebnisseite | Beliebte Suchanfragen, Begriffe ohne Treffer |
| Klick auf ein Ergebnis | Suchfenster und Suchergebnisseite | +1 Punkt für das Produkt bei diesem Begriff |
| In den Warenkorb nach einer Suche | überall im Shop | +3 Punkte |
| Kauf nach einer Suche | beim Bestellabschluss | +5 Punkte |

**Bessere Platzierung:** Erreicht ein Produkt bei einem Suchbegriff den eingestellten Mindest-Lernwert (Standard 3 Punkte), rückt es bei genau diesem Begriff in der Sortierung „Relevanz“ nach oben – im Suchfenster und auf der Suchergebnisseite. Die Trefferliste selbst bleibt unverändert (es tauchen keine unpassenden Produkte auf), Einzelklicks verzerren nichts. Die Stärke wächst logarithmisch und ist auf das Doppelte der besten Shopware-Bewertung begrenzt.

**Beliebte Suchanfragen:** die häufigsten Begriffe mit Treffern aus den letzten 90 Tagen, ab einer Mindestanzahl Suchen (Standard 5). Eine **Sperrliste** verhindert, dass unerwünschte Begriffe öffentlich angezeigt werden. Von Hand gepflegte Begriffe stehen immer vorne.

## Claude: Suchbegriffe ohne Treffer dauerhaft verbessern

1. Ein Begriff liefert keine Treffer → er wird vorgemerkt.
2. Stündlich (Scheduled Task) bzw. sofort per `bin/console nova-search:rewrite` gehen die offenen Begriffe **gebündelt** (bis 30 je Anfrage) an Claude (Modell `claude-haiku-5-5`). Claude kennt dabei Shopname, Sprache sowie Kategorie- und Markennamen des Verkaufskanals und schlägt je Begriff bis zu drei Alternativen vor (Tippfehler, Spitznamen, Füllwörter).
3. **Jede Alternative wird gegen die echte Produktsuche geprüft** – gespeichert wird nur, was tatsächlich Treffer liefert.
4. Ab dann sucht der Shop bei diesem Begriff automatisch mit der Umschreibung – im Suchfenster, auf der Suchergebnisseite und in den Vorschlägen – und zeigt den Hinweis „Ergebnisse für „bremsbelag 2cv““.
5. Nach 30 Tagen wird nachgeprüft: Findet der ursprüngliche Begriff inzwischen selbst etwas (neues Sortiment), wird die Umschreibung entfernt. Begriffe, für die es damals keine Lösung gab, bekommen eine neue Chance.

**Nachhaltig:** Jeder Begriff wird nur **einmal** an Claude geschickt – nicht bei jeder Suche. Die Verbesserung bleibt dauerhaft in der Datenbank und wirkt ohne weitere Anfragen.

**Optional sofort:** Mit „Neue Begriffe auf der Suchergebnisseite sofort auswerten“ bekommt der erste Kunde mit einem neuen Begriff direkt passende Treffer (Wartezeit beim ersten Mal bis zu 4 Sekunden).

**Kosten (Schätzung):** Claude Haiku 5.5 kostet 0,10 $ je 1 Mio. Eingabe- und 0,50 $ je 1 Mio. Ausgabe-Tokens. Eine gebündelte Anfrage mit 30 Begriffen braucht grob 1.500 Eingabe- und 1.000 Ausgabe-Tokens – also deutlich unter 0,1 Cent. **1.000 neue Begriffe ohne Treffer kosten damit rund 2–3 Cent.**

### Kostenlimit

Damit die Claude-Stufe nie teurer wird als geplant, gibt es ein **monatliches Kostenlimit** (Standard **5 USD**):

- Das Limit gilt **für alle Verkaufskanäle zusammen** – in der Regel nutzen alle denselben API-Schlüssel. Bitte unter *Erweiterungen → Nova Suche → Konfigurieren* mit der Auswahl **„Alle Verkaufskanäle“** einstellen. `0` schaltet Claude komplett ab.
- Nach jeder Antwort werden die von der API gemeldeten Tokens (Eingabe, Ausgabe, ggf. Cache) mit den Modellpreisen umgerechnet und je **Kalendermonat (UTC)** gespeichert.
- **Vor jedem Aufruf** wird geprüft, ob die geschätzten **Höchstkosten** des Aufrufs (Prompt + volle Antwortlänge) noch ins Restbudget passen. Ein einzelner Aufruf kann das Limit deshalb nicht nennenswert überschreiten. Bei Haiku 5.5 sind das höchstens ca. 0,2 Cent je Aufruf.
- Ist das Limit erreicht, werden **keine Anfragen mehr gestellt**. Offene Begriffe bleiben vorgemerkt und werden im nächsten Monat (oder nach Erhöhung des Limits) ausgewertet – auch die Sofort-Auswertung auf der Suchergebnisseite hält sich daran. Die Suche selbst läuft normal weiter.
- Beim Erreichen der **Warnschwelle** (Standard 80 %) und des Limits steht **einmal pro Monat** ein Hinweis im Shopware-Log.
- Verbrauch ansehen: `bin/console nova-search:claude-usage` (aktueller Monat mit Anfragen, Tokens, Kosten, Limit, Rest sowie die letzten 12 Monate) oder Export mit `bin/console nova-search:export --type=claude-usage`.

Preise in der Berechnung (USD je 1 Mio. Tokens, Eingabe/Ausgabe): `claude-haiku-5-5` 0,10/0,50 (bei Prompts über 100.000 Tokens 0,50/2,50), `claude-sonnet-5-5` 2/10, `claude-opus-5-5` 4/20, `claude-fable-5-1` 10/50. Unbekannte Modelle werden mit den teuersten bekannten Preisen gerechnet. Prompt-Caching nutzt das Plugin nicht; meldet die API trotzdem Cache-Tokens, zählen Cache-Lesezugriffe wie Eingabe und Cache-Schreibzugriffe doppelt (bewusst eher zu hoch).

> **Empfehlung – zweite Sicherung in der Anthropic Console:** Lege in der [Anthropic Console](https://console.anthropic.com) einen **eigenen Workspace** (z. B. „Shop-Suche“) mit einem **Ausgabenlimit** an und erstelle den API-Schlüssel in diesem Workspace. Ausgabenlimits gelten pro Workspace, nicht pro einzelnem Schlüssel. Der Zähler im Plugin ist eine Schätzung auf Basis der gemeldeten Tokens – **maßgeblich ist immer die Abrechnung in der Console**.

**Fehlerfest:** Fehlt der API-Schlüssel, ist Claude nicht erreichbar oder antwortet nicht verwertbar, läuft die Suche ganz normal weiter. Fehlversuche werden gezählt; nach drei Versuchen wird ein Begriff nicht mehr angefragt.

## Store-API für Apps

Die Shop-App nutzt die Nova Suche über die Store-API – mit demselben gelernten Ranking und denselben Claude-Umschreibungen wie das Suchfenster. Authentifizierung wie bei jeder Store-API-Anfrage über die Header `sw-access-key` und `sw-context-token`; alle Routen erwarten `POST` mit JSON-Body.

### Suche

```http
POST /store-api/nova-search/search
{ "search": "bremsbelag ente", "p": 1, "limit": 24, "order": "score",
  "properties": "<Options-ID>|<Options-ID>", "manufacturer": "<Hersteller-ID>" }
```

| Feld | Pflicht | Bedeutung |
|---|---|---|
| `search` | ja | Suchbegriff, mind. 2 Zeichen – sonst `400` (Shopware-Fehlerstruktur) |
| `p` | nein | Seite (Standard 1) |
| `limit` | nein | Treffer pro Seite (Standard 24, max. 48) |
| `order` | nein | Sortierschlüssel aus `availableSortings`; unbekannte Werte → Standard-Sortierung |
| `properties` | nein | Eigenschafts-Optionen mit `|` getrennt, z. B. das gewählte Fahrzeug |
| `manufacturer` | nein | Hersteller-IDs mit `|` getrennt |

Antwort:

```json
{
  "ids": ["0190…", "0188…"],
  "total": 30, "page": 1, "limit": 24, "sorting": "score",
  "availableSortings": [{ "key": "score", "label": "Relevanz" }, { "key": "price-asc", "label": "Preis aufsteigend" }],
  "rewrite": { "original": "bremsbelag ente", "term": "bremsbelag 2cv" }
}
```

`ids` sind die Produkt-IDs in Ranking-Reihenfolge – Preise, Bilder und Bestand lädt die App wie gewohnt über `/store-api/product`. `rewrite` ist `null`, wenn ohne Umschreibung gesucht wurde; sonst kann die App „Ergebnisse für …“ anzeigen.

**Wichtig:** Diese Route zählt die Suche **nicht** in der Statistik, weil die App sie beim Blättern und Nachladen mehrfach aufruft. Die App meldet jede fertige Suche genau einmal über `track` (siehe unten). Begriffe ohne Treffer werden hier nur für die Claude-Umschreibung vorgemerkt.

### Beliebte Suchanfragen

```http
POST /store-api/nova-search/popular
{ "limit": 10 }
```

Antwort: `{ "terms": ["chrom", "scheinwerfer", "rolldach"] }` – gleiche Liste wie im Suchfenster (manuelle zuerst, dann automatische, Sperrliste beachtet), max. 20.

### Nutzungssignale (Lernen)

```http
POST /store-api/nova-search/track
{ "events": [
  { "type": "search",   "term": "bremse", "total": 24 },
  { "type": "click",    "term": "bremse", "productId": "0190…" },
  { "type": "cart",     "term": "bremse", "productId": "0190…", "quantity": 2 },
  { "type": "purchase", "term": "bremse", "productId": "0190…" }
] }
```

Antwort immer `204`. Es gelten dieselben Regeln wie im Suchfenster: max. 20 Ereignisse pro Aufruf und **300 pro Kontext-Token und Stunde**, nur existierende Produkte, Begriffe werden normalisiert, E-Mail-Adressen und Telefonnummern verworfen. Gespeichert werden nur Begriff, Produkt-ID und Zähler – kein Token, keine Kunden- oder Bestelldaten.

`purchase` zählt nur, wenn der angemeldete Kunde (auch Gast) dieses Produkt in den **letzten 48 Stunden wirklich bestellt** hat, und je Kontext-Token und Produkt nur einmal. So lassen sich keine Käufe erfinden.

### Shopwares eigene Suche

Auch Shopwares Standard-Route `/store-api/search` nutzt das gelernte Ranking und gespeicherte Umschreibungen (die Plugin-Logik sitzt im Such-Builder). Dort wird nichts gezählt – die App kann bei Fehlern also gefahrlos darauf zurückfallen.

## Suchdaten exportieren

Alle gesammelten Daten lassen sich als CSV exportieren – zur Auswertung in Excel oder zur Analyse durch Claude:

```bash
bin/console nova-search:export                       # alles, alle Verkaufskanäle
bin/console nova-search:export --days=90             # nur die letzten 90 Tage
bin/console nova-search:export --type=terms --min-searches=3
bin/console nova-search:export --sales-channel=<ID> --output=/tmp/export
```

| Datei | Inhalt |
|---|---|
| `suchbegriffe.csv` | Begriff, Suchen, Treffer (zuletzt), Klicks, Warenkorb, Käufe, Klickrate, erstmals/zuletzt gesucht |
| `suchbegriffe-produkte.csv` | Begriff × Produkt (Nummer, Name) mit Klicks, Warenkorb, Käufen |
| `claude-umschreibungen.csv` | Begriffe ohne Treffer, Umschreibung durch Claude, Status, Treffer |
| `claude-kosten.csv` | Claude-Verbrauch je Monat und Modell: Anfragen, Tokens, Kosten in USD (für alle Verkaufskanäle zusammen) |

| Option | Bedeutung |
|---|---|
| `--type` | `terms`, `products`, `rewrites`, `claude-usage` (mehrfach möglich, Standard: alle) |
| `--sales-channel` | nur diese Verkaufskanal-ID(s) |
| `--days` | nur Einträge der letzten N Tage |
| `--min-searches` | Suchbegriffe erst ab N Suchen |
| `--output` | Zielordner (Standard `nova-search-export`) |
| `--delimiter` | Trennzeichen (Standard `;` für deutsches Excel) |

Die Dateien sind UTF-8 mit BOM (Umlaute in Excel korrekt). Suchbegriffe, die Excel als Formel ausführen würde (z. B. `=…`), werden mit einem Apostroph entschärft. Sie enthalten **keine Kundendaten** – nur Suchbegriffe, Produkte und Zähler.

## Datenschutz

- **Keine personenbezogenen Daten:** gespeichert werden nur Suchbegriffe, Produkt-IDs und Zähler – keine IP-Adressen, Kunden-, Bestell- oder Sitzungs-IDs. Begriffe, die wie E-Mail-Adressen oder Telefonnummern aussehen, werden nie gespeichert.
- **An Anthropic (Claude) gehen nur** Suchbegriffe ohne Treffer sowie Shop-, Kategorie- und Markennamen – keine Kundendaten. Claude ist standardmäßig ausgeschaltet.
- **Cookies/Speicher im Browser nur mit Zustimmung** (Shopware-Cookie-Banner):
  - *Statistik* → „Suche: Käufe Suchbegriffen zuordnen“: Cookie `nova-search-attr` merkt sich bis zu 2 Tage, welches Produkt über welchen Begriff gefunden wurde; wird nach der Bestellung gelöscht. Ohne Zustimmung werden nur anonyme Suchen und Klicks gezählt.
  - *Komfortfunktionen* → „Zuletzt angesehene Produkte merken“: Liste im localStorage (`nova-recently-viewed`), verlässt nie den Browser. Die Zustimmungspflicht ist abschaltbar.
- **Missbrauchsschutz:** max. 20 Ereignisse pro Aufruf und 300 pro Sitzung und Stunde, nur existierende Produkte, Mindestanzahl und Sperrliste für öffentlich angezeigte Begriffe.
- Einträge, die ein Jahr lang nicht vorkamen, werden automatisch gelöscht. Beim Deinstallieren ohne „Daten behalten“ werden alle Tabellen entfernt.

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

- `plugin:install` installiert automatisch das offizielle **Anthropic-PHP-SDK** (`anthropic-ai/sdk`) per Composer und legt die Tabellen `nova_search_term`, `nova_search_term_product` und `nova_search_rewrite` an.
- `theme:compile` ist nötig, damit die Styles des Suchfensters ins Theme übernommen werden.
- **Danach ist das Suchfenster noch aus.** Unter *Erweiterungen → Meine Erweiterungen → Nova Suche → Konfigurieren* oben den Verkaufskanal wählen und „Sofortsuche verwenden“ einschalten – so lässt sich die Suche zuerst in einem Shop testen.
- Für die stündliche Auswertung müssen Shopwares **Scheduled Tasks und die Message Queue** laufen (Standard bei Shopware: `bin/console scheduled-task:run` und `bin/console messenger:consume` als Dienst/Cronjob oder der Admin-Worker). Alternativ `bin/console nova-search:rewrite` per Cronjob.

## Einstellungen

**Erweiterungen → Meine Erweiterungen → Nova Suche → Konfigurieren** (pro Verkaufskanal):

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Sofortsuche verwenden | **aus** | Nach der Installation pro Verkaufskanal gezielt einschalten (oben den Verkaufskanal wählen). Aus = Shopwares Standard-Suchvorschläge |
| Produkte pro Ladeschritt | 24 | Beim Scrollen wird automatisch nachgeladen |
| Passende Kategorien anzeigen | an | |
| Filter „Marken“ anzeigen | an | |
| Wartezeit nach Tastendruck | 180 ms | Kleiner = schnellere Reaktion, größer = weniger Serverlast |
| Beliebte Suchanfragen | – | Eine pro Zeile, z. B. `chrom`, `scheinwerfer`, `rolldach` – stehen immer vorne |
| Beliebte Suchanfragen automatisch ermitteln | an | Häufigste Begriffe mit Treffern der letzten 90 Tage |
| Mindestanzahl Suchen für „beliebt“ | 5 | |
| Sperrliste | – | Begriffe mit diesen Wörtern werden nie als „beliebt“ angezeigt |
| Highlight-Produkte | – | Produkte für „Aktuelle Highlights“ |
| „Zuletzt angesehen“ anzeigen | an | |
| Nur mit Zustimmung (Komfortfunktionen) | an | |
| Aus Suchen, Klicks und Käufen lernen | an | |
| Mindest-Lernwert für bessere Platzierung | 3 | Klick = 1, Warenkorb = 3, Kauf = 5 Punkte |
| Suchbegriffe ohne Treffer mit Claude verbessern | aus | |
| Anthropic-API-Schlüssel | – | Aus der Anthropic Console |
| Modell | `claude-haiku-5-5` | |
| Monatliches Kostenlimit (USD) | 5 | Für alle Verkaufskanäle zusammen – unter „Alle Verkaufskanäle“ einstellen. 0 = Claude aus |
| Warnschwelle (% des Limits) | 80 | Einmaliger Hinweis im Log pro Monat |
| Neue Begriffe auf der Suchergebnisseite sofort auswerten | aus | |

Die Mindestlänge des Suchbegriffs kommt aus Shopwares Sucheinstellungen (*Einstellungen → Suche*).

## Tipps für bessere Treffer (ohne Plugin-Änderung)

- **Suchbegriffe pflegen:** Im Produkt unter *Suchbegriffe* Spitznamen und Varianten eintragen (z. B. „Ente“ beim 2CV)
- **Abverkaufsartikel ohne Bestand ausblenden:** *Einstellungen → Produkte → „Abverkaufsprodukte ohne Bestand ausblenden“* – gilt auch für die Suche
- **Suchgewichtung anpassen:** *Einstellungen → Suche* (z. B. Produktnummer und Name höher gewichten)

## Entwicklung

Innerhalb von Shopware: `bin/build-storefront.sh`. Ohne Shopware (nur das Storefront-JavaScript):

```bash
cd src/Resources/app/storefront
npm install
npm run build
```

Templates erweitern (`views/storefront/nova-search/`):

| Template | Inhalt | Blöcke |
|---|---|---|
| `start.html.twig` | Startansicht | `nova_search_start`, `nova_search_popular`, `nova_search_recent`, `nova_search_highlights` |
| `results.html.twig` | Trefferansicht | `nova_search_results`, `nova_search_empty`, `nova_search_filters`, `nova_search_main`, `nova_search_rewrite_hint` |
| `card.html.twig` | Produktkarte | `nova_search_card` |
| `items.html.twig` | Nachgeladene Karten | – |
| `layout/header/search.html.twig` | Rahmen des Fensters | `nova_search_layer_template` |
| `page/search/index.html.twig` | Hinweis auf der Suchergebnisseite | `page_search_headline_text` |

### Schnittstelle für andere Plugins (z. B. Tag Manager)

Events auf `document`:

| Event | `detail` | Wann |
|---|---|---|
| `nova:search-open` | – | Suchfenster geöffnet |
| `nova:search-close` | – | Suchfenster geschlossen |
| `nova:search` | `{ term, total }` | Ergebnis angezeigt – nach einer Tipp-Pause, je Begriff einmal pro geöffnetem Fenster (Filter/Sortierung lösen es nicht erneut aus) |
| `nova:products-rendered` | `{ container }` | Produktkarten eingefügt (Start-/Trefferansicht oder Nachladen) |

Markup:

- Jede Produktkarte trägt `data-product-information` (JSON `{ id, name, brand, price, sku }` – wie Shopwares Produktbox).
- Listen tragen `data-nova-list-id` / `data-nova-list-name`: `search_window`/„Suchfenster“, `search_highlights`/„Suche: Highlights“, `recently_viewed`/„Zuletzt angesehen“.
- `localStorage['nova-recently-viewed']`: JSON-Liste von Produkt-IDs, neueste zuerst, max. 20 (gleiches Format wie im Nova Theme).

Zusätzlich feuert das Storefront-Plugin-Event `novaSearch/render`.

Serverseitig: Eine gespeicherte Umschreibung hängt an der Criteria als Extension `novaSearchRewrite` (`original`, `rewrite`) – z. B. `page.listing.criteria.extension('novaSearchRewrite')`.

## Kompatibilität

Shopware 6.7.x (geprüft gegen 6.7.15).

## Roadmap

Geplante Funktionen (Synonyme, Weiterleitungen, Auswertungen in der Administration, Empfehlungen, Produktberater, Assistent …) stehen mit Stand in der [ROADMAP.md](ROADMAP.md).

## Häufige Probleme und Lösungen

Hier sammeln wir Hürden, die bei Installation und Betrieb tatsächlich aufgetreten sind. **Neue Probleme bitte ergänzen** (oder als Issue melden), damit andere nicht lange suchen müssen.

### Nach der Installation ändert sich nichts

**Ursache:** Das Suchfenster ist **standardmäßig aus**  
**Lösung:** *Erweiterungen → Nova Suche → Konfigurieren* → oben den Verkaufskanal wählen → „Sofortsuche verwenden“ einschalten

### Suchfenster ohne Gestaltung

**Ursache:** Styles noch nicht im Theme  
**Lösung:** `bin/console theme:compile` und `bin/console cache:clear`

### Zwei Suchfenster gehen auf

**Ursache:** Eine andere Such-Erweiterung mit eigenem Overlay ist im selben Verkaufskanal aktiv  
**Lösung:** Die andere Such-Erweiterung in diesem Verkaufskanal deaktivieren

### Installation bricht bei Composer ab / „Class Anthropic\Client not found“ im Log

**Ursache:** Shopware lädt das Anthropic-SDK bei der Installation per Composer nach; auf manchen Hostern fehlt Composer oder Schreibrecht  
**Lösung:** Im Shop-Hauptverzeichnis `composer require anthropic-ai/sdk` ausführen. Ohne SDK funktioniert alles außer der Claude-Stufe (im Log steht dann ein klarer Hinweis)

### Claude verbessert nichts

**Ursache:** Claude ist aus, kein API-Schlüssel, oder **Scheduled Tasks / Message Queue laufen nicht**  
**Lösung:** Schlüssel aus der Anthropic Console eintragen, Claude einschalten; prüfen, dass `bin/console scheduled-task:run` und `bin/console messenger:consume` laufen (Cron/Supervisor). Sofort testen: `bin/console nova-search:rewrite`

### „Ergebnisse für …“ erscheint erst später

**Ursache:** Begriffe ohne Treffer werden gesammelt und **stündlich** gebündelt ausgewertet  
**Lösung:** Gewollt (spart Kosten). Sofort: `bin/console nova-search:rewrite`. Optional „Neue Begriffe auf der Suchergebnisseite sofort auswerten“ einschalten

### Claude stoppt mitten im Monat

**Ursache:** **Monatliches Kostenlimit erreicht** (Hinweis im Shopware-Log „Monatliches Claude-Kostenlimit erreicht“)  
**Lösung:** Verbrauch prüfen mit `bin/console nova-search:claude-usage`; ggf. Limit unter „Alle Verkaufskanäle“ erhöhen. Offene Begriffe werden danach bzw. im nächsten Monat automatisch ausgewertet

### Limit pro Shop eingestellt, wirkt aber nicht wie erwartet

**Ursache:** Das Limit gilt für alle Verkaufskanäle zusammen und wird global gelesen  
**Lösung:** Wert unter „Alle Verkaufskanäle“ eintragen

### Kosten im Plugin weichen von der Anthropic-Rechnung ab

**Ursache:** Der Plugin-Zähler ist eine Schätzung anhand der gemeldeten Tokens und der hinterlegten Preise  
**Lösung:** Maßgeblich ist die Console; zusätzlich dort ein Workspace-Ausgabenlimit setzen

### Tippfehler werden nicht gefunden

**Ursache:** Shopwares MySQL-Suche kennt keine Tippfehler-Toleranz  
**Lösung:** Mit OpenSearch/Elasticsearch deutlich besser; sonst fängt die Claude-Stufe viele Fälle ab

### Beliebte Suchanfragen sind leer

**Ursache:** Automatische Liste braucht erst echte Suchen (Mindestanzahl, letzte 90 Tage)  
**Lösung:** Anfangs Begriffe in der Konfiguration von Hand eintragen

### Ranking ändert sich nicht

**Ursache:** Boost greift erst ab einem Schwellenwert (Klicks/Käufe) und nur bei Sortierung „Relevanz“  
**Lösung:** Gewollt, damit Einzelklicks nichts verzerren

### „Zuletzt angesehen“ fehlt im Suchfenster

**Ursache:** Nur mit Zustimmung „Komfortfunktionen“ im Cookie-Dialog  
**Lösung:** Cookie-Einstellungen prüfen

### Export enthält keine Daten

**Ursache:** Daten werden erst gesammelt, seit das Suchfenster aktiv ist  
**Lösung:** Einige Tage nach dem Einschalten erneut exportieren

### Die App sucht nicht mit der Nova Suche

**Ursache:** Store-API-Routen erst ab dieser Version; die App fällt bei 404 auf die Shopware-Suche zurück  
**Lösung:** Plugin aktualisieren, App neu starten

## Lizenz

MIT
