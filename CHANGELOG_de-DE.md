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
