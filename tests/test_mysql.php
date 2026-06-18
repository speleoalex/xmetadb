<?php
/**
 * Unit tests – XMETATable_mysql driver
 * Credentials: localhost / root / (no password) / database: xmetadb
 * Run: php tests/test_mysql.php
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../xmetadb/xmetadb_core.php';

// ── Pre-flight: check MySQL availability ────────────────────────────────────
if (!extension_loaded('mysqli')) {
    echo "SKIP: mysqli extension not loaded\n";
    exit(0);
}
$probe = @new mysqli('localhost', 'root', '');
if ($probe->connect_errno) {
    echo "SKIP: MySQL not available ({$probe->connect_error})\n";
    exit(0);
}
$probe->close();

$t = new TestRunner();

// ── Setup ────────────────────────────────────────────────────────────────────
$tmpdir   = sys_get_temp_dir() . '/xmetadb_mysql_' . uniqid();
mkdir($tmpdir);
$db       = 'testdb';
$tbl      = 'users';
$sqltable = 'xmetadb_phptest_' . substr(md5(uniqid()), 0, 8); // unique name avoids conflicts

createxmldatabase($db, $tmpdir);

$fields = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'name',  'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'email', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'age',   'primarykey' => '0', 'type' => 'int'],
];

// Pass MySQL connection details via the $singlefilename array parameter
createxmltable($db, $tbl, $fields, $tmpdir, [
    'driver'   => 'mysql',
    'host'     => 'localhost',
    'user'     => 'root',
    'password' => '',
    'database' => 'xmetadb',
    'sqltable' => $sqltable,
]);

$table = xmetadb_table($db, $tbl, $tmpdir);

echo "=== XMETATable_mysql Driver Tests (sqltable={$sqltable}) ===\n";

// ── INSERT ───────────────────────────────────────────────────────────────────
$t->section('INSERT');

$r1 = $table->InsertRecord(['name' => 'Alice',   'email' => 'alice@test.com',   'age' => '30']);
$t->ok(is_array($r1),          'Insert Alice returns array');
$t->eq('Alice', $r1['name'],   'Inserted record has correct name');

$r2 = $table->InsertRecord(['name' => 'Bob',     'email' => 'bob@test.com',     'age' => '25']);
$t->ok(is_array($r2),          'Insert Bob returns array');

$r3 = $table->InsertRecord(['name' => 'Charlie', 'email' => 'charlie@test.com', 'age' => '35']);
$t->ok(is_array($r3),          'Insert Charlie returns array');

// Autoincrement: ids should be sequential
$t->ok((int)$r1['id'] > 0,  'Alice id is a positive integer');
$t->ok((int)$r2['id'] > (int)$r1['id'], 'Bob id > Alice id (autoincrement)');
$t->ok((int)$r3['id'] > (int)$r2['id'], 'Charlie id > Bob id (autoincrement)');

// ── GET RECORDS ──────────────────────────────────────────────────────────────
$t->section('GET RECORDS');

$all = $table->GetRecords();
$t->cnt(3, $all, 'GetRecords() returns all 3 records');

$byName = $table->GetRecords(['name' => 'Bob']);
$t->cnt(1, $byName, 'GetRecords([name=Bob]) returns 1 record');
$t->eq('Bob', $byName[0]['name'], 'Filtered record has correct name');

// ── GET RECORD / GET BY PK ───────────────────────────────────────────────────
$t->section('GET RECORD / GET BY PK');

$rec = $table->GetRecord(['name' => 'Alice']);
$t->ok(is_array($rec),                 'GetRecord([name=Alice]) returns array');
$t->eq('alice@test.com', $rec['email'], 'GetRecord returns correct email');

$byPk = $table->GetRecordByPrimaryKey($r2['id']);
$t->ok(is_array($byPk),      'GetRecordByPrimaryKey returns array');
$t->eq('Bob', $byPk['name'], 'GetRecordByPrimaryKey returns correct record');

$miss = $table->GetRecordByPrimaryKey('999999');
$t->notOk($miss, 'GetRecordByPrimaryKey(999999) returns falsy for missing record');

// ── GET NUM RECORDS ──────────────────────────────────────────────────────────
$t->section('GET NUM RECORDS');

$t->eq(3, (int)$table->GetNumRecords(), 'GetNumRecords() = 3');
$t->eq(1, (int)$table->GetNumRecords(['name' => 'Alice']), 'GetNumRecords([name=Alice]) = 1');

// ── ORDER AND PAGINATION ──────────────────────────────────────────────────────
$t->section('ORDER AND PAGINATION');

$asc = $table->GetRecords(false, false, false, 'name');
$t->ok(is_array($asc) && count($asc) === 3, 'GetRecords with ORDER returns 3 records');
$t->eq('Alice', $asc[0]['name'], 'Sorted ASC by name: first = Alice');

// Pagination
$page = $table->GetRecords(false, 0, 2);
$t->cnt(2, $page, 'Pagination LIMIT 0,2 returns 2 records');

// ── UPDATE ───────────────────────────────────────────────────────────────────
$t->section('UPDATE');

$upd = $table->UpdateRecord(['id' => $r1['id'], 'name' => 'Alicia', 'email' => 'alicia@test.com', 'age' => '31']);
$t->ok(is_array($upd),         'UpdateRecord returns array on success');
$t->eq('Alicia', $upd['name'], 'UpdateRecord returns updated name');

$chk = $table->GetRecordByPrimaryKey($r1['id']);
$t->eq('Alicia',          $chk['name'],  'GetByPk confirms updated name');
$t->eq('alicia@test.com', $chk['email'], 'GetByPk confirms updated email');

// ── DELETE ───────────────────────────────────────────────────────────────────
$t->section('DELETE');

$del = $table->DelRecord($r2['id']);
$t->ok($del, 'DelRecord(Bob) returns true');

$t->eq(2, (int)$table->GetNumRecords(), 'Record count = 2 after delete');

$gone = $table->GetRecordByPrimaryKey($r2['id']);
$t->notOk($gone, 'Deleted record no longer retrievable');

// ── TRUNCATE ─────────────────────────────────────────────────────────────────
$t->section('TRUNCATE');

$table->Truncate();
$t->eq(0, (int)$table->GetNumRecords(), 'GetNumRecords() = 0 after Truncate');

$rNew = $table->InsertRecord(['name' => 'Fresh', 'email' => 'fresh@test.com', 'age' => '10']);
$t->ok(is_array($rNew), 'Insert after Truncate succeeds');

// ── String WHERE filter via GetRecords ────────────────────────────────────────
$t->section('STRING WHERE FILTER');

// The mysql driver accepts a raw SQL WHERE string
$filtered = $table->GetRecords("name LIKE 'Fresh'");
$t->cnt(1, $filtered, 'GetRecords(raw WHERE string) works for mysql driver');
$t->eq('Fresh', $filtered[0]['name'], 'Correct record returned via raw WHERE');

// ── Teardown ──────────────────────────────────────────────────────────────────
// Drop the MySQL test table
$table->driverclass->dbQuery("DROP TABLE IF EXISTS `{$sqltable}`");
xmetadb_remove_dir_rec($tmpdir);

exit($t->summary('XMETATable_mysql') ? 0 : 1);
