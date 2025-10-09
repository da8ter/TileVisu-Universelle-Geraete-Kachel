
![IP-Symcon](https://img.shields.io/badge/IP--Symcon-Module-blue.svg)

# Universal Device Tile

Das Modul **Universal Device Tile** stellt eine flexibel konfigurierbare Geräte-Kachel für die IP-Symcon Visualisierung bereit. Eine Kachel kann Statusinformationen, frei wählbare Variablen, Progress-Balken, Buttons und Bilder in einem gemeinsamen Layout kombinieren.

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
- **Universelle Variablenliste**: Darstellung mehrerer Variablen als Text, Progress-Balken, Buttons oder Bilder; Gruppierung mehrerer Variablen pro Zeile möglich.
- **Progress-Balken**: Zweite Wertezeile, einstellbare Farben, deaktivierte Balken werden automatisch ausgegraut.
- **Buttons & Skripte**: Boolesche Variablen oder Skriptaufrufe mit individuellen Icons, Beschriftungen und Spin-Indikator während der Ausführung.
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
3. In der Instanzen-Verwaltung eine neue Instanz vom Typ **Universal Device Tile** anlegen.

## Konfiguration

Die Konfiguration gliedert sich in mehrere Bereiche der Form:

- **Tile configuration**
  - Hintergrundbild, Transparenz und Hintergrundfarbe der Kachel.
  - Position und Ausrichtung des Hauptbildes; Standard-Hintergrund aktivieren/deaktivieren.

- **Device status**
  - Auswahl der Statusvariable, Schriftgröße sowie Anzeigeoptionen für Icon/Label/Wert.
  - Festlegen eines Default-Bildes und Pflege der Profilassoziationen (inkl. Bild-, Icon- und Farbwahl, Progressbar-Status).

- **Globale Darstellungsoptionen**
  - Gemeinsame Einstellungen für Progress-Balken (Größe, Farben, Transparenz) und Buttons (Höhe).
  - Steuerung des vertikalen Abstands zwischen Elementen und allgemeine Rundungen.

- **VariablesList**
  - Zentrale Liste der dargestellten Variablen (Drag & Drop für Reihenfolge).
  - Je Eintrag: Display-Typ (`text`, `progress`, `button`, `image`), Anzeigeoptionen, Gruppenzugehörigkeit, Farben, Skript-IDs, Zweitvariablen für Progress u. a.
  - Für `button`-Einträge lassen sich optionale Skript-IDs und fixe Button-Breiten hinterlegen.
  - Für `image`-Einträge stehen Medienobjekt, Breite und Radius zur Verfügung.

- **Group Configuration**
  - Definition der Gruppennamen, Icons und Optionen (Name über Gruppe, Trennlinie, Stretch-Modus).
  - Globale Schriftgröße für Gruppennamen.

> **Hinweis:** Änderungen an Variablen oder Medien werden durch das Modul überwacht und automatisch in die Visualisierung eingespielt.

## Visualisierung und Bedienung
- Die Kachel wird im WebFront bzw. in unterstützten Visualisierungen angezeigt.
- Buttons reagieren abhängig vom Modus:
  - Boolesche Buttons spiegeln den Variablenzustand.
  - Script-Buttons lösen das hinterlegte Skript aus, zeigen während der Ausführung ein drehendes FontAwesome-Icon und leuchten für die Dauer des Spins auf.
- Deaktivierte Progress-Balken und Werte werden automatisch ausgegraut und mit `-` angezeigt.

## PHP-Befehle
Das Modul stellt aktuell keine öffentlichen PHP-Befehle bereit. Die komplette Funktionalität erfolgt über die Instanz-Konfiguration.

## Changelog
- **1.0.3**
  - Verbesserte Script-Button-Anzeige inklusive Spin-Status und Opacity-Handling.
  - Korrekturen am Fallback-Icon und an der Icon-Wiederherstellung.
  - README nach Symcon-Vorgaben ergänzt.
- **1.0.2**
  - Diverse Fehlerkorrekturen bei Default-Bildern und Progress-Anzeige.
- **1.0.1**
  - Initiale öffentliche Version.