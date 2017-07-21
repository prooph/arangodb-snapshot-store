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

namespace ProophTest\ArangoDB\SnapshotStore;

use ArangoDBClient\Connection;
use ArangoDBClient\ServerException;
use ArangoDBClient\Urls;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Prooph\ArangoDB\SnapshotStore\ArangoDBSnapshotStore;
use Prooph\ArangoDB\SnapshotStore\Exception\TruncateCollectionFailed;
use Prooph\SnapshotStore\Snapshot;

class ArangoDBSnapshotStoreTest extends TestCase
{
    /**
     * @var ArangoDBSnapshotStore
     */
    private $snapshotStore;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @test
     */
    public function it_saves_and_reads()
    {
        $aggregateRoot = ['name' => 'Sascha'];
        $aggregateType = 'user';

        $date = date('Y-m-d\TH:i:s.u');
        $now = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $date, new DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);
        $this->snapshotStore->save($snapshot);

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 2, $now);
        $this->snapshotStore->save($snapshot);

        $this->assertNull($this->snapshotStore->get($aggregateType, 'invalid'));

        $readSnapshot = $this->snapshotStore->get($aggregateType, 'id');
        $this->assertEquals($snapshot, $readSnapshot);
    }

    /**
     * @test
     */
    public function it_saves_multiple_snapshots()
    {
        $aggregateRoot = ['name' => 'Sascha'];
        $aggregateType = 'user';

        $date = date('Y-m-d\TH:i:s.u');
        $now = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $date, new DateTimeZone('UTC'));

        $snapshot1 = new Snapshot($aggregateType, 'id1', $aggregateRoot, 1, $now);
        $snapshot2 = new Snapshot($aggregateType, 'id2', $aggregateRoot, 2, $now);

        $this->snapshotStore->save($snapshot1, $snapshot2);

        $readSnapshot = $this->snapshotStore->get($aggregateType, 'id1');
        $this->assertEquals($snapshot1, $readSnapshot);

        $readSnapshot = $this->snapshotStore->get($aggregateType, 'id2');
        $this->assertEquals($snapshot2, $readSnapshot);
    }

    /**
     * @test
     */
    public function it_uses_custom_snapshot_table_map()
    {
        $aggregateType = \stdClass::class;
        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $date = date('Y-m-d\TH:i:s.u');
        $now = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $date, new DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->snapshotStore->save($snapshot);

        $response = $this->connection->get(Urls::URL_COLLECTION . '/bar/count')->getJson();
        $this->assertSame(1, $response['count'] ?? 0);

        $readSnapshot = $this->snapshotStore->get($aggregateType, 'id');
        $this->assertEquals($snapshot, $readSnapshot);
    }

    /**
     * @test
     */
    public function it_truncate_snapshot()
    {
        $aggregateRoot = ['name' => 'Sascha'];
        $aggregateType = 'user';

        $date = date('Y-m-d\TH:i:s.u');
        $now = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $date, new DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);
        $this->snapshotStore->save($snapshot);

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 2, $now);
        $this->snapshotStore->save($snapshot);

        $readSnapshot = $this->snapshotStore->get($aggregateType, 'id');
        $this->assertEquals($snapshot, $readSnapshot);

        $this->snapshotStore->removeAll($aggregateType);

        $this->assertNull($this->snapshotStore->get($aggregateType, 'id'));
    }

    /**
     * @test
     */
    public function it_throws_truncate_collection_failed_exception()
    {
        $aggregateType = 'user';

        $this->connection->delete(Urls::URL_COLLECTION . '/snapshots');
        $this->expectException(TruncateCollectionFailed::class);
        $this->snapshotStore->removeAll($aggregateType);
    }

    protected function setUp(): void
    {
        $this->connection = TestUtil::getClient();

        $this->connection->post(Urls::URL_COLLECTION, $this->connection->json_encode_wrapper(['name' => 'snapshots']));
        $this->connection->post(Urls::URL_COLLECTION, $this->connection->json_encode_wrapper(['name' => 'bar']));

        $this->snapshotStore = new ArangoDBSnapshotStore(
            $this->connection,
            [\stdClass::class => 'bar'],
            'snapshots'
        );
    }

    protected function tearDown(): void
    {
        try {
            $this->connection->delete(Urls::URL_COLLECTION . '/snapshots');
        } catch (ServerException $ex) {
            // this is needed for test it_throws_truncate_collection_failed_exception
        }
        $this->connection->delete(Urls::URL_COLLECTION . '/bar');
        unset($this->connection);
    }
}
