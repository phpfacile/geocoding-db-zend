PHPFacile! Geocoding-db-zend
==================

This service store in database (using zend-db) a location retrieved using phpfacile/geocoding (Cf. https://github.com/phpfacile/geocoding).

Installation
-----
At the root of your project type
```
composer require phpfacile/geocoding-db-zend
```
Or add "phpfacile/geocoding-db-zend": "^1.0" to the "require" part of your composer.json file
```composer
"require": {
    "phpfacile/geocoding-db-zend": "^1.0"
}
```

Usage
-----
### Step 1 : Adapter instanciation ###
Instanciate a Zend Adapter to allow a connexion to a database.

Example with SQLite (for test purpose only)
```php
$config = [
    'driver' => 'Pdo_Sqlite',
    'database' => 'my_database.sqlite',
];
$adapter = new Zend\Db\Adapter\Adapter($config);
```

Example with MySQL
```php
$config = [
    'driver' => 'Pdo_Mysql',
    'host' => 'localhost'
    'dbname' => 'my_database',
    'user' => 'my_username',
    'password' => 'my_password',
];
$adapter = new Zend\Db\Adapter\Adapter($config);
```

### Step 2 : LocationService instanciation ###
```php
use PHPFacile\Geocoding\Db\Service\LocationService;

$locationService = new LocationService($adapter);
```

### Step 3 : Store a location and/or get it's id if already in database ###
Assuming you've got a $location StdClass retrieved from a previous phpfacile/geocoding query:
```php
$id = $locationService->getIdOfStdClassLocationAfterInsertIfNeeded($location)
```
