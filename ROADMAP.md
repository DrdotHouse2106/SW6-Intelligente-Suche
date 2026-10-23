# Roadmap Nova Suche

Ziel: Die Nova Suche soll einen externen Such-Dienst vollständig ersetzen können – Suchfenster,
Regeln, Auswertungen, Empfehlungen und Assistent. Die Liste ist bewusst vollständig; umgesetzt
wird **Schritt für Schritt**. Erledigte Punkte werden hier abgehakt und im Changelog genannt.

**Legende:** ✅ vorhanden · 🟡 teilweise · ⬜ geplant

---

## Phase 1 – Umstieg ohne Funktionsverlust

Alles, was Kunden heute im Suchfenster sehen und was für den Umstieg nötig ist.

### 1.1 Startansicht des Suchfensters (noch nichts getippt)

- ✅ Beliebte Suchanfragen (automatisch + von Hand, Sperrliste)
- ✅ Aktuelle Highlights als Slider
- ✅ Zuletzt angesehene Produkte
- ✅ **Zuletzt gesucht**: eigene letzte Suchbegriffe des Besuchers als Chips, einzeln
  entfernbar (✕) und „Alles löschen“. Nur im Browser gespeichert, nur mit Komfort-Einwilligung
- 🟡 Highlights wahlweise automatisch: „am beliebtesten“, „Bestseller“, „neu“ (1.5.0), nur mit Bild (1.7.1) – Produktstream folgt
- ⬜ **Persönliche Vorschläge beim Öffnen**: Produkte passend zu dem, was der Besucher zuletzt
  angesehen oder gesucht hat (gleiche Kategorie, gleiches Fahrzeugmodell bzw. gleiche Eigenschaft),
  bei angemeldeten Kunden auch aus früheren Bestellungen („Passt zu Ihrem Fahrzeug“) – nur mit Einwilligung

### 1.2 Ergebnisse beim Tippen

- ✅ Produktkarten mit Bild, Preis, Streichpreis, Rabatt-Markierung
- ✅ Filter Marken + Preis, Sortierung, passende Kategorien, Nachladen beim Scrollen
- ✅ **Suche nach Artikel-, Hersteller- und OE-Nummern** (1.3.0), Checkbox „Nur exakte Teilenummer“ (1.4.0) inkl. Schreibweisen mit/ohne
  Leerzeichen, Punkt, Bindestrich (z. B. `12345 678` / `12345678`)
- ✅ Mindestanzahl Zeichen, ab der gesucht wird (einstellbar)
- ✅ „In den Warenkorb“-Button direkt auf der Karte (Menge 1, Varianten → Produktseite)
- ✅ Verfügbarkeits-Hinweis auf der Karte (nicht vorrätig / Lieferzeit), optional ausblenden
- ✅ Bewertungssterne auf der Karte (Shopware-Produktbewertungen)
- 🟡 Weitere Filter wählbar: Eigenschaften (z. B. Fahrzeugmodell) ✅ 1.5.0 – Kategorie, „auf Lager“,
  „im Angebot“ folgen
- ⬜ Zustand in der Browser-URL (Begriff, Filter, Sortierung): Neuladen und „Zurück“ stellen das
  Fenster wieder her; Suchbegriff bleibt beim Weiterklicken erhalten (abschaltbar)
- ⬜ Layout wählbar: **Vollbild** (wie jetzt) oder **schwebend** unter dem Suchfeld

### 1.3 Keine Treffer

- ✅ Hinweistext bei 0 Treffern
- ✅ Claude-Umschreibung für Begriffe ohne Treffer (dauerhaft gespeichert, Kostenlimit)
- ✅ **Empfohlene Produkte bei 0 Treffern** (beliebte Produkte oder frei wählbarer Produktstream)
- ✅ Eigener Text/HTML für „keine Treffer“, übersetzbar (Textbaustein), z. B. mit Telefonnummer
  und Link „Teil anfragen“
- ✅ **Schwestershops**: bei 0 Treffern Treffer aus anderen Shops derselben Installation (1.9.0)
- ✅ **Hinweise** nach Wörtern im Suchbegriff (z. B. Fabrikat/Modell → anderer Shop) (1.9.0)
- ✅ „Teil anfragen“ direkt im Suchfenster (E-Mail oder Telefon, 1.10.0), Kontaktformular vorausgefüllt (1.9.0)
- ⬜ Hinweise und Schwestershop-Treffer auch auf der Suchergebnisseite und in der Store-API (App)

### 1.4 Regeln (Merchandising)

- ✅ **Synonyme**: Gruppen gleichwertiger Begriffe (z. B. `bremsklötze, bremsbeläge`) –
  seit 1.7.0 als Liste unter *Erweiterungen → Nova Suche* mit CSV-Import/-Export
- ✅ **Weiterleitungen**: Suchbegriff → Seite (z. B. `kundenkonto` → Konto, `versand` →
  Versandinfos, `widerruf` → Widerrufsseite)
- ✅ **Ausgeschlossene Produkte**: Produkte/Kategorien/Eigenschaften, die nie im Suchfenster
  erscheinen
- 🟡 Pro Verkaufskanal pflegbar (je Sprache noch nicht)

### 1.5 Übernahme der bestehenden Daten

- ✅ Import vorhandener Synonym-Gruppen und Weiterleitungen als CSV (1.7.0)
- ✅ Import von Suchbegriffen und Klickdaten der letzten Monate (CSV), damit „Beliebte
  Suchanfragen“ und das Lernen nicht bei null starten (1.8.0)
- ✅ Umstiegs-Checkliste in der README (altes Plugin deaktivieren, Skript entfernen)

---

## Phase 2 – Auswertungen in der Administration

Seit 1.6.0: *Erweiterungen → Nova Suche*, je Verkaufskanal und Zeitraum (Gerät noch nicht).

### 2.1 Kennzahlen mit Vergleich zum Vorzeitraum

- ✅ Anzahl Suchen und Veränderung in %
- ✅ Klickrate (Anteil der Suchen mit Klick)
- ⬜ Umsatz über die Suche und Konversionsrate der Suchsitzungen
- ⬜ Anteil der Sitzungen bzw. Käufer, die die Suche nutzen; Kaufwahrscheinlichkeit mit/ohne Suche
- ⬜ Durchschnittliche Position geklickter Ergebnisse
- ⬜ Anteil Suchen ohne Interaktion (Fenster geschlossen ohne Klick)
- ✅ Anteil Suchen ohne Treffer
- 🟡 Verlaufsdiagramme (Suchen, Klickrate, Verkäufe)

### 2.2 Tabellen (sortierbar, durchsuchbar, CSV-Export)

- 🟡 Suchbegriffe: Anzahl, Klickrate, Ø Klickposition
- 🟡 Geklickte Produkte: Klicks, Klickrate, Ø Position
- 🟡 **Suchen ohne Treffer** mit Button **„Beheben“** → Synonym und Weiterleitung ✅ (1.7.0);
  Umschreibung, feste Produkte und Claude-Vorschlag mit einem Klick folgen
- ✅ Begriffe mit vielen Suchen, aber 0 % Klickrate (Treffer passen nicht)

### 2.3 Claude in der Administration

- ✅ Kosten und Limit per Konsole (`nova-search:claude-usage`)
- ⬜ Übersicht der gespeicherten Umschreibungen: prüfen, bearbeiten, löschen, sperren
- ✅ Verbrauch/Limit als Kachel
- ⬜ **KI-Synonymvorschläge** aus dem Suchverhalten (Begriffe, nach denen nacheinander gesucht
  wird) – annehmen oder ablehnen

---

## Phase 3 – Weitere Regeln und bessere Treffer

- ⬜ **Feste Ergebnisse** je Suchbegriff: Produkte oben anheften oder in fester Reihenfolge
- ⬜ **Boosting**: Hersteller, Kategorien, Eigenschaften, Lagerbestand, Neuheiten, Marge
  höher/niedriger gewichten – global oder je Begriff, mit Zeitraum
- ⬜ **Banner** je Suchbegriff (Bild + Link, mit Zeitraum)
- ⬜ Gewichtung der Suchfelder und Relevanzkriterien (Name, Nummer, Hersteller, Beschreibung,
  Eigenschaften) – baut auf *Einstellungen → Suche* von Shopware auf
- ⬜ **Suchvorschläge/Autovervollständigung** während des Tippens („mot“ → „motorhaube“,
  „motoraufhängung“) aus Produktnamen, Kategorien und erfolgreichen Suchen
- ⬜ Personalisierung: Ergebnisse leicht nach bisherigem Verhalten des Besuchers sortieren
  (z. B. zuletzt angesehenes Fahrzeugmodell), nur mit Einwilligung
- ⬜ Mehrere Ergebnis-Reiter: Produkte · Kategorien · Inhaltsseiten (Ratgeber, FAQ, Versand)

---

## Phase 4 – Komfortfunktionen im Suchfenster

- ⬜ **Sprachsuche** (Mikrofon-Symbol, Browser-Spracherkennung, Desktop/Mobil getrennt schaltbar)
- ⬜ **Geführte Suche**: passende Filter als klickbare Vorschläge über den Ergebnissen
  (z. B. Fahrzeugmodell, „Neuteil“)
- ⬜ **Ähnliche Produkte** („Mehr wie dieses“) direkt von der Produktkarte aus
- ⬜ **Bildsuche**: Foto eines Teils hochladen → ähnliche Produkte (Claude-Bildanalyse, mit
  Kostenlimit)
- ⬜ Logo und Farben des Fensters separat einstellbar (Standard: aus dem Theme)
- ⬜ Eigenes CSS und anpassbare Produktkarten-Vorlage (Twig-Block) – mit Hinweis auf Risiken

---

## Phase 5 – Produktempfehlungen

Automatische Produkt-Slider aus den gesammelten Daten, als Erlebniswelten-Element und
Theme-Blöcke, mit Auswertung (Einblendungen, Klickrate, Umsatz).

- ✅ Zuletzt angesehen (Nova Theme)
- ⬜ Startseite: **am beliebtesten** / **Bestseller**
- ⬜ Produktseite: **ähnliche Produkte**
- ⬜ Produktseite: **„Andere Kunden sahen auch“**
- ⬜ Produktseite/Warenkorb: **„Häufig zusammen gekauft“** (aus Bestellungen)
- ⬜ Hervorgehobene Produkte (von Hand)
- ⬜ Filter je Slider (Kategorie, Hersteller, nur lieferbar) und Ersatzlogik, wenn zu wenige
  Produkte gefunden werden
- ⬜ Auswertung je Slider in der Administration

---

## Phase 6 – Kategorie-Merchandising

- ⬜ Produkte in Kategorie-Listings oben anheften
- ⬜ Boosting-Regeln auch für Kategorien (Bestseller, Lagerbestand, Neuheiten)
- ⬜ Reihenfolge per Ziehen und Ablegen mit Vorschau
- ⬜ Optional personalisierte Reihenfolge, Auswertung je Kategorie

---

## Phase 7 – Produktberater und Assistent

- ⬜ **Produktberater (Quiz)**: Fragen wie Fahrzeug → Baujahr → Baugruppe führen zu passenden
  Produkten; Erscheinungsbild anpassbar, Auswertung (Starts, Abschlüsse, Klickrate), CSV-Export
  der Antworten; Anbindung an die Fahrzeug-Schnellauswahl
- ⬜ **Einkaufsassistent mit Claude** als Chat im Shop: versteht Fragen („Was brauche ich für
  neue Bremsen an der Ente?“), sucht mit der Nova Suche, kennt Versand-/Zahlungsinfos aus einer
  **Wissensdatenbank** (Shopseiten, FAQ), Gesprächsprotokolle in der Administration,
  Kostenlimit wie bei der Claude-Stufe

---

## Phase 8 – Anbindungen

- ✅ Tag Manager: Suche, Listen, Klicks im Datenlayer (Nova Tag Manager)
- ✅ Store-API für die App
- ✅ Dynamische Lieferzeiten auf den Suchkarten
- ⬜ Newsletter-/Marketing-Tools: Suchinteressen als Kundenmerkmal übergeben (optional)

---

## Bewusst nicht geplant

- Externer Index/„tägliche Indexierung“: Die Nova Suche nutzt die Live-Daten von Shopware –
  Preise, Bestand und neue Produkte sind sofort aktuell.
