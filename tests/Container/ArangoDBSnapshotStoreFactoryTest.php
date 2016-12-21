<?php
/**
 * This file is part of the prooph/arangodb-snapshot-store.
 * (c) 2016-2016 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2016 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\ArangoDB\SnapshotStore\Container;

use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Prooph\ArangoDB\SnapshotStore\ArangoDBSnapshotStore;
use Prooph\ArangoDB\SnapshotStore\Container\ArangoDBSnapshotStoreFactory;
use ProophTest\ArangoDB\SnapshotStore\TestUtil;

class ArangoDBSnapshotStoreFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_adapter_via_connection_service(): void
    {
        $config['prooph']['arangodb_snapshot_store']['default'] = [
            'arangodb_client_service' => 'my_connection',
        ];

        $client = TestUtil::getClient();

        $container = $this->prophesize(ContainerInterface::class);

        $container->get('my_connection')->willReturn($client)->shouldBeCalled();
        $container->get('config')->willReturn($config)->shouldBeCalled();

        $factory = new ArangoDBSnapshotStoreFactory();
        $snapshotStore = $factory($container->reveal());

        $this->assertInstanceOf(ArangoDBSnapshotStore::class, $snapshotStore);
    }

    /**
     * @test
     */
    public function it_creates_adapter_via_connection_options(): void
    {
        $config['prooph']['arangodb_snapshot_store']['custom'] = [
            'connection_options' => TestUtil::getConnectionParams(),
        ];

        $container = $this->prophesize(ContainerInterface::class);

        $container->get('config')->willReturn($config)->shouldBeCalled();

        $snapshotStoreName = 'custom';
        $snapshotStore = ArangoDBSnapshotStoreFactory::$snapshotStoreName($container->reveal());

        $this->assertInstanceOf(ArangoDBSnapshotStore::class, $snapshotStore);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_invalid_container_given(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $eventStoreName = 'custom';
        ArangoDBSnapshotStoreFactory::$eventStoreName('invalid container');
    }
}
