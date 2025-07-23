# Universelle Geräte-Kachel
![Universal Device Status Tile](https://github.com/da8ter/images/blob/1c5fe63e9757e81e6d8c4c84a63e0b39fa00247c/waschmaschine.jpg)

Support: https://community.symcon.de/t/html-kachelsammlung-bewohnerstatus-waermepumpe-etc/

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Kachelkonfiguration](#5-kachelkonfiguration)

### 1. Funktionsumfang

* **Universelle Geräte-Kachel** für die Anzeige beliebiger IP-Symcon Variablen mit flexibler Konfiguration
* **Dynamische Variablenliste** - Füge beliebig viele Variablen hinzu und konfiguriere deren Darstellung individuell
* **Drei Anzeigetypen** pro Variable:
  - **Text**: Einfache Textanzeige mit Icon, Beschriftung und Wert
  - **Fortschrittsbalken**: Grafische Darstellung von Prozentwerten (0-100%)
  - **Schalter**: Interaktive Schaltflächen für Bool-Variablen mit automatischen Profil-Farben
* **Gruppenverwaltung** - Organisiere Variablen in Gruppen für bessere Übersicht
* **Gerätestatus-Bereich** - Optionaler fester Status-Bereich mit Profilzuordnungen
* **Vollständig konfigurierbar** - Farben, Schriftgrößen, Anzeigeoptionen für jede Variable einzeln

### 2. Voraussetzungen

- IP-Symcon ab Version 7.1
- TileVisu ab Version 3.7

### 3. Software-Installation

* Über den Module Store: "TileVisu Universal Device Status Tile"
* Über das Module Control folgende URL hinzufügen:
```
https://github.com/da8ter/TileVisu-Universal-Device-Status-Tile.git
```

### 4. Einrichten der Instanzen in IP-Symcon

Unter 'Instanz hinzufügen' kann die Universelle Geräte-Kachel mithilfe des Schnellfilters gefunden werden.  
(Suchbegriff: TileVisu, Universal, Device, Status oder Kachel)
- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Kachelkonfiguration

**Grundsätzlicher Hinweis:**  
Die Kachel nutzt ein universelles Variablen-System. Du kannst beliebig viele Variablen hinzufügen und deren Darstellung individuell konfigurieren. Standardmäßig sind alle Elemente ausgeblendet und werden nur bei entsprechender Konfiguration angezeigt.

#### Kacheldesign
Name | Beschreibung
-----|-------------
Standard-Hintergrundbild | Ein-/Ausschalten des Standard-Hintergrundbildes
Hintergrundbild | Auswahl eines eigenen Medienobjekts als Hintergrund
Transparenz Bild (Werte von 0 bis 1) | Transparenz des Hintergrundbildes
Kachelhintergrundfarbe | Farbe des Kachelhintergrunds
Elementabstand (vertikaler Abstand zwischen Elementen) | Vertikaler Abstand zwischen Variablen-Elementen (0-50px)

#### Bild-Konfiguration
Name | Beschreibung
-----|-------------
Bildauswahl | Waschmaschine, Wäschetrockner oder eigene Bilder
Bild Breite | Breite des Bildes in Prozent der Kachelbreite
Eigenes Bild AN | Medienobjekt für Status "An"
Eigenes Bild AUS | Medienobjekt für Status "Aus"

#### Gerätestatus (Optional)
Name | Beschreibung
-----|-------------
Status-Variable | Variable für Gerätestatus mit Variablenprofil
Icon anzeigen/Beschriftung anzeigen/Wert anzeigen | Anzeigeoptionen für Status-Element
Schriftgröße | Schriftgröße des Status-Textes
Profilzuordnungen | Automatische Zuordnung von Farben und Bildern je Status

#### Fortschrittsbalken-Konfiguration (Global)
Name | Beschreibung
-----|-------------
Balken-Höhe | Höhe aller Fortschrittsbalken (10-40px)
Eckenradius | Rundung der Fortschrittsbalken (0-20px)
Hintergrundfarbe | Hintergrundfarbe der Fortschrittsbalken
Hintergrund-Transparenz | Transparenz des Hintergrunds (0-100%)
Text innerhalb des Fortschrittsbalkens anzeigen | Text im oder neben dem Fortschrittsbalken
Text-Innenabstand | Innenabstand des Textes (0-20px)

#### Anzuzeigende Variablen (Hauptfunktion)

**Universelle Variablenliste** - Das Herzstück der Kachel:

##### Grundkonfiguration pro Variable:
Name | Beschreibung
-----|-------------
Variable | Auswahl der anzuzeigenden IP-Symcon Variable
Anzeigetyp | **Text**, **Fortschrittsbalken** oder **Schalter**
Gruppe | Zuordnung zu einer Anzeigegruppe
Beschriftung (Überschreibt den Variablenname) | Eigener Beschriftungstext

##### Anzeigeoptionen (für alle Anzeigetypen):
Name | Beschreibung
-----|-------------
Icon anzeigen | Variable-Icon anzeigen (automatisch aus Profil/Darstellung)
Beschriftung anzeigen | Variable-Beschriftung anzeigen
Wert anzeigen | Aktuellen Wert anzeigen
Schriftgröße | Schriftgröße für diese Variable (8-50px)
Textfarbe | Textfarbe für diese Variable

##### Text-spezifische Optionen:
- Zeigt Icon, Beschriftung und Wert horizontal nebeneinander an
- Vollständig über Anzeigeoptionen konfigurierbar

##### Fortschrittsbalken-spezifische Optionen:
Name | Beschreibung
-----|-------------
Fortschrittsbalken-Farbe 1 oder Button = aktiv | Startfarbe des Farbverlaufs
Fortschrittsbalken-Farbe 2 oder Button = inaktiv | Endfarbe des Farbverlaufs
Zweite Variable | Optionale zweite Variable für erweiterte Anzeige
Zusätzliche Informationen im Fortschrittsbalken | Konfiguration für zweite Variable

##### Schalter-spezifische Optionen:
Name | Beschreibung
-----|-------------
Button-Breite (nur für Buttons) | Breite des Buttons (50-300px)
Automatische Profil-Farben | Automatische Farbextraktion aus Variablenprofil
Button-Inhalt | Was im Button angezeigt wird (Icon + Beschriftung, Nur Icon, Nur Beschriftung, Nur Wert)

#### Gruppenverwaltung
- **Gruppen**: Erstelle eigene Gruppen für bessere Organisation
- **Automatische Dropdown-Updates**: Erstellte Gruppen erscheinen sofort in der Variablenliste
- **Flexible Zuordnung**: Jede Variable kann einer Gruppe zugeordnet werden

#### Interaktive Funktionen
- **Schalter-Klicks**: Bool-Variablen können direkt über Schalter-Klicks geschaltet werden
- **Profil-Integration**: Automatische Farb- und Icon-Extraktion aus IP-Symcon Variablenprofilen
- **Echtzeit-Updates**: Alle Änderungen werden automatisch über das MessageSink-System übertragen

#### Anwendungsbeispiele

**Waschmaschine komplett:**
1. Gerätestatus: Status-Variable mit Profil (Aus, Waschen, Schleudern, Fertig)
2. Anzuzeigende Variablen: 
   - Programm (Text)
   - Fortschritt (Fortschrittsbalken, 0-100%)
   - Restzeit (Text)
   - Verbrauch heute (Text)

**Smart Home Zentrale:**
1. Anzuzeigende Variablen:
   - Außentemperatur (Text)
   - Heizung EG (Schalter)
   - Heizung OG (Schalter)  
   - PV-Leistung (Fortschrittsbalken)
   - Stromverbrauch (Text)

**Schalter-Gruppe Beleuchtung:**
1. Gruppenverwaltung: "Wohnzimmer Licht"
2. Anzuzeigende Variablen:
   - Deckenlampe (Schalter, Gruppe: "Wohnzimmer Licht")
   - Stehlampe (Schalter, Gruppe: "Wohnzimmer Licht")
   - Ambiente (Schalter, Gruppe: "Wohnzimmer Licht")

Die Kachel ist vollständig universell einsetzbar und passt sich automatisch an deine IP-Symcon Konfiguration an!

__Fortschrittsbalken__

Stellt den aktuellen Programmfortschritt grafisch dar. Kann prinzipiell jede Variable abbilden die einen wert zwischen 0-100% liefert.
Name     | Beschreibung
-------- | ------------------
Programmfortschritt|Eine Variable, die einen Wert zwischen 0-100% liefert.
Restlaufzeit|Eine Variable, die eine Restlaufzeit in Sekunden liefert.
Schriftgröße|Die Schriftgröße der Balkenbeschriftung in em.
Farbe 1|Farbe 1 des Balken-Farbverlaufs.
Farbe 2|Farbe 2 des Balken-Farbverlaufs.

__Engergieverbrauch/Kosten__

Name     | Beschreibung
-------- | ------------------
Aktuelle Leistungsaufnahme|Eine Variable, die die aktuelle Leistungsaufnahme liefert.
Verbrauch Heute:|Eine Variable, die den heutigen Energieverbrauch liefert.
Kosten heute:|Eine Variable, die die Stromkosten des aktuellen Tages liefert.
Schriftgröße:|Schriftgröße der Energieverbrauchanzeige in em
