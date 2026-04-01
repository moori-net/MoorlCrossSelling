<?php declare(strict_types=1);

namespace MoorlCrossSelling\Data;

use MoorlCrossSelling\MoorlCrossSelling;
use MoorlFoundation\Core\System\DataExtension;
use MoorlFoundation\Core\System\DataInterface;

class Data extends DataExtension implements DataInterface
{
    public function getTables(): ?array
    {
        return array_merge(
            $this->getShopwareTables(),
            $this->getPluginTables()
        );
    }

    public function getShopwareTables(): ?array
    {
        return MoorlCrossSelling::SHOPWARE_TABLES;
    }

    public function getPluginTables(): ?array
    {
        return MoorlCrossSelling::PLUGIN_TABLES;
    }

    public function getPluginName(): string
    {
        return MoorlCrossSelling::NAME;
    }

    public function getCreatedAt(): string
    {
        return MoorlCrossSelling::DATA_CREATED_AT;
    }

    public function getLockedEntityRecords(): array
    {
        return MoorlCrossSelling::LOCKED_ENTITY_RECORDS;
    }

    public function getName(): string
    {
        return 'data';
    }

    public function getType(): string
    {
        return 'data';
    }

    public function getPath(): string
    {
        return __DIR__;
    }

    public function getInstallConfig(): array {
        return [
            "MoorlCrossSelling.config.cmsPageId" => "{ID:CMS_PAGE_WIDGET}"
        ];
    }
}
