<?php declare(strict_types=1);

namespace MoorlCrossSelling\Migration;

use Doctrine\DBAL\Connection;
use MoorlFoundation\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;

class Migration1774953465MoorlCsSessionProduct extends MigrationStep
{
    public const OPERATION_HASH = 'c58a038b1aa50b98863f486147a578c3';
    public const PLUGIN_VERSION = '1.7.2';

    public function getCreationTimestamp(): int
    {
        return 1774953465;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE moorl_cs_session_product ADD last_visit INT DEFAULT NULL;
SQL;

        // Try to execute all queries at once
        try {
            $connection->executeStatement($sql);
            return;
        } catch (\Exception) {
            if (!class_exists(EntityDefinitionQueryHelper::class)) {
                throw new MissingRequirementException('moorl/foundation', '*');
            }
        }

        // Try to execute all queries step by step
        if (!EntityDefinitionQueryHelper::columnExists($connection, 'moorl_cs_session_product', 'last_visit')) {
            $sql = "ALTER TABLE moorl_cs_session_product ADD last_visit INT DEFAULT NULL;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_session_product');
        }
    }
}
