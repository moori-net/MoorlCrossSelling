<?php declare(strict_types=1);

namespace MoorlCrossSelling\Migration;

use Doctrine\DBAL\Connection;
use MoorlFoundation\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1775068987MoorlCsRelation extends MigrationStep
{
    public const OPERATION_HASH = 'f0bc95282e28e0e5236afebb7786338f';
    public const PLUGIN_VERSION = '0.7.0';

    public function getCreationTimestamp(): int
    {
        return 1775068987;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::tableExists($connection, 'moorl_cs_relation', '')) {
            $sql = "CREATE TABLE moorl_cs_relation (id BINARY(16) NOT NULL, product_a_id BINARY(16) NOT NULL, product_b_id BINARY(16) NOT NULL, sales_channel_id BINARY(16) NOT NULL, product_a_version_id BINARY(16) DEFAULT 0x0FA91CE3E96A4BC2BE4BD9CE752C3425 NOT NULL, product_b_version_id BINARY(16) DEFAULT 0x0FA91CE3E96A4BC2BE4BD9CE752C3425 NOT NULL, score INT DEFAULT NULL, type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_moorl_cs_relation_id (product_a_id, product_a_version_id, product_b_id, product_b_version_id, sales_channel_id, type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_relation');
        }
        if (!EntityDefinitionQueryHelper::constraintExists($connection, 'moorl_cs_relation', 'fk.moorl_cs_relation.product_a_id')) {
            $sql = "ALTER TABLE moorl_cs_relation ADD CONSTRAINT `fk.moorl_cs_relation.product_a_id` FOREIGN KEY (product_a_id, product_a_version_id) REFERENCES product (id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_relation');
        }
        if (!EntityDefinitionQueryHelper::constraintExists($connection, 'moorl_cs_relation', 'fk.moorl_cs_relation.product_b_id')) {
            $sql = "ALTER TABLE moorl_cs_relation ADD CONSTRAINT `fk.moorl_cs_relation.product_b_id` FOREIGN KEY (product_b_id, product_b_version_id) REFERENCES product (id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_relation');
        }
        if (!EntityDefinitionQueryHelper::constraintExists($connection, 'moorl_cs_relation', 'fk.moorl_cs_relation.sales_channel_id')) {
            $sql = "ALTER TABLE moorl_cs_relation ADD CONSTRAINT `fk.moorl_cs_relation.sales_channel_id` FOREIGN KEY (sales_channel_id) REFERENCES sales_channel (id) ON UPDATE CASCADE ON DELETE CASCADE;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_relation');
        }
    }
}
