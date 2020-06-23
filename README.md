# elbucho/database

This project provides a utility wrapper around PDO requests for MySQL.  It allows you to lazy-load multiple 
different database connections in one callable object, and gives you a streamlined access to PDO's methods.

## Configuration

The Database class is instantiated by passing it an Elbucho\Config object with one or more DSNs in it.
See the elbucho/config documentation for instructions on how to place and read in your config files.

### DSNs
Database connections must be stored in the "dsns" key of your Config object, and each must have the below
required keys.  You can include multiple connections in one Config object.

The required keys for each database connection are:

* host      => This is the hostname (eg. localhost)
* dbname    => The name of the database you're connecting to
* user      => Your database connection's username
* pass      => Your database connection's password

Additionally, the optional key "port" can be specified with the port number your database server is running on.
This defaults to 3306.

### Default Handle
If your config file only contains information for one database connection, the Database object will set the handle
for this connection to "default".  If you have multiple connections, each connection must be prefaced with the
handle name for that connection.

You can override the default handle name by specifying a 'default_handle' key in your config:

```
default_handle: foobar
```

Below are two sample .yml files. This first one shows a singular connection, and it will be automatically 
assigned the default handle.

```
dsns:
    host:   localhost
    port:   3307
    dbname: app_data
    user:   app_user
    pass:   app_password
```

This .yml file shows multiple connections (eg. a dev and production db server):

```
dsns:
    dev:
        host:   localhost
        port:   3306
        dbname: app_dev
        user:   dev_user
        pass:   dev_password

    prod:
        host:   10.20.1.101
        port:   3308
        dbname: app_prod
        user:   prod_user
        pass:   prod_password
```

This will create two handles in the $database object: "dev" and "prod".

## Querying the Database

Once your config file is set, you can instantiate a database object and query it in one of two ways:

### $database->query():
This method takes up to 3 arguments: your query itself, any parameters you wish to pass to the MySQL engine, 
and the handle to query (defaults to "default").  It returns an array of results or throws a \PDOException if
there was an issue with the query.

```
$database = new Database($config);

$results = $database->query('SELECT * FROM users`, [], 'prod');

foreach ($results as $user) {
    ...
}
```

### $database->exec():
This method takes the same 3 arguments as query(), but does not return results.  It is useful for passing
commands to MySQL that you do not require a return for: 

```
$database->exec('SET NAMES utf8mb4', [], 'prod');
```

```
$newUser = ['johnSmith', 'john.smith@company.org'];

$database->exec('INSERT INTO users (username, email) VALUES (?,?)', $newUser, 'prod');
```

## Fetching last insert ID
If you've just inserted a row or rows into your database, you can get the last insert id by typing:

```
$lastId = $database->getLastInsertId('prod');

var_dump($lastId);

// int(12345)
```

Again, if the handle is left out, it will use whatever your default handle is set to.
