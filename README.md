# EXT:wlp_prospi

TYPO3-Extension (Extbase/Fluid) für die Kursliste des SPI-Orientierungsplans:
Liste mit serverseitigen Filtern, Detailansicht und Link zur Anmeldung bei ProSPI.

- TYPO3 13.4, PHP 8.2–8.4
- Composer-Paket `blx/wlp-prospi`, Extension-Key `wlp_prospi`

## Installation

```bash
composer req blx/wlp-prospi:@dev
vendor/bin/typo3 extension:setup
```

Inhaltselement **WLP ProSPI: Kursliste** (Gruppe „Plugins“) auf einer Seite einfügen.

## Konfiguration

Unter *Einstellungen → Extension-Konfiguration → wlp_prospi*:

| Einstellung     | Standard                                                            | Bedeutung                                   |
|-----------------|---------------------------------------------------------------------|---------------------------------------------|
| `apiBaseUrl`    | `https://www.spi-monitoring.de/orientierungsplan/trainercourses`    | Endpunkt der Kursliste; Anmeldung unter `…/book?code=<uuid>` |
| `cacheLifetime` | `900`                                                               | Cache der API-Antwort in Sekunden (0 = unbegrenzt) |
| `timeout`       | `10`                                                                | Timeout der API-Anfrage in Sekunden         |

Die API-Antwort liegt im Cache `wlpprospi_api` (Gruppe `pages`) und wird mit
„Frontend-Caches leeren“ verworfen. Fehler der API werden geloggt; die Liste bleibt dann leer.

Das Plugin selbst ist nicht gecacht (`USER_INT`), weil die Filter per GET-Formular
ohne cHash übertragen werden.

## Aufbau

```
Classes/
  Configuration/ExtensionSettings.php   Extension-Konfiguration, typisiert
  Controller/ProspiController.php       list, detail
  Domain/Filter/CourseFilter.php        Filterwerte bereinigen und auf Kurse anwenden
  Service/ProspiApiClient.php           HTTP (RequestFactory) + Cache
  Service/CourseNormalizer.php          abgeleitete Felder (PLZ, Formate, freie Plätze, …)
  Service/CourseRepository.php          normalisierte Kurse
  Service/FilterOptionProvider.php      Auswahllisten für das Formular
  Updates/PluginListTypeToCTypeUpdate   Upgrade-Wizard list_type → CType
Resources/Private/
  Language/                             locallang(.xlf) für Frontend, locallang_db für Backend (en + de)
  Layouts/, Templates/Prospi/, Partials/{Course,Filter}/
Resources/Public/
  Css/prospi.css                        BEM-Block .prospi, Gestaltung über --prospi-* Custom Properties
  JavaScript/prospi-filter.js           Filter-Chips zurücksetzen, Scrollen zum Formular
```

Templates enthalten kein Inline-CSS/-JS; CSS und JS werden über `f:asset.css` /
`f:asset.script` nur auf Seiten mit dem Plugin eingebunden. Alle Texte stehen in XLF.

## Anpassen

- **Templates:** eigene Pfade mit höherem Index ergänzen, z. B.
  `plugin.tx_wlpprospi.view.partialRootPaths.10 = EXT:mein_sitepackage/Resources/Private/Extensions/WlpProspi/Partials/`.
  Nur die geänderten Dateien kopieren, nicht das ganze Template.
- **Farben/Abstände:** die `--prospi-*` Custom Properties auf `.prospi` im Sitepackage überschreiben.
- **Texte:** per `$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']` überschreiben.

## Upgrade von 0.1.x

1. Extension-Key ist jetzt `wlp_prospi` (vorher `wlp-prospi`):
   in `config/system/settings.php` den Block `EXTENSIONS/wlp-prospi` in `wlp_prospi` umbenennen
   und die Composer-Metadaten aktualisieren (`composer update blx/wlp-prospi`).
2. Upgrade-Wizard ausführen: `vendor/bin/typo3 upgrade:run wlpProspi_pluginListTypeToCType`
   (stellt das Plugin von `list_type` auf CType `wlpprospi_list` um).
3. Kopien der alten Templates (z. B. in `fileadmin`) entfernen; Variablen und Klassen haben sich geändert.
4. Das globale `page.includeCSS.wlpProspi` und `config.concatenateCss/compressCss` setzt die Extension nicht mehr.
