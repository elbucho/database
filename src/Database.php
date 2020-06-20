<?php

namespace Elbucho\Database;
use Elbucho\Config\Config;
use PDO;
use PDOException;
use PDOStatement;
use Closure;

/**
 * Class Database
 *
 * Loads the config file information about the database setup, and creates PDO drivers
 *
 * @package Driver
 */
class Database
{
    /**
     * PDO drivers
     *
     * @access  private
     * @var     PDO[]
     */
    private $connections = array();

    /**
     * Default handle to use
     *
     * @access  private
     * @var     string
     */
    private $defaultHandle = 'default';

    /**
     * Mock driver to use instead of a new PDO instance
     *
     * @access  private
     * @var     PDO
     */
    private $mockDriver;

    /**
     * Class constructor
     *
     * @access  public
     * @param   Config  $config
     * @return  Database
     * @throws  InvalidConfigException
     */
    public function __construct(Config $config)
    {
        if (isset($config->{'default_handle'}) and is_string($config->{'default_handle'})) {
            $this->defaultHandle = $config->{'default_handle'};
        }

        $this->connections = $this->loadFromConfig($config);

        return $this;
    }

    /**
     * Create PDO connection(s) from supplied config file
     *
     * @access  private
     * @param   Config  $config
     * @return  Closure[]
     * @throws  InvalidConfigException
     */
    private function loadFromConfig(Config $config): array
    {
        // Determine if multiple DSNs exist in Config
        if (isset($config->{'dsns'}) and
            is_object($config->{'dsns'}) and
            $config->{'dsns'} instanceof Config and
            $config->{'dsns'}->count() > 0)
        {
            $dsns = array();

            foreach ($config->{'dsns'} as $key => $value) {
                $dsns[$key] = $this->createClosureFromConfig($value);
            }

            return $dsns;
        }

        return array('default' => $this->createClosureFromConfig($config));
    }

    /**
     * Create a closure for a single connection from the Database config
     *
     * @access  private
     * @param   Config  $config
     * @return  Closure
     * @throws  InvalidConfigException
     */
    private function createClosureFromConfig(Config $config): Closure
    {
        // Determine if the proper keys all exist
        foreach (array('host','dbname','user','pass') as $required) {
            if ( ! isset($config->{$required})) {
                throw new InvalidConfigException(sprintf(
                    'Required key %s not found in database config',
                    $required
                ));
            }
        }

        $port = (isset($config->{'port'})) ? ';port=' . $config->{'port'} : '';
        $dsn = sprintf(
            'mysql:hostname=%s;dbname=%s%s;charset=utf8',
            $config->{'host'},
            $config->{'dbname'},
            $port
        );

        return function() use ($dsn, $config) {
            if (isset($this->mockDriver)) {
                return $this->mockDriver;
            }

            return new PDO($dsn, $config->{'user'}, $config->{'pass'});
        };
    }

    /**
     * Verify that the Database handle exists, and is of the PDO class
     *
     * @access  private
     * @param   string  $handle
     * @return  void
     * @throws  PDOException
     */
    private function testHandle($handle = null)
    {
        if (is_null($handle)) {
            $handle = $this->defaultHandle;
        }

        if ( ! array_key_exists($handle, $this->connections) or
            ! is_object($this->connections[$handle]) or
            ! $this->connections[$handle] instanceof Closure)
        {
            throw new PDOException(sprintf(
                'Invalid handle: %s',
                $handle
            ));
        }
    }

    /**
     * Add a new connection to the Database class
     *
     * @access  public
     * @param   string  $handle
     * @param   Config  $config
     * @return  Database
     * @throws  InvalidConfigException
     */
    public function addConnection(string $handle, Config $config)
    {
        if (array_key_exists($handle, $this->connections)) {
            throw new InvalidConfigException(sprintf(
                'Handle %s already exists',
                $handle
            ));
        }

        $this->connections[$handle] = $this->createClosureFromConfig($config);

        return $this;
    }

    /**
     * Query the database, return results as array
     *
     * @access  public
     * @param   string  $statement
     * @param   array   $params
     * @param   string  $handle     // DB handle to use - defaults to "default"
     * @return  array
     */
    public function query($statement, array $params = array(), $handle = null)
    {
        $this->testHandle($handle);

        /* @var PDOStatement $sth */
        $sth = $this->connections[$handle]()->prepare($statement);
        $sth->execute($params);

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Query the database, don't return any results
     *
     * @access  public
     * @param   string  $statement
     * @param   array   $params
     * @param   string  $handle     // DB handle to use - defaults to "default"
     * @return  void
     */
    public function exec($statement, array $params = array(), $handle = null)
    {
        $this->testHandle($handle);

        /* @var PDOStatement $sth */
        $sth = $this->connections[$handle]()->prepare($statement);
        $sth->execute($params);
    }

    /**
     * Get the last insert id from the database
     *
     * @access  public
     * @param   string  $handle
     * @return  int
     */
    public function getLastInsertId($handle = null): int
    {
        $this->testHandle($handle);

        return (int) $this->connections[$handle]()->lastInsertId();
    }

    /**
     * Import a mock PDO library for testing
     *
     * @access  public
     * @param   object  $mock
     * @return  void
     */
    public function useMockDriver($mock)
    {
        if ($mock instanceof PDO) {
            $this->mockDriver = $mock;
        }
    }
}