<?php

/**
 * This file is part of prooph/arangodb-snapshot-store.
 * (c) 2016-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\SnapshotStore\ArangoDb;

use ArangoDBClient\Batch;
use ArangoDBClient\Connection;
use ArangoDBClient\ServerException;
use ArangoDBClient\Statement;
use ArangoDBClient\Urls;
use Prooph\SnapshotStore\ArangoDb\Exception\TruncateCollectionFailed;
use Prooph\SnapshotStore\Snapshot;
use Prooph\SnapshotStore\SnapshotStore;

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

    public function get(string $aggregateType, string $aggregateId): ?Snapshot
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
            \unserialize($result->aggregate_root),
            (int) $result->last_version,
            \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $result->created_at, new \DateTimeZone('UTC'))
        );
    }

    public function save(Snapshot ...$snapshots): void
    {
        $batch = new Batch($this->connection);

        foreach ($snapshots as $snapshot) {
            $aggregateId = $snapshot->aggregateId();
            $aggregateType = $snapshot->aggregateType();

            $collectionName = $this->getCollectionName($aggregateType);

            $batch->append(
                'DELETE',
                'DELETE /_api/document/' . $collectionName . '/' . $aggregateId . ' HTTP/1.1'
            );

            $data = [
                '_key' => $aggregateId,
                'aggregate_type' => $aggregateType,
                'last_version' => $snapshot->lastVersion(),
                'created_at' => $snapshot->createdAt()->format('Y-m-d\TH:i:s.u'),
                'aggregate_root' => \serialize($snapshot->aggregateRoot()),
            ];

            $batch->append(
                'POST',
                'POST /_api/document/' . $collectionName . "?silent=true HTTP/1.1\n\n"
                . $this->connection->json_encode_wrapper($data)
            );
        }

        $batch->process();
    }

    public function removeAll(string $aggregateType): void
    {
        try {
            $this->connection->put(
                Urls::URL_COLLECTION . '/' . $this->getCollectionName($aggregateType) . '/truncate',
                ''
            );
        } catch (ServerException $ex) {
            throw TruncateCollectionFailed::with($this->getCollectionName($aggregateType), $ex);
        }
    }

    private function getCollectionName(string $aggregateType): string
    {
        $collectionName = $this->defaultSnapshotCollectionName;

        if (isset($this->snapshotCollectionMap[$aggregateType])) {
            $collectionName = $this->snapshotCollectionMap[$aggregateType];
        }

        return $collectionName;
    }
}
