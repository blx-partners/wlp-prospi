
# EXT:wlp_prospi (API preset)

TYPO3 Extbase/Fluid-Extension für die Anzeige von Kursen aus dem SPI-Orientierungsplan.

**API:** vorkonfiguriert auf  
`https://prospi-testing.intevation.de/orientierungsplan/trainercourses`

## Installation

```bash
composer req blx/wlp-prospi:@dev
# oder Extension-Ordner nach typo3conf/ext/wlp_prospi kopieren
```

Plugin **WLP ProSPI: Liste** auf einer Seite einfügen.  
TypoScript-Setup ist enthalten.

## Hinweise
- Cache-Lifetime im Testbetrieb: 15 Minuten
- Filter serverseitig (SEO-freundlich)
- Optional RouteEnhancer in eurer Site-Config für schöne Detail-URLs ergänzen.
- API-Basis-URL kann über die Extension-Konfiguration (`apiBaseUrl`) angepasst werden.
