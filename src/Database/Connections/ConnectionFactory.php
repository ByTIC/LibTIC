<?php

namespace Nip\Database\Connections;

use InvalidArgumentException;
use Nip\Container\Container;

use Nip_Helper_Arrays as Arr;

/**
 * Class ConnectionFactory
 * @package Nip\Database\Connectors
 */
class ConnectionFactory
{
    /**
     * The IoC container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * Create a new connection factory instance.
     *
     * @param  Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Establish a PDO connection based on the configuration.
     *
     * @param  array $config
     * @param  string $name
     * @return Connection
     */
    public function make(array $config, $name = null)
    {
        $config = $this->parseConfig($config, $name);

//        if (isset($config['read'])) {
//            return $this->createReadWriteConnection($config);
//        }
        return $this->createSingleConnection($config);
    }

    /**
     * Parse and prepare the database configuration.
     *
     * @param  array $config
     * @param  string $name
     * @return array
     */
    protected function parseConfig(array $config, $name)
    {
        return $config;
//        return Arr::add(Arr::add($config, 'prefix', ''), 'name', $name);
    }

    /**
     * Create a single database connection instance.
     *
     * @param  array $config
     * @return Connection
     */
    protected function createSingleConnection(array $config)
    {
        $pdo = false;
        $connection = $this->createConnection($config['driver'], $pdo, $config['database'], $config['prefix'], $config);
        $connection->connect($config['host'], $config['user'], $config['password'], $config['database']);
        return $connection;
    }

    /**
     * Create a new connection instance.
     *
     * @param  string $driver
     * @param  \PDO|\Closure $connection
     * @param  string $database
     * @param  string $prefix
     * @param  array $config
     * @return Connection
     *
     * @throws \InvalidArgumentException
     */
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
//        if ($resolver = Connection::getResolver($driver)) {
//            return $resolver($connection, $database, $prefix, $config);
//        }
        switch ($driver) {
            case 'mysql':
                return new MySqlConnection($connection, $database, $prefix, $config);
        }

        throw new InvalidArgumentException("Unsupported driver [$driver]");
    }
}