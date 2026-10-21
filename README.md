# Nova Suche für Shopware 6

🇬🇧 [English version](README.en.md)

Schnelles **Suchfenster** für Shopware 6.7: Ein Klick ins Suchfeld öffnet ein großes Fenster mit Produktbildern, Filtern und Sortierung – ohne externen Suchdienst und ohne laufende Kosten.

## Funktionen

**Beim Öffnen (noch nichts getippt)**
- **Beliebte Suchanfragen** – ein Klick startet die Suche
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
- Ca. 9,5 KB JavaScript, Inhalte werden erst beim ersten Öffnen geladen

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

`theme:compile` ist nötig, damit die Styles des Ergebnisfensters ins Theme übernommen werden.

## Einstellungen

**Erweiterungen → Meine Erweiterungen → Nova Suche → Konfigurieren** (pro Verkaufskanal):

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Sofortsuche verwenden | an | Aus = Shopwares Standard-Suchvorschläge |
| Produkte pro Ladeschritt | 24 | Beim Scrollen wird automatisch nachgeladen |
| Passende Kategorien anzeigen | an | |
| Filter „Marken“ anzeigen | an | |
| Wartezeit nach Tastendruck | 180 ms | Kleiner = schnellere Reaktion, größer = weniger Serverlast |
| Beliebte Suchanfragen | – | Eine pro Zeile, z. B. `chrom`, `scheinwerfer`, `rolldach` (max. 12) |
| Highlight-Produkte | – | Produkte für „Aktuelle Highlights“ |

Die Mindestlänge des Suchbegriffs kommt aus Shopwares Sucheinstellungen (*Einstellungen → Suche*).

## Tipps für bessere Treffer (ohne Plugin-Änderung)

- **Suchbegriffe pflegen:** Im Produkt unter *Suchbegriffe* Spitznamen und Varianten eintragen (z. B. „Ente“ beim 2CV)
- **Abverkaufsartikel ohne Bestand ausblenden:** *Einstellungen → Produkte → „Abverkaufsprodukte ohne Bestand ausblenden“* – gilt auch für die Suche
- **Suchgewichtung anpassen:** *Einstellungen → Suche* (z. B. Produktnummer und Name höher gewichten)

## Ausblick

Geplant ist eine zweite Stufe mit der **Claude API (Haiku 5.5)**: Wenn die normale Suche nichts findet oder ein ganzer Satz eingegeben wird („Bremsbelag für die Ente vorne“), übersetzt Claude die Anfrage in passende Suchbegriffe. Antworten werden gespeichert, sodass jede Anfrage nur einmal Kosten verursacht.

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
| `start.html.twig` | Startansicht | `nova_search_start`, `nova_search_popular`, `nova_search_highlights` |
| `results.html.twig` | Trefferansicht | `nova_search_results`, `nova_search_empty`, `nova_search_filters`, `nova_search_main` |
| `card.html.twig` | Produktkarte | `nova_search_card` |
| `items.html.twig` | Nachgeladene Karten | – |
| `layout/header/search.html.twig` | Rahmen des Fensters | `nova_search_layer_template` |

Nach jeder Anzeige feuert das Storefront-Event `novaSearch/render`.

## Kompatibilität

Shopware 6.7.x (geprüft gegen 6.7.15).

## Lizenz

MIT
