<?php
defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Blx\WlpProspi\Controller\ProspiController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function () {

    ExtensionUtility::configurePlugin(
        'WlpProspi',
        'List',
        [ProspiController::class => 'list,detail'],
        [ProspiController::class => 'list,detail']
    );

    ExtensionManagementUtility::addTypoScriptSetup(
        "@import 'EXT:wlp-prospi/Configuration/TypoScript/setup.typoscript'"
    );
})();
