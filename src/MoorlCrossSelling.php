<?php declare(strict_types=1);

namespace MoorlCrossSelling;

use MoorlFoundation\Core\PluginLifecycleHelper;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class MoorlCrossSelling extends Plugin
{
    final public const NAME = 'MoorlCrossSelling';
    final public const DATA_CREATED_AT = '2026-03-29 00:00:00.000';
    final public const PLUGIN_TABLES = [
        'moorl_cs_relation',
        'moorl_cs_session',
        'moorl_cs_session_product',
    ];
    final public const SHOPWARE_TABLES = [];

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        PluginLifecycleHelper::update(self::class, $this->container);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        PluginLifecycleHelper::update(self::class, $this->container);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        if ($uninstallContext->keepUserData()) {
            return;
        }

        PluginLifecycleHelper::uninstall(self::class, $this->container);
    }
}
