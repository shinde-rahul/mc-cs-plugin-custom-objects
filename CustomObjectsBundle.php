<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\CoreBundle\Factory\MauticFactory;
use MauticPlugin\CustomObjectsBundle\Migration\Engine;

class CustomObjectsBundle extends PluginBundleBase
{
    /**
     * @param Plugin           $plugin
     * @param MauticFactory    $factory
     * @param array|null       $metadata
     * @param Schema|bool|null $installedSchema
     *
     * @throws \Exception
     */
    public static function onPluginInstall(Plugin $plugin, MauticFactory $factory, $metadata = null, $installedSchema = null): void
    {
        if (true === $installedSchema) {
            // Schema exists
            return;
        }

        self::runMigrations($plugin, $factory);
    }

    /**
     * @param Plugin        $plugin
     * @param MauticFactory $factory
     * @param array|null    $metadata
     * @param Schema|null   $installedSchema
     *
     * @throws \Exception
     */
    public static function onPluginUpdate(Plugin $plugin, MauticFactory $factory, $metadata = null, Schema $installedSchema = null): void
    {
        self::runMigrations($plugin, $factory);
    }

    /**
     * @param Plugin        $plugin
     * @param MauticFactory $factory
     */
    private static function runMigrations(Plugin $plugin, MauticFactory $factory): void
    {
        $migrationEngine = new Engine(
            $factory->getEntityManager(),
            $factory->getParameter('mautic.db_table_prefix'),
            dirname(__FILE__)
        );

        $migrationEngine->up();
    }
}
