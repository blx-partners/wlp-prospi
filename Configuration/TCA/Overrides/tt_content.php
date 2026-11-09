<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    $signature = ExtensionUtility::registerPlugin(
        'WlpProspi',
        'List',
        'LLL:EXT:wlp_prospi/Resources/Private/Language/locallang_db.xlf:plugin.list.title',
        'wlpprospi-plugin-list',
        'plugins',
        'LLL:EXT:wlp_prospi/Resources/Private/Language/locallang_db.xlf:plugin.list.description'
    );

    $GLOBALS['TCA']['tt_content']['types'][$signature]['showitem'] = '
            --palette--;;headers,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
            categories,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
            rowDescription,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
    ';
})();
