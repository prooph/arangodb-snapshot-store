<?php

/**
 * This file is part of the prooph/arangodb-snapshot-store.
 * (c) 2016-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\SnapshotStore\ArangoDb\Exception;

use ArangoDBClient\ServerException;

class TruncateCollectionFailed extends RuntimeException
{
    /**
     * Server exception
     *
     * @var ServerException
     */
    private $serverException;

    public static function with(string $collectionName, ServerException $ex)
    {
        $self = new self(
            \sprintf(
                'Could not truncate "%s". Got HTTP status %s - %s',
                $collectionName,
                $ex->getServerCode(),
                $ex->getServerMessage()
            )
        );

        return $self;
    }

    public function getServerException(): ServerException
    {
        return $this->serverException;
    }
}
