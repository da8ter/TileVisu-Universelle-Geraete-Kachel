
![IP-Symcon](https://img.shields.io/badge/IP--Symcon-Module-blue.svg)

# Universelle Geräte Kachel

Das Modul **Universelle Geräte Kachel** stellt eine flexibel konfigurierbare Geräte-Kachel für die IP-Symcon Visualisierung bereit. Eine Kachel kann Statusinformationen, frei wählbare Variablen, Fortschrittsbalken, Schieberegler, Schalter und Bilder in einem gemeinsamen Layout kombinieren.

## Inhaltsverzeichnis
1. [Funktion](#funktion)
2. [Voraussetzungen](#voraussetzungen)
3. [Installation](#installation)
4. [Konfiguration](#konfiguration)
5. [Visualisierung und Bedienung](#visualisierung-und_bedienung)
6. [PHP-Befehle](#php-befehle)
7. [Changelog](#changelog)

## Funktion
- **Statusbereich**: Anzeige einer zentralen Statusvariable inklusive optionalem Icon, Label, Wert sowie Standard- oder Default-Bild.
- **Universelle Variablenliste**: Darstellung mehrerer Variablen als Text, Fortschrittsbalken, Schieberegler, Schalter oder Bilder; Gruppierung mehrerer Variablen pro Zeile möglich.
- **Fortschrittsbalken**: Optionale zweite Variable als Zusatzzeile oder Marker (z. B. Soll-/Zielwerte), einstellbare Farben; deaktivierte Balken werden automatisch ausgegraut.
- **Schieberegler-Steuerung**: Profilbasierte Min-/Max-/Schrittwerte mit Drag-Steuerung, +/- Schaltern und automatischer Schreib-Verifikation.
- **Schalter & Aktionen**: Bool-, Integer- und String-Variablen (assoziationsbasiert), optionale Skript-Schalter sowie Schalter zum Öffnen von Objekten.
- **Variablelose Zeilen**: Bild- und Schalter-Zeilen können auch ohne direkte Variable über Medienobjekt, Skript oder Objektöffnung konfiguriert werden.
- **Bild- und Icon-Unterstützung**: Eigene Medienobjekte, Symcon-Icons oder vorkonfigurierte Bilder können angezeigt werden.
- **Layout-Steuerung**: Globale Einstellungen für Abstände, Hintergrund, Bildposition, Gruppennamen, Stretch-Modus für Button-Gruppen u. v. m.

## Voraussetzungen
- IP-Symcon Version ≥ 7.2
- Kachelvisualisierung.
- Für benutzerdefinierte Icons/Bilder: entsprechende Medienobjekte in IP-Symcon.

## Installation
1. Im IP-Symcon `Modul-Control` folgende URL hinzufügen:
   ```
   https://github.com/da8ter/TileVisu-Universelle-Geraete-Kachel.git
   ```
   Oder über den Module-Store: in der Suche Universelle Geräte Kachel eingeben und dann installieren.


2. Das Modul aktualisieren/laden.
3. In der Instanzen-Verwaltung eine neue Instanz vom Typ **Universelle Geräte Kachel** anlegen.

## Konfiguration

Die Konfiguration gliedert sich in mehrere Bereiche der Form:

- **Kachel-Konfiguration**
  - Hintergrundbild, Transparenz und Hintergrundfarbe der Kachel.
  - Position und Ausrichtung des Hauptbildes; Standard-Hintergrund aktivieren/deaktivieren.

- **Gerätestatus**
  - Auswahl der Statusvariable, Schriftgröße sowie Anzeigeoptionen für Icon/Label/Wert.
  - Festlegen eines Default-Bildes und Pflege der Profilassoziationen (inkl. Bild-, Icon- und Farbwahl, Fortschrittsbalken-Status).

- **Globale Darstellungsoptionen**
  - Gemeinsame Einstellungen für Fortschrittsbalken (Größe, Farben, Transparenz) und Schalter (Höhe).
  - Steuerung des vertikalen Abstands zwischen Elementen und allgemeine Rundungen.

- **Variablenliste**
  - Zentrale Liste der dargestellten Variablen (Drag & Drop für Reihenfolge).
  - Je Eintrag: Darstellungsart (Text, Fortschrittsbalken, Schieberegler, Schalter, Bild), Anzeigeoptionen, Gruppenzugehörigkeit, Farben, Skript-IDs, Objektziele zum Öffnen und Zweitvariablen für den Fortschrittsbalken.
  - Für Fortschrittsbalken kann eine zweite Variable als Zusatzzeile oder Marker genutzt werden.
  - Für Schieberegler stehen eigene Schieberegler-Farben (Schieberegler-Farbe 1/2) zur Verfügung.
  - Für Schalter lassen sich optionale Skript-IDs, Objektziele zum Öffnen und feste Schalterbreiten hinterlegen (auch ohne Variable).
  - Für Bilder stehen Medienobjekt, Breite und Radius zur Verfügung (auch ohne Variable).

- **Gruppenkonfiguration**
  - Definition der Gruppennamen, Icons und Optionen (Name über Gruppe, Trennlinie, Stretch-Modus).
  - Globale Schriftgröße für Gruppennamen.


## Visualisierung und Bedienung
- Die Kachel wird in der Kachel-Visualisierung angezeigt.
- Schalter reagieren abhängig vom Modus:
  - Boolesche Schalter spiegeln den Variablenzustand.
  - Integer-/String-Schalter werden aus Assoziationen als Mehrfach-Schaltergruppe dargestellt.
  - Skript-Schalter lösen das hinterlegte Skript aus, zeigen während der Ausführung ein drehendes FontAwesome-Icon und leuchten für die Dauer des Spins auf.
  - Schalter zum Öffnen von Objekten öffnen das konfigurierte Zielobjekt.
- Deaktivierte Fortschrittsbalken und Werte werden automatisch ausgegraut und mit `-` angezeigt.
- Schieberegler unterstützen Drag und +/- Schalter, setzen Werte optimistisch und verifizieren anschließend, ob der Wert tatsächlich in die Variable geschrieben wurde. Falls nicht, springt der Schieberegler automatisch auf den tatsächlichen Variablenwert zurück.
- Schieberegler-Farben: Schieberegler verwenden eigene Schieberegler-Farben (Schieberegler-Farbe 1/2). Sind diese nicht gesetzt, wird auf die Fortschrittsbalken-Farben zurückgefallen.

## PHP-Befehle
Das Modul stellt aktuell keine öffentlichen PHP-Befehle bereit. Die komplette Funktionalität erfolgt über die Instanz-Konfiguration.

## Changelog
- **1.0.13**
  - Fix: Modul-/Bibliotheksname auf „Universelle Geräte Kachel“ ohne Bindestrich vereinheitlicht und Klassenname entsprechend angepasst.
- **1.0.12**
  - Dokumentation: README-Funktionsumfang ergänzt (Schieberegler-Darstellungsart, Marker/Zweitvariable, assoziationsbasierte Mehrfach-Schalter, Schalter zum Öffnen von Objekten, variablelose Bild-/Schalter-Zeilen).
- **1.0.11**
  - Cleanup: JavaScript-Debugausgaben in der Visualisierung entfernt.
- **1.0.10**
  - Bugfix: Konfigurierte Gruppennamen werden nun auch im Variablen-Edit-Dialog korrekt angezeigt.
  - Technisch: Neben den statischen Group-Select-Optionen werden jetzt auch die Group-Optionen im dynamischen `form`-Script zur Laufzeit ersetzt.
- **1.0.9**
  - Frontend-Sichttexte (`On/Off`, `Target SOC`, `Device Image`, `Image`) werden nun zentral über `locale.json` bereitgestellt und als `uiTexts` an die Visualisierung übertragen.
- **1.0.7**
  - Refactoring: `GetConfigurationForm()` aktualisiert Group-Optionen jetzt strukturiert im JSON-Array statt über fragile String-Replacements.
  - Lifecycle: `ApplyChanges()` führt Side-Effects nur noch bei Kernel-Runlevel `KR_READY` aus.
  - Cleanup: `Destroy()` entfernt den registrierten WebHook-Eintrag (`/hook/udtimages/<InstanceID>`) wieder.
  - Diagnostik: Error-Suppression (`@...`) und leere `catch`-Blöcke an zentralen Stellen reduziert; Fehler werden via `SendDebug` protokolliert.
- **1.0.6**
  - Verbesserung: Lokalisierungen in `locale.json` für Backend-/Frontend-Sichttexte vervollständigt (Deutsch und Englisch).
  - Ergänzt: Fehlende Schlüssel aus `form.json` (inkl. dynamischer Formularzeilen) und Status-Fallback-Texte.
- **1.0.5**
  - Bugfix: Statusvariable zeigt Icon jetzt auch dann korrekt, wenn das Icon nur über Associations (Profile/OPTIONS/TEMPLATE/PRESENTATION) bereitgestellt wird.
  - Technisch: `GetIcon()` nutzt einen zusätzlichen Association-Fallback auf das aktuell aktive Association-Icon.
- **1.0.4**
  - Bugfix: Wenn eine Variable mehrfach in der Kachel dargestellt wird (z. B. in Gruppen oder in mehreren Sektionen), werden nun alle Instanzen korrekt aktualisiert.
    - Fortschrittsbalken-Mini-/Vollupdate aktualisiert jetzt alle `.progress-container` mit gleicher `data-variable-id`.
    - Schieberegler-Mini-Updates berücksichtigen alle `.slider-container`-Instanzen.
    - Zweitvariablen-/Zielmarker-Updates (Fortschrittsbalken) werden auf alle zutreffenden Container angewendet.
  - Änderung: Skript-Schalter werden optisch stets als aktiv dargestellt (keine Abdunkelung), inkl. Spin-Phase und danach.
- **1.0.3**
  - Verbesserte Skript-Schalter-Anzeige inklusive Spin-Status und Opacity-Handling.
  - Korrekturen am Fallback-Icon und an der Icon-Wiederherstellung.
  - Gleichmäßige Breitenverteilung für mehrere Fortschrittsbalken in Gruppen (alle Balken teilen den verfügbaren Platz nun 1:1:1 ...).
- **1.0.2**
  - Diverse Fehlerkorrekturen bei Default-Bildern und Progress-Anzeige.
- **1.0.1**
  - Initiale öffentliche Version.