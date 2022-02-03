<?php

namespace Elbucho\Database;
use Elbucho\Config\Config;
use Closure;
use PDOException;
use PDOStatement;
use PDO;

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
    private $connections;

    /**
     * Default handle to use
     *
     * @access  private
     * @var     string
     */
    private $defaultHandle;

    /**
     * Mock driver to use instead of a new PDO instance
     *
     * @access  private
     * @var     PDO
     */
    private $mockDriver;

    /**
     * Number of rows affected by the previously ran statement
     *
     * @access  private
     * @var     array
     */
    private $resultRows = [];

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
        $this->defaultHandle = $config->get('default_handle', 'default');
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
        /**
         * Determine if the dsns key exists, and if it contains
         * multiple DSNs or a singular one
         */
        if (isset($config->{'dsns'}) and $config->{'dsns'} instanceof Config) {
            if ($config->{'dsns'}->count() > 0) {
                $dsns = array();

                foreach ($config->{'dsns'} as $key => $value) {
                    if ($value instanceof Config) {
                        try {
                            $dsns[$key] = $this->createClosureFromConfig($value);
                        } catch (InvalidConfigException $e) {
                            continue;
                        }
                    }
                }

                if ( ! empty($dsns)) {
                    return $dsns;
                }
            }

            return [
                $this->defaultHandle => $this->createClosureFromConfig($config->{'dsns'})
            ];
        }

        return [
            $this->defaultHandle => $this->createClosureFromConfig($config)
        ];
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
     * @param   string|null $handle
     * @return  void
     * @throws  PDOException
     */
    private function testHandle(string $handle = null)
    {
        if (is_null($handle)) {
            $handle = $this->defaultHandle;
        }

        if ( ! array_key_exists($handle, $this->connections)) {
            throw new PDOException(sprintf(
                'Invalid handle: %s',
                $handle
            ));
        }

        if ($this->connections[$handle] instanceof Closure) {
            $this->connections[$handle] = $this->connections[$handle]();
        }

        if ( ! $this->connections[$handle] instanceof PDO) {
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
    public function addConnection(string $handle, Config $config): Database
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
     * @param   string      $statement
     * @param   array       $params
     * @param   string|null $handle     // DB handle to use - defaults to "default"
     * @return  array
     */
    public function query(string $statement, array $params = array(), string $handle = null): array
    {
        $sth = $this->exec($statement, $params, $handle);

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Query the database, don't return any results
     *
     * @access  public
     * @param   string      $statement
     * @param   array       $params
     * @param   string|null $handle     // DB handle to use - defaults to "default"
     * @return  PDOStatement
     */
    public function exec(string $statement, array $params = array(), string $handle = null): PDOStatement
    {
        $handle = (is_null($handle) ? $this->defaultHandle : $handle);
        $this->testHandle($handle);

        if ( ! array_key_exists($handle, $this->resultRows)) {
            $this->resultRows[$handle] = 0;
        }

        /* @var PDOStatement $sth */
        $sth = $this->connections[$handle]->prepare($statement);
        $sth->execute($params);
        $this->resultRows[$handle] = $sth->rowCount();

        return $sth;
    }

    /**
     * Get the last insert id from the database
     *
     * @access  public
     * @param   string|null $handle
     * @return  int
     */
    public function getLastInsertId(string $handle = null): int
    {
        $handle = (is_null($handle) ? $this->defaultHandle : $handle);
        $this->testHandle($handle);

        return (int) $this->connections[$handle]->lastInsertId();
    }

    /**
     * Return the number of rows affected by the last query to a given handle
     *
     * @access  public
     * @param   string|null $handle
     * @return  int
     */
    public function getRows(string $handle = null): int
    {
        $handle = (is_null($handle) ? $this->defaultHandle : $handle);

        if ( ! array_key_exists($handle, $this->resultRows)) {
            $this->resultRows[$handle] = 0;
        }

        return $this->resultRows[$handle];
    }

    /**
     * Set the PDO attributes for a given handle
     *
     * @access  public
     * @param   int         $attribute
     * @param   mixed       $value
     * @param   string|null $handle
     * @return  bool
     */
    public function setAttribute(int $attribute, $value, string $handle = null): bool
    {
        $handle = (is_null($handle) ? $this->defaultHandle : $handle);
        $this->testHandle($handle);

        return $this->connections[$handle]->setAttribute($attribute, $value);
    }

    /**
     * Import a mock PDO library for testing
     *
     * @access  public
     * @param   object  $mock
     * @return  void
     */
    public function useMockDriver(object $mock)
    {
        if ($mock instanceof PDO) {
            $this->mockDriver = $mock;
        }
    }
}