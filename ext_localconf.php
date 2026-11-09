<?php

declare(strict_types=1);

use Blx\WlpProspi\Controller\ProspiController;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    // Die Filter kommen per GET ohne cHash; die API-Antwort wird stattdessen
    // im Cache "wlpprospi_api" vorgehalten (siehe ProspiApiClient).
    ExtensionUtility::configurePlugin(
        'WlpProspi',
        'List',
        [ProspiController::class => 'list,detail'],
        [ProspiController::class => 'list,detail'],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['wlpprospi_api'] ??= [
        'frontend' => VariableFrontend::class,
        'backend' => FileBackend::class,
        'groups' => ['pages'],
    ];

    ExtensionManagementUtility::addTypoScriptSetup(
        "@import 'EXT:wlp_prospi/Configuration/TypoScript/setup.typoscript'"
    );
})();
