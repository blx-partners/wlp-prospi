<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Updates;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

/**
 * Stellt vorhandene Plugin-Inhaltselemente von list_type auf einen eigenen CType um.
 */
#[UpgradeWizard('wlpProspi_pluginListTypeToCType')]
final class PluginListTypeToCTypeUpdate extends AbstractListTypeToCTypeUpdate
{
    public function getTitle(): string
    {
        return 'WLP ProSPI: Plugin auf eigenen Inhaltstyp umstellen';
    }

    public function getDescription(): string
    {
        return 'Migriert Inhaltselemente mit list_type "wlpprospi_list" auf CType "wlpprospi_list" '
            . 'und passt die Rechte der Backend-Gruppen an.';
    }

    protected function getListTypeToCTypeMapping(): array
    {
        return ['wlpprospi_list' => 'wlpprospi_list'];
    }
}
