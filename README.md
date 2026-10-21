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

**Fehlerfest:** Fehlt der API-Schlüssel, ist Claude nicht erreichbar oder antwortet nicht verwertbar, läuft die Suche ganz normal weiter. Fehlversuche werden gezählt; nach drei Versuchen wird ein Begriff nicht mehr angefragt.

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
- Für die stündliche Auswertung müssen Shopwares **Scheduled Tasks und die Message Queue** laufen (Standard bei Shopware: `bin/console scheduled-task:run` und `bin/console messenger:consume` als Dienst/Cronjob oder der Admin-Worker). Alternativ `bin/console nova-search:rewrite` per Cronjob.

## Einstellungen

**Erweiterungen → Meine Erweiterungen → Nova Suche → Konfigurieren** (pro Verkaufskanal):

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Sofortsuche verwenden | an | Aus = Shopwares Standard-Suchvorschläge |
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

## Lizenz

MIT
