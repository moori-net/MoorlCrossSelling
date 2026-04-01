<?php declare(strict_types=1);

namespace MoorlCrossSelling\Migration;

use Doctrine\DBAL\Connection;
use MoorlFoundation\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1775029499MoorlCsSessionProduct extends MigrationStep
{
    public const OPERATION_HASH = 'f7f0fc247822588f5deae7fa292b78b3';
    public const PLUGIN_VERSION = '0.7.0';

    public function getCreationTimestamp(): int
    {
        return 1775029499;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::tableExists($connection, 'moorl_cs_session_product', '')) {
            $sql = "CREATE TABLE moorl_cs_session_product (id BINARY(16) NOT NULL, moorl_cs_session_id BINARY(16) NOT NULL, product_id BINARY(16) NOT NULL, product_version_id BINARY(16) DEFAULT 0x0FA91CE3E96A4BC2BE4BD9CE752C3425 NOT NULL, count INT DEFAULT NULL, last_visit INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id, product_version_id)) DEFAULT CHARACTER SET utf8mb4;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_session_product');
        }
        if (!EntityDefinitionQueryHelper::constraintExists($connection, 'moorl_cs_session_product', 'fk.moorl_cs_session_product.product_id')) {
            $sql = "ALTER TABLE moorl_cs_session_product ADD CONSTRAINT `fk.moorl_cs_session_product.product_id` FOREIGN KEY (product_id, product_version_id) REFERENCES product (id, version_id) ON UPDATE CASCADE ON DELETE CASCADE;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_session_product');
        }
        if (!EntityDefinitionQueryHelper::constraintExists($connection, 'moorl_cs_session_product', 'fk.moorl_cs_session_product.moorl_cs_session_id')) {
            $sql = "ALTER TABLE moorl_cs_session_product ADD CONSTRAINT `fk.moorl_cs_session_product.moorl_cs_session_id` FOREIGN KEY (moorl_cs_session_id) REFERENCES moorl_cs_session (id) ON UPDATE CASCADE ON DELETE CASCADE;";
            EntityDefinitionQueryHelper::tryExecuteStatement($connection, $sql, 'moorl_cs_session_product');
        }
    }
}
