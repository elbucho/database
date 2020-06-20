<?php

namespace Elbucho\Database\Tests;
use Elbucho\Config\InvalidFileException;
use Elbucho\Config\Loader\File\IniFileLoader;
use Elbucho\Database\InvalidConfigException;
use PHPUnit\Framework\TestCase;
use Elbucho\Database\Database;
use Elbucho\Config\Config;

class PDOMock extends \PDO {
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct($dsn, $username = null, $passwd = null, $options = null) {
    }
}

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
    public function setup()
    {
        $loader = new IniFileLoader();
        $config = new Config(
            $loader->load(self::CONFIG_DIR . DIRECTORY_SEPARATOR . 'database.ini')
        );

        $PDOMock = $this->getMockBuilder('Elbucho\Database\Tests\PDOMock')
            ->setConstructorArgs(['mysql:hostname=localhost;dbname=test;charset=utf8'])
            ->setMethods(['prepare', 'lastInsertId'])
            ->getMock();

        $PDOStatement = $this->getMockBuilder('\PDOStatement')
            ->setMethods(['execute', 'fetchAll'])
            ->getMock();
        $PDOStatement->expects($this->any())
            ->method('execute')
            ->will(
                $this->returnCallback(function ($arguments) {
                    $this->lastExecuteArguments = $arguments;
                })
            );
        $PDOStatement->expects($this->any())
            ->method('fetchAll')
            ->will($this->returnCallback(array($this, 'fetchAllHelper')));

        $PDOMock
            ->expects($this->any())
            ->method('prepare')
            ->will(
                $this->returnCallback(function ($query) use ($PDOStatement) {
                    $this->lastQuery = $query;
                    return $PDOStatement;
                })
            );

        $PDOMock
            ->expects($this->any())
            ->method('lastInsertId')
            ->will(
                $this->returnCallback(function () {
                    return rand(0, 15);
                })
            );

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
    public function testConnections() {
        $this->setFetchAllReturnValue(['test1']);

        $response = $this->database->query(
            'SHOW DATABASES',
            [],
            'test1'
        );

        $this->assertEquals(['test1'], $response);

        $this->setFetchAllReturnValue(['test2']);

        $response = $this->database->query(
            'SHOW DATABASES',
            [],
            'test2'
        );

        $this->assertEquals(['test2'], $response);
    }

    /**
     * Test that we get an error when an invalid handle is called
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testInvalidConnection() {
        $error = false;

        try {
            $this->database->query(
                'SHOW DATABASES',
                [],
                'invalidConnection'
            );
        } catch (\PDOException $e) {
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
    public function testInvalidConfigFile()
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
    public function testEmptyConfigFile()
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
    public function testDuplicateConfigFile()
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
        $error = false;

        try {
            $this->database->exec(
                'SET NAMES=utf8',
                [],
                'test1'
            );
        } catch (\PDOException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertFalse($error);
    }

    /**
     * Test the lastInsertId command
     *
     * @access  public
     * @param   void
     * @return  void
     */
    public function testLastInsertId()
    {
        $error = false;

        try {
            $lastId = $this->database->getLastInsertId('test1');

            $this->assertIsInt($lastId);
        } catch (\PDOException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertFalse($error);
    }

    /**
     * Set the return value for the \PDOStatement "fetchAll" method
     *
     * @access  protected
     * @param   array   $return
     * @return  void
     */
    protected function setFetchAllReturnValue(array $return)
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
    public function fetchAllHelper()
    {
        if ( ! is_array($this->fetchAllReturnValue)) {
            return [];
        }

        return $this->fetchAllReturnValue;
    }
}