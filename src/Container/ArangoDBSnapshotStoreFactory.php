<?php
/**
 * This file is part of the prooph/arangodb-snapshot-store.
 * (c) 2016-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\ArangoDB\SnapshotStore\Container;

use ArangoDBClient\Connection;
use ArangoDBClient\ConnectionOptions;
use ArangoDBClient\UpdatePolicy;
use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfigId;
use Interop\Container\ContainerInterface;
use Prooph\ArangoDB\SnapshotStore\ArangoDBSnapshotStore;

class ArangoDBSnapshotStoreFactory implements ProvidesDefaultOptions, RequiresConfigId
{
    use ConfigurationTrait;

    /**
     * @var string
     */
    private $configId;

    /**
     * Creates a new instance from a specified config, specifically meant to be used as static factory.
     *
     * In case you want to use another config key than provided by the factories, you can add the following factory to
     * your config:
     *
     * <code>
     * <?php
     * return [
     *     ArangoDBSnapshotStore::class => [ArangoDBSnapshotStoreFactory::class, 'service_name'],
     * ];
     * </code>
     *
     * @throws \InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments): ArangoDBSnapshotStore
    {
        if (! isset($arguments[0]) || ! $arguments[0] instanceof ContainerInterface) {
            throw new \InvalidArgumentException(
                sprintf('The first argument must be of type %s', ContainerInterface::class)
            );
        }

        return (new static($name))->__invoke($arguments[0]);
    }

    public function __invoke(ContainerInterface $container): ArangoDBSnapshotStore
    {
        $config = $container->get('config');
        $config = $this->options($config, $this->configId);

        if (isset($config['arangodb_client_service'])) {
            $connection = $container->get($config['arangodb_client_service']);
        } else {
            $connection = new Connection($config['connection_options']);
        }

        return new ArangoDBSnapshotStore(
            $connection,
            $config['snapshot_collection_map'],
            $config['default_snapshot_collection_name']
        );
    }

    public function __construct(string $configId = 'default')
    {
        $this->configId = $configId;
    }

    public function dimensions(): iterable
    {
        return ['prooph', 'arangodb_snapshot_store'];
    }

    public function defaultOptions(): iterable
    {
        return [
            'connection_options' => [
                ConnectionOptions::OPTION_DATABASE => 'snapshot_store',
                ConnectionOptions::OPTION_ENDPOINT => 'tcp://arangodb:8529',
                ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
                ConnectionOptions::OPTION_AUTH_USER => '',
                ConnectionOptions::OPTION_AUTH_PASSWD => '',
                ConnectionOptions::OPTION_CONNECTION => 'Close',
                ConnectionOptions::OPTION_TIMEOUT => 3,
                ConnectionOptions::OPTION_RECONNECT => true,
                ConnectionOptions::OPTION_CREATE => true,
                ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
            ],
            'snapshot_collection_map' => [],
            'default_snapshot_collection_name' => 'snapshots',
        ];
    }
}
