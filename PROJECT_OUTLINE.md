Fetched [https://de.wikipedia.org/wiki/Dobble](https://de.wikipedia.org/wiki/Dobble)
Completed with input: {"url":"https://de.wikipedia.org/wiki/Dobble"}


## Plan: webbasiertes Dobble-Set-Werkzeug

### 1. Ziel und Grundprinzip

Das Werkzeug führt Nutzer durch drei Schritte: **Set konfigurieren**, **eigene Grafiken zuordnen** und **Karten exportieren**. Im Zentrum steht ein Generator, der sicherstellt, dass sich jedes Kartenpaar in genau einem Symbol überschneidet.

Dafür eignet sich die mathematische Konstruktion über projektive Ebenen:

- Bei einer Primzahlpotenz \(q\) enthält jede Karte \(q+1\) Symbole.
- Ein vollständiges Set umfasst \(q^2+q+1\) Karten und ebenso viele verschiedene Symbole.
- Es lassen sich weniger Karten aus diesem vollständigen Set auswählen, ohne die Überschneidungsregel zu verlieren.
- Beliebige Kombinationen aus Kartenanzahl und Symbolen pro Karte sind nicht automatisch möglich.

Das passt zu den im [Wikipedia-Artikel zu Dobble](https://de.wikipedia.org/wiki/Dobble) genannten Beispielen: Für \(q=7\) ergeben sich 57 mögliche Karten mit je 8 Symbolen; für \(q=5\) ergeben sich 31 mögliche Karten mit je 6 Symbolen. Kommerzielle Sets können daraus jeweils weniger Karten verwenden.

### 2. Konfiguration und Machbarkeit

Ein Konfigurationsschritt fragt ab:

- gewünschte Anzahl der Karten,
- Symbole pro Karte unter Berücksichtigung der Möglichkeiten,
- optional: Zielgröße der Druckkarten (Radius)

Das Werkzeug prüft die Eingaben und erklärt verständlich, welche Kombinationen mit der mathematischen Standardkonstruktion möglich sind. Es schlägt passende Werte vor, statt unpassende Kombinationen stillschweigend anzupassen. Zum Beispiel könnte es für „6 Symbole pro Karte“ die vollständige Größe 31 Karten anbieten oder ein kleineres Set daraus erzeugen.

**Späterer Ausbau:** Ein zusätzlicher Suchmodus könnte beliebige Kombinationen per Constraint-Solver untersuchen. Er müsste klar ausweisen, dass eine Lösung nicht garantiert ist und die Suche bei großen Anforderungen aufwendig werden kann.

### 3. Eigene Symbole und Gestaltung

Nutzer können eigene Bilder hochladen und jedem Symbol einen Namen zuweisen. Sinnvolle Funktionen:

- Vorschau, Ersetzen und Entfernen von Bildern,
- Unterstützung gängiger Bildformate,
- Prüfung auf fehlende oder doppelt zugewiesene Symbole,
- Warnung bei schlechter Auflösung oder ungeeignetem Seitenverhältnis,
- Vorschau der Karten mit anpassbarer Symbolgröße, Drehung und Anordnung.

Jedes Symbol wird intern durch eine eindeutige ID repräsentiert. Die mathematische Kartenstruktur bleibt dadurch unabhängig von den konkreten Bildern.

### 4. Generator und Qualitätssicherung

Der Generator erstellt zunächst die abstrakte Zuordnung von Karten zu Symbol-IDs. Anschließend wird automatisch geprüft:

1. Jede Karte enthält die konfigurierte Symbolanzahl.
2. Jedes Symbol ist eindeutig zugeordnet.
3. Jedes Kartenpaar hat **genau ein** gemeinsames Symbol.
4. Alle verwendeten Symbole besitzen ein Bild.

Bei einem Fehler zeigt das Werkzeug eine konkrete Diagnose, statt ein scheinbar fertiges Set auszugeben.

### 5. Vorschau und Export

Eine Druckvorschau zeigt das gesamte Set sowie einzelne Karten. Nutzer können Karten neu anordnen und das Layout prüfen. Als erste Exportformate bieten sich an:

- **PDF** zum Drucken, mit mehreren Karten pro Seite und Schnittmarken,
- **PNG** für einzelne Karten oder digitale Nutzung,
- optional ein Projektformat zum späteren Weiterbearbeiten.

Vor dem Export sollte eine abschließende Prüfung auf abgeschnittene Symbole, fehlende Bilder und Druckränder stattfinden.

### 6. Umsetzung in Etappen

**MVP**
- Projekt anlegen und Konfiguration validieren,
- Projektive-Ebenen-Generator für unterstützte Primzahlpotenzen,
- Upload und Zuordnung eigener Bilder,
- Karten- und Setvorschau,
- automatischer Regelcheck,
- PDF-Export.

**Ausbau**
- flexible Layout- und Designoptionen,
- Projekt speichern und später bearbeiten,
- PNG-Export,
- weitere mathematische Konstruktionen und optionaler Suchmodus für Sondergrößen.

### 7. Abnahmekriterien

Ein erstes Release ist fachlich gelungen, wenn Nutzer ein unterstütztes Set konfigurieren, eigene Grafiken zuordnen, eine Vorschau erhalten und ein druckbares PDF erzeugen können — und der automatische Test für **jedes Kartenpaar genau ein gemeinsames Symbol** bestätigt.
