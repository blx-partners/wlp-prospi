<?php

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
(static function () {
    ExtensionUtility::registerPlugin(
        'WlpProspi',
        'List',
        'WLP ProSPI: Liste'
    );
})();