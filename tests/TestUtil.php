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
use ArangoDBClient\ConnectionOptions;
use ArangoDBClient\UpdatePolicy;

abstract class TestUtil
{
    public static function getClient(): Connection
    {
        return new Connection(self::getConnectionParams());
    }

    public static function getDatabaseName(): string
    {
        if (! self::hasRequiredConnectionParams()) {
            throw new \RuntimeException('No connection params given');
        }

        return $GLOBALS['arangodb_dbname'];
    }

    public static function getConnectionParams(): array
    {
        if (! self::hasRequiredConnectionParams()) {
            throw new \RuntimeException('No connection params given');
        }

        return self::getSpecifiedConnectionParams();
    }

    private static function hasRequiredConnectionParams(): bool
    {
        return isset(
            $GLOBALS['arangodb_username'],
            $GLOBALS['arangodb_password'],
            $GLOBALS['arangodb_host'],
            $GLOBALS['arangodb_dbname']
        );
    }

    private static function getSpecifiedConnectionParams(): array
    {
        return [
            ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            ConnectionOptions::OPTION_CONNECTION => 'Close',
            ConnectionOptions::OPTION_TIMEOUT => 3,
            ConnectionOptions::OPTION_RECONNECT => false,
            ConnectionOptions::OPTION_CREATE => false,
            ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
            ConnectionOptions::OPTION_AUTH_USER => $GLOBALS['arangodb_username'],
            ConnectionOptions::OPTION_AUTH_PASSWD => $GLOBALS['arangodb_password'],
            ConnectionOptions::OPTION_ENDPOINT => $GLOBALS['arangodb_host'],
            ConnectionOptions::OPTION_DATABASE => $GLOBALS['arangodb_dbname'],
        ];
    }
}
