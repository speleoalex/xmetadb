# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Purpose

**xmetadb** is a lightweight PHP database abstraction library. Its default backend stores records as XML-wrapped PHP files on the filesystem — no database server required. Drivers exist for MySQL/MariaDB, SQLite3, SQL Server, CSV, and PHP serialization. Switching backends requires changing one line in the table descriptor.

## Running the Example

```bash
php examples/contacts.php
```

No build step — the library is pure PHP (7.2+). Include it via:

```php
require_once 'xmetadb/xmetadb_core.php';
```

## Architecture

### Storage model (default `xmlphp` driver)

- **Database** = a directory (e.g. `data/myapp/`)
- **Table** = a descriptor `data/myapp/contacts.php` (XML field definitions) + a subdirectory `data/myapp/contacts/`
- **Record** = one file per row (e.g. `data/myapp/contacts/1.php`), prefixed with `<?php exit(0);?>` to block direct web access

### Key classes

| File | Role |
|---|---|
| `xmetadb/xmetadb_core.php` | Database/table creation, XML↔array helpers, file utilities, sorting |
| `xmetadb/XMETATable.php` | Table abstraction — loads descriptor, detects primary key, delegates CRUD to driver, handles file uploads/thumbnails |
| `xmetadb/XMETAField.php` | Field metadata (name, type, primarykey, defaultvalue, etc.) |
| `xmetadb/XMETADatabase.php` | SQL-like query interface — parses SELECT/INSERT/UPDATE/DELETE and delegates to `XMETATable` |
| `xmetadb/XMETATable_xmlphp.php` | Default driver (filesystem XML) |
| `xmetadb/XMETATable_mysql.php` | MySQL/MariaDB driver (`mysqli`) |
| `xmetadb/XMETATable_sqlite3.php` | SQLite3 driver |
| `xmetadb/XMETATable_sqlserver.php` | SQL Server driver (`sqlsrv`) |
| `xmetadb/XMETATable_csv.php` | CSV driver |
| `xmetadb/XMETATable_serialize.php` | PHP serialization driver |

### Driver selection

The driver is stored in the table descriptor XML (`<driver>xmlphp</driver>`). All drivers expose the same interface: `GetRecords()`, `InsertRecord()`, `UpdateRecord()`, `DelRecord()`, `GetNumRecords()`.

### Caching

`XMETATable` uses in-memory file-mtime tracking to invalidate the record cache automatically on changes.

### SQL parsing caveat

`XMETADatabase` uses `eval()` to evaluate WHERE clauses at runtime — this is intentional for the self-contained design but means SQL queries must only come from trusted sources.

## Field types

`varchar`, `text`, `int`, `image`, `file` — `image` and `file` trigger automatic upload handling and (for images) GD-based thumbnail generation.
