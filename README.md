# Nova Suche für Shopware 6

🇬🇧 [English version](README.en.md)

Schnelle **Sofortsuche** für Shopware 6.7: Schon beim Tippen öffnet sich unter dem Suchfeld ein Ergebnisfenster mit Produktbildern, Preisen, passenden Kategorien und einem Herstellerfilter – ohne externen Suchdienst und ohne zusätzliche Kosten.

## Funktionen

- **Ergebnisfenster beim Tippen** mit Bild, Name, Variante, Preis (inkl. „Ab“-Preis und Streichpreis)
- **Passende Kategorien** mit Pfad, z. B. „Bremsanlage – 2CV6“ und „Bremsanlage – Dyane/Acady“
- **Herstellerfilter** als Chips direkt im Fenster
- **Suchwörter hervorgehoben** in Produkt- und Kategorienamen
- **Tastatur:** ↓/↑ durch die Treffer, Esc schließt, Enter öffnet die volle Ergebnisseite
- **Smartphone:** Fenster über die volle Breite, Produkte in zwei Spalten
- Passt sich automatisch an das Theme an (Farben, Eckenradien)

## Schnell – auch ohne OpenSearch

- Nutzt Shopwares eigene Produktsuche (MySQL oder – falls vorhanden – OpenSearch)
- Berechnet nur die Hersteller-Facette; Eigenschafts-, Preis- und Bewertungsfilter werden für das Fenster abgeschaltet
- Antworten sind **HTTP-Cache-fähig** und werden zusätzlich im Browser zwischengespeichert
- Anfragen werden beim Tippen gebündelt (Verzögerung einstellbar), veraltete Anfragen abgebrochen
- Ca. 6 KB JavaScript, wird nur auf Seiten mit Suchfeld geladen

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
| Anzahl Produkte | 12 | Produkte im Ergebnisfenster (1–48) |
| Passende Kategorien anzeigen | an | |
| Herstellerfilter anzeigen | an | |
| Wartezeit nach Tastendruck | 180 ms | Kleiner = schnellere Reaktion, größer = weniger Serverlast |

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

Templates erweitern: `nova-search/results.html.twig` enthält die Blöcke `nova_search_results`, `nova_search_empty`, `nova_search_aside`, `nova_search_main` und `nova_search_product`. Nach jeder Anzeige feuert das Storefront-Event `novaSearch/render`.

## Kompatibilität

Shopware 6.7.x (geprüft gegen 6.7.15).

## Lizenz

MIT
