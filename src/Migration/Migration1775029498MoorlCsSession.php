<?php declare(strict_types=1);

namespace MoorlCrossSelling\Migration;

use Doctrine\DBAL\Connection;
use MoorlFoundation\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;

class Migration1775029498MoorlCsSession extends MigrationStep
{
    public const OPERATION_HASH = 'fe3f3f520cabe7e30a6b7c9820143c5b';
    public const PLUGIN_VERSION = '0.7.0';

    public function getCreationTimestamp(): int
    {
        return 1775029498;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::tableExists($connection, 'moorl_cs_session', '')) {
            $sql = "CREATE TABLE moorl_cs_session (id BINARY(16) NOT NULL, customer_id BINARY(16) DEFAULT NULL, sales_channel_id BINARY(16) NOT NULL, processed TINYINT DEFAULT 0, token VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_session');
        }
        if (!EntityDefinitionQueryHelper::constraintExists($connection, 'moorl_cs_session', 'fk.moorl_cs_session.sales_channel_id')) {
            $sql = "ALTER TABLE moorl_cs_session ADD CONSTRAINT `fk.moorl_cs_session.sales_channel_id` FOREIGN KEY (sales_channel_id) REFERENCES sales_channel (id) ON UPDATE CASCADE ON DELETE CASCADE;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_session');
        }
        if (!EntityDefinitionQueryHelper::constraintExists($connection, 'moorl_cs_session', 'fk.moorl_cs_session.customer_id')) {
            $sql = "ALTER TABLE moorl_cs_session ADD CONSTRAINT `fk.moorl_cs_session.customer_id` FOREIGN KEY (customer_id) REFERENCES customer (id) ON UPDATE CASCADE ON DELETE SET NULL;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_session');
        }
    }
}
