# xmetadb

A lightweight, file-based database abstraction layer for PHP. Store data as XML files with no server setup required, or switch to MySQL, SQLite3, SQL Server, CSV, or serialize backends by changing a single line in the table descriptor.

## Features

- **Zero-configuration default**: data stored as `.php`-wrapped XML files, directly in the filesystem
- **Multiple drivers**: `xmlphp` (default), `mysql`, `sqlite3`, `sqlserver`, `csv`, `serialize`
- **SQL-like query interface** via `XMETADatabase`
- **File upload / image handling** with automatic thumbnail generation (GD)
- **In-memory caching** with automatic invalidation on file change
- **PHP 7.2+** compatible; no external dependencies for the `xmlphp` driver

## Requirements

| Driver | Requirement |
|---|---|
| `xmlphp` (default) | PHP 7.2+ only |
| `sqlite3` | PHP `sqlite3` extension |
| `mysql` | PHP `mysqli` extension + MySQL/MariaDB server |
| `sqlserver` | PHP `sqlsrv` extension + SQL Server |
| `csv` | PHP 7.2+ only |
| `serialize` | PHP 7.2+ only |

## Installation

Copy the `xmetadb/` directory into your project and include the entry point:

```php
require_once 'xmetadb/xmetadb_core.php';
```

## Quick Start

```php
<?php
require_once 'xmetadb/xmetadb_core.php';

$db_path = __DIR__ . '/data';

// 1. Create a database (a directory)
createxmldatabase('myapp', $db_path);

// 2. Define table fields
$fields = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'title', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
    ['name' => 'done',  'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => '0'],
];
createxmltable('myapp', 'tasks', $fields, $db_path);

// 3. Get a table handle
$tasks = xmetadb_table('myapp', 'tasks', $db_path);

// 4. Insert
$r = $tasks->InsertRecord(['title' => 'Buy milk', 'done' => '0']);
echo "Inserted id={$r['id']}\n";

// 5. Read all
foreach ($tasks->GetRecords() as $row) {
    echo "{$row['id']} | {$row['title']} | done={$row['done']}\n";
}

// 6. Filter (LIKE wildcard: %value%)
$pending = $tasks->GetRecords(['done' => '0']);

// 7. Update
$tasks->UpdateRecord(['id' => $r['id'], 'done' => '1']);

// 8. Delete
$tasks->DelRecord($r['id']);
```

## API Reference

### Database & table management

```php
createxmldatabase($name, $path)                // create database directory
createxmltable($db, $table, $fields, $path)   // create table descriptor + data directory
xmldatabaseexists($db, $path)                  // bool
xmltableexists($db, $table, $path)             // bool
addxmltablefield($db, $table, $field, $path)   // add or update a field definition
getxmltablefield($db, $table, $field, $path)   // get field descriptor array
```

### Table handle

```php
$tbl = xmetadb_table($db, $table, $path, $params);
```

| Method | Description |
|---|---|
| `InsertRecord(array $values)` | Insert a record; returns the inserted row (with autoincrement id filled) or error string |
| `GetRecords($filter, $min, $length, $order, $reverse, $fields)` | Fetch multiple records |
| `GetRecord($filter)` | Fetch first matching record |
| `GetRecordByPrimaryKey($id)` | Fetch by primary key |
| `GetNumRecords($filter)` | Count matching records |
| `UpdateRecord(array $values)` | Update by primary key contained in `$values` |
| `DelRecord($pkvalue)` | Delete by primary key |

### Filter syntax (xmlphp driver)

```php
// Exact match
$tbl->GetRecords(['status' => 'active']);

// LIKE contains
$tbl->GetRecords(['name' => '%john%']);

// LIKE starts with
$tbl->GetRecords(['name' => 'john%']);

// LIKE ends with
$tbl->GetRecords(['name' => '%john']);
```

### Pagination & ordering

```php
// $min = start offset (1-based), $length = max rows
$page = $tbl->GetRecords(false, 1, 10, 'name');          // first 10, order by name ASC
$page = $tbl->GetRecords(false, 1, 10, 'name', true);    // first 10, order by name DESC
```

### Field definition

```php
$fields = [
    // Auto-increment integer primary key
    ['name' => 'id',         'primarykey' => '1', 'type' => 'int',      'extra' => 'autoincrement'],

    // String with default
    ['name' => 'title',      'primarykey' => '0', 'type' => 'varchar',  'defaultvalue' => ''],

    // Text (long content)
    ['name' => 'body',       'primarykey' => '0', 'type' => 'text'],

    // Image field (auto-thumbnail with GD)
    ['name' => 'photo',      'primarykey' => '0', 'type' => 'image',    'thumbsize' => '200'],
];
```

### SQL-like query interface

```php
require_once 'xmetadb/XMETADatabase.php';

$db = new XMETADatabase('myapp', $db_path);

$rows  = $db->Query("SELECT * FROM tasks WHERE done = '0' ORDER BY id LIMIT 1,10");
$count = $db->Query("SELECT COUNT(*) AS total FROM tasks");
$db->Query("INSERT INTO tasks (title, done) VALUES ('New task', '0')");
$db->Query("UPDATE tasks SET done = '1' WHERE id = '5'");
$db->Query("DELETE FROM tasks WHERE done = '1'");
$cols  = $db->Query("DESCRIBE tasks");
$tbls  = $db->Query("SHOW TABLES");
```

### Switching drivers

Add `<driver>` and connection details to the table descriptor when creating the table:

```php
// MySQL
createxmltable('myapp', 'tasks', $fields, $db_path, [
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'user'      => 'root',
    'password'  => 'secret',
    'database'  => 'myapp',
]);

// SQLite3 (file auto-created at $db_path/myapp.sqlite3)
createxmltable('myapp', 'tasks', $fields, $db_path, ['driver' => 'sqlite3']);
```

No code changes needed elsewhere — `xmetadb_table()` reads the driver from the descriptor automatically.

## Directory structure

```
xmetadb/
    xmetadb_core.php       ← include this in your project
    XMETADatabase.php      ← optional SQL-like query interface
    XMETATable.php         ← core table class
    XMETAField.php         ← field descriptor
    XMETATable_xmlphp.php  ← driver: XML files (default)
    XMETATable_mysql.php   ← driver: MySQL / MariaDB
    XMETATable_sqlite3.php ← driver: SQLite3
    XMETATable_sqlserver.php ← driver: SQL Server
    XMETATable_csv.php     ← driver: CSV files
    XMETATable_serialize.php ← driver: PHP serialize
```

## Data storage (xmlphp driver)

Each database is a directory; each table is a `.php` descriptor file plus a data subdirectory:

```
data/
    myapp/
        tasks.php          ← table descriptor (field definitions, driver)
        tasks/
            1.php          ← one file per record (or per index group)
            2.php
```

Files start with `<?php exit(0);?>` to prevent direct web access.

## License

GNU General Public License — see [LICENSE](LICENSE).
