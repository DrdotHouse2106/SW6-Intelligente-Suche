# 1.7.1
- Neu: Schalter „Nur Produkte mit Bild“ für Aktuelle Highlights und Empfehlungen ohne Treffer (Standard: an)

# 1.7.0
- Neu: Synonyme und Weiterleitungen als eigene Listen unter „Erweiterungen → Nova Suche“ (Reiter neben der Auswertung) – Suche, Aktiv-Schalter, Bearbeiten, Löschen, Verkaufskanal je Eintrag
- Neu: CSV-Import (auch Exporte anderer Suchdienste) und CSV-Export
- Neu: „Ohne Treffer“ in der Auswertung mit Knöpfen „Synonym“ und „Weiterleitung“ zum direkten Beheben
- Bisherige Einträge aus den Textfeldern der Konfiguration werden beim Update übernommen; die Textfelder entfallen

# 1.6.0
- Neu: Menüpunkt „Erweiterungen → Nova Suche“ mit Auswertung – Kennzahlen mit Vergleich zum Vorzeitraum, Suchen pro Tag, Suchbegriffe, ohne Treffer (inkl. Claude-Umschreibung), geklickte Produkte, Claude-Kosten, CSV-Export und Knopf zu den Einstellungen
- Tageswerte je Suchbegriff und Produkt (nach 2 Jahren gelöscht)

# 1.5.0
- Neu: „Zuletzt gesucht“ als Chips in der Startansicht (einzeln entfernbar, „Alles löschen“; nur im Browser, mit Zustimmung)
- Neu: Aktuelle Highlights automatisch – Bestseller, beliebt in der Suche oder neu
- Neu: Eigenschaften als Filter im Suchfenster (z. B. Fahrzeugmodell)
- Neu: Produktkarten mit Bewertungssternen, „Derzeit nicht lieferbar“ und Button „In den Warenkorb“
- Neu: Bei 0 Treffern eigener Text je Shop und Produktempfehlungen
- Neu: Mindestanzahl Zeichen einstellbar
- „In den Warenkorb“ aus dem Suchfenster zählt fürs Lernen wie ein Klick

# 1.4.0
- Neu: Checkbox „Nur exakte Teilenummer“ im Suchfenster – zeigt ausschließlich Produkte mit genau dieser Nummer, sonst „Keine Teilenummer … gefunden“; bleibt für die Sitzung erhalten, gilt auch für Enter; Store-API-Parameter exactNumber
- Begriffe aus der Nummernsuche werden nicht mehr an Claude zur Umschreibung gegeben

# 1.3.0
- Neu: Nummernsuche – Artikel-, Hersteller-, EAN- und OE-Nummern (Zusatzfelder, Eigenschaften) unabhängig von Leerzeichen, Punkten, Bindestrichen und Schrägstrichen; auch über den Nummernanfang
- Nummern-Index wird beim Speichern von Produkten aktualisiert, komplett im stündlichen Lauf bzw. mit bin/console nova-search:numbers

# 1.2.0
- Neu: Synonyme (eine Gruppe pro Zeile) – gelten in Suchfenster, Suchergebnisseite und App; Mehrwortsuchen bleiben genau
- Neu: Weiterleitungen für Suchbegriffe (z. B. „kundenkonto“ → Konto) – Enter führt direkt zum Ziel, im Suchfenster erscheint ein Link, die Store-API liefert das Feld `redirect`
- Neu: Ausgeschlossene Produkte – Abverkauf ohne Bestand, Produkte, Kategorien, Tags oder Ja/Nein-Zusatzfeld
- README: Umstieg von einem externen Suchdienst

# 1.1.0
- Monatliches Kostenlimit für die Claude-Stufe (Standard 5 USD, 0 = aus) mit Warnschwelle (Standard 80 %)
- Verbrauch wird je Monat und Modell aus den gemeldeten Tokens berechnet und gespeichert
- Vor jedem Aufruf wird geprüft, ob die Höchstkosten noch ins Restbudget passen; bei erreichtem Limit bleiben Begriffe offen
- Neuer Befehl bin/console nova-search:claude-usage, Export-Typ claude-usage

# 1.0.0
- Erste Version: Suchfenster mit Bildern, Filtern (Marken, Preis), Sortierung und automatischem Nachladen
- Lernt aus Suchen, Klicks, Warenkorb und Käufen (ohne personenbezogene Daten)
- Beliebte Suchanfragen automatisch, „Zuletzt angesehen“ in der Startansicht
- Claude (Haiku 5.5) verbessert Suchbegriffe ohne Treffer dauerhaft – jede Anfrage nur einmal
- Store-API für Apps (Suche, beliebte Suchanfragen, Tracking)
- Export der Suchdaten als CSV: bin/console nova-search:export
- Das Suchfenster ist nach der Installation aus und wird pro Verkaufskanal eingeschaltet
