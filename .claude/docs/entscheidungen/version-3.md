# Version 3.0.0: Review und Entscheidungen

Im September 2026 wurde die Kachel nach dem Refactoring 2.1.0 (Traits unter `libs/`) einem Code-Review unterzogen. Die Befunde tragen Kennungen **F1–F15** (gemeldet) und **N1–N21** (Nebenbefunde, N11 als a–e); jeder ist in einem eigenen Commit behoben, die Kennung steht in der Commit-Botschaft (`git log --grep='(F7'`). Was sich für Nutzer ändert, steht im Changelog des `README.md`.

## Entscheidungen

- **Zeilen-Protokoll statt Einzelwerten** (F6, F8, F9, F14, N6, N7). Vorher schickte jede Wertänderung einzelne Schlüssel je Variable; mehrere Zeilen derselben Variable, wertabhängige Icons und Ziel-Marker gerieten dabei durcheinander. Jetzt hat jede Zeile einen Schlüssel (Index in der Variablenliste), und ein Update schickt die betroffenen Zeilen **vollständig** in `{"rows": [...]}`. Ein Statuswechsel aktualisiert Statusteil und Balkenzeilen, baut die Kachel aber nicht neu auf. Ein Speichern der Konfiguration baut sie dagegen vollständig neu auf (F14, N15).
- **Bedienung über `row_<key>`** (F1). `RequestAction` nimmt nur Idents konfigurierter Zeilen an und wirkt nur auf deren Ziel (Variable einer Button-/Reglerzeile oder Script eines Script-Buttons). Grund: Kachel-Idents sind für jeden Visu-Browser erreichbar.
- **Bool-Buttons senden den Wunschwert** statt umzuschalten (`ad0ee8c`). Ein Doppelklick oder ein verspätetes Echo schaltet so nicht zurück.
- **Texte immer als Text** (F2, N21). Die Render-Funktionen bauen HTML-Bausteine und maskieren jeden Wert und jede Beschriftung (`esc()`); Buttons kommen ohne Inline-`onclick` aus. Kachel-Nachrichten und Startstand kodiert eine Stelle (`PayloadTrait`, mit `JSON_HEX_TAG`), sodass `</script>` oder `<!--<script>` in einem Wert nicht ausbrechen.
- **Bild-Hook nur für Medien dieser Kachel** (F3), Antworten unter `ScriptOutputBufferLimit` (F12): Ein zu großes Bild wird zum Platzhalter mit Meldung im Log, statt dass Symcon die Antwort ersetzt.
- **Bilder mit Inhaltsversion** (F7, `b8c1a84`). Alle konfigurierten Medien sind angemeldet (`MM_UPDATE`) und referenziert; Bildadressen tragen `v=<Version>`, Assets `v=<Dateistand>`. Nur versionierte Adressen werden lange (privat) gecacht; unter alter Version kommt der aktuelle Inhalt ohne Cache. Geänderte Kamerabilder erscheinen dadurch sofort.
- **Module Strict, nativer Hook, Symcon ab 8.1** (N1, N17 → Version 3.0.0). `RegisterHook()` in `Create()` statt Einträgen im WebHook Control. Vorher (N20) war der Hook nach dem Refactoring tot: `ProcessHookData(): void` in einer `IPSModule`-Klasse vertrug sich nicht mit Symcons `HookInstance`-Aufsatz (Plattformwissen `hooks-und-grenzen.md`).
- **`module.html` als Gerüst** (N11a), Teile in `html/css` und `html/js`, Zusammenbau beim Ausliefern wie in der Raum-Kachel.

## Verworfen

- **Zusammengesetzte Seite und Icon-Mapping im Instanzpuffer** (Zweig `stufe3-kachel`). Gemessen sparte das 0,03–0,22 ms je Öffnen und kostete dauerhaft Kernel-Speicher je Instanz (Messwerte nicht am Code prüfbar).

Stand: geprüft gegen den Code am 08.10.2026
