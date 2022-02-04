<?php

/** @noinspection PhpIllegalPsrClassPathInspection */
namespace Elbucho\Database\Tests;
use Elbucho\Config\InvalidFileException;
use Elbucho\Config\Loader\File\IniFileLoader;
use Elbucho\Database\InvalidConfigException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Elbucho\Database\Database;
use Elbucho\Config\Config;

class DatabaseTest extends TestCase
{
    const CONFIG_DIR = __DIR__ . '/docs';

    /**
     * Database object
     *
     * @access  protected
     * @var     Database
     */
    protected $database;

    /**
     * Last query
     *
     * @access  protected
     * @var     string
     */
    protected $lastQuery;

    /**
     * Last execute arguments
     *
     * @access  protected
     * @var     array
     */
    protected $lastExecuteArguments = [];

    /**
     * Array of arguments to return for the return value
     *
     * @access  protected
     * @var     array
     */
    protected $fetchAllReturnValue = [];

    /**
     * Set up the mock PDO class
     *
     * @access  public
     * @param   void
     * @return  void
     * @throws  InvalidFileException
     * @throws  InvalidConfigException
     */
    public function setup(): void
    {
        $loader = new IniFileLoader();
        $config = new Config(
            $loader->load(self::CONFIG_DIR . DIRECTORY_SEPARATOR . 'database.ini')
        );

        $PDOStatement = $this->getMockBuilder('stdClass')
            ->addMethods(['execute', 'fetchAll', 'rowCount'])
            ->getMock();

        $PDOStatement->method('execute')
            ->will(
                $this->returnCallback(function ($arguments) {
                    $this->lastExecuteArguments = $arguments;
                })
            );

        $PDOStatement->method('fetchAll')
            ->will($this->returnCallback(array($this, 'fetchAllHelper')));

        $PDOStatement->method('rowCount')
            ->will(
                $this->returnCallback(function () {
                    return rand(0, 15);
                })
            );

        $PDOMock = $this->getMockBuilder('stdClass')
            ->addMethods(['prepare', 'lastInsertId', 'setAttribute'])
            ->getMock();

        $PDOMock
            ->method('prepare')
            ->will(
                $this->returnCallback(function ($query) use ($PDOStatement) {
                    $this->lastQuery = $query;
                    return $PDOStatement;
                })
            );

        $PDOMock
            ->method('lastInsertId')
            ->will(
                $this->returnCallback(function () {
                    return rand(0, 15);
                })
            );

        $PDOMock
            ->method('setAttribute')
            ->willReturn(true);

        $this->database = new Database($config);
        $this->database->useMockDriver($PDOMock);
    }

    /**
     * Test that we have two potential PDO connections in the self::$database variable
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testConnections(): void
    {
        $this->setFetchAllReturnValue(['test1']);

        $response = $this->database->query(
            'SHOW DATABASES',
            [],
            'test1'
        );

        $databases = [];

        foreach ($response as $value) {
            $databases[] = $value['Database'];
        }

        $this->assertTrue(in_array('test1', $databases));

        $this->setFetchAllReturnValue(['test2']);

        $response = $this->database->query(
            'SHOW DATABASES',
            [],
            'test2'
        );

        $databases = [];

        foreach ($response as $value) {
            $databases[] = $value['Database'];
        }

        $this->assertTrue(in_array('test2', $databases));
    }

    /**
     * Test that we get an error when an invalid handle is called
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testInvalidConnection(): void
    {
        $error = false;

        try {
            $this->database->query(
                'SHOW DATABASES',
                [],
                'invalidConnection'
            );
        } catch (PDOException $e) {
            $error = true;
        }

        $this->assertTrue($error);
    }

    /**
     * Test an invalid database connection config
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testInvalidConfigFile(): void
    {
        $invalidConfig = array(
            'host'  => 'localhost',
            'foo'   => 'bar'
        );

        $error = false;

        try {
            $this->database->addConnection('invalid', new Config($invalidConfig));
        } catch (InvalidConfigException $e) {
            $error = true;
        }

        $this->assertTrue($error);
    }

    /**
     * Test an empty database connection config
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testEmptyConfigFile(): void
    {
        $emptyConfig = [];

        $error = false;

        try {
            $this->database->addConnection('empty', new Config($emptyConfig));
        } catch (InvalidConfigException $e) {
            $error = true;
        }

        $this->assertTrue($error);
    }

    /**
     * Test an empty database connection config
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testDuplicateConfigFile(): void
    {
        $duplicateConfig = [
            'host'      => 'localhost',
            'port'      => 3306,
            'dbname'    => 'test1',
            'user'      => 'testuser1',
            'pass'      => 'testpass1'
        ];

        $error = false;

        try {
            $this->database->addConnection('test1', new Config($duplicateConfig));
        } catch (InvalidConfigException $e) {
            $error = true;
        }

        $this->assertTrue($error);
    }

    /**
     * Test the exec command
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testExec()
    {
        try {
            $this->database->exec(
                'SET NAMES=utf8',
                [],
                'test1'
            );
        } catch (PDOException $e) {
            try {
                $this->database->exec(
                    'SET NAMES "utf8"',
                    [],
                    'test1'
                );
            } catch (PDOException $e) {
                $this->fail($e->getMessage());
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Test the lastInsertId command
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testLastInsertId(): void
    {
        try {
            $lastId = $this->database->getLastInsertId('test1');

            $this->assertIsInt($lastId);
        } catch (PDOException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(true);
    }

    /**
     * Test the getRows command
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testGetRows(): void
    {
        try {
            $rows = $this->database->getRows('test1');

            $this->assertIsInt($rows);
        } catch (PDOException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(true);
    }

    /**
     * Test the setAttribute command
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testSetAttribute(): void
    {
        $this->assertTrue(
            $this->database->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION,
                'test1'
            )
        );
    }

    /**
     * Set the return value for the \PDOStatement "fetchAll" method
     *
     * @access  protected
     * @param   array   $return
     * @return  void
     */
    protected function setFetchAllReturnValue(array $return): void
    {
        $this->fetchAllReturnValue = $return;
    }

    /**
     * Helper for the \PDOStatement "fetchAll" method
     *
     * @access  public
     * @param   void
     * @return  array
     */
    public function fetchAllHelper(): array
    {
        if ( ! is_array($this->fetchAllReturnValue)) {
            return [];
        }

        return $this->fetchAllReturnValue;
    }
}