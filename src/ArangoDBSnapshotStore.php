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

namespace Prooph\ArangoDB\SnapshotStore;

use ArangoDBClient\Batch;
use ArangoDBClient\Connection;
use ArangoDBClient\Statement;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventSourcing\Snapshot\Snapshot;
use Prooph\EventSourcing\Snapshot\SnapshotStore;

final class ArangoDBSnapshotStore implements SnapshotStore
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * Custom sourceType to snapshot mapping
     *
     * @var array
     */
    private $snapshotCollectionMap;

    /**
     * @var string
     */
    private $defaultSnapshotCollectionName;

    public function __construct(
        Connection $connection,
        array $snapshotCollectionMap = [],
        string $defaultSnapshotCollectionName = 'snapshots'
    ) {
        $this->connection = $connection;
        $this->snapshotCollectionMap = $snapshotCollectionMap;
        $this->defaultSnapshotCollectionName = $defaultSnapshotCollectionName;
    }

    public function get(AggregateType $aggregateType, string $aggregateId): ?Snapshot
    {
        $collectionName = $this->getCollectionName($aggregateType);

        $statement = new Statement(
            $this->connection, [
                'query' => 'FOR s IN @@collection FILTER s._key == @aggregate_id SORT s.last_version DESC RETURN s',
                'bindVars' => [
                    '@collection' => $collectionName,
                    'aggregate_id' => $aggregateId,
                ],
            ]
        );

        $cursor = $statement->execute();

        if (! $cursor->getCount()) {
            return null;
        }

        $result = $cursor->current();

        return new Snapshot(
            $aggregateType,
            $aggregateId,
            unserialize($result->aggregate_root),
            (int) $result->last_version,
            \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $result->created_at, new \DateTimeZone('UTC'))
        );
    }

    public function save(Snapshot $snapshot): void
    {
        $collectionName = $this->getCollectionName($snapshot->aggregateType());

        $batch = new Batch($this->connection);

        $batch->append(
            'DELETE',
            'DELETE /_api/document/' . $collectionName . '/' . $snapshot->aggregateId() . ' HTTP/1.1'
        );

        $data = [
            '_key' => $snapshot->aggregateId(),
            'aggregate_type' => $snapshot->aggregateType()->toString(),
            'last_version' => $snapshot->lastVersion(),
            'created_at' => $snapshot->createdAt()->format('Y-m-d\TH:i:s.u'),
            'aggregate_root' => serialize($snapshot->aggregateRoot()),
        ];

        $batch->append(
            'POST',
            'POST /_api/document/' . $collectionName . "?silent=true HTTP/1.1\n\n"
            . $this->connection->json_encode_wrapper($data)
        );

        $batch->process();
    }

    private function getCollectionName(AggregateType $aggregateType): string
    {
        $collectionName = $this->defaultSnapshotCollectionName;

        if (isset($this->snapshotCollectionMap[$aggregateType->toString()])) {
            $collectionName = $this->snapshotCollectionMap[$aggregateType->toString()];
        }

        return $collectionName;
    }
}
