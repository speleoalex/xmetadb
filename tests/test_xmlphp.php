<?php
/**
 * Unit tests – XMETATable_xmlphp driver (default file-based XML backend)
 * Run: php tests/test_xmlphp.php
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../xmetadb/xmetadb_core.php';

$t = new TestRunner();

// ── Setup ────────────────────────────────────────────────────────────────────
$tmpdir = sys_get_temp_dir() . '/xmetadb_xmlphp_' . uniqid();
mkdir($tmpdir);
$db  = 'testdb';
$tbl = 'users';

createxmldatabase($db, $tmpdir);
$fields = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'name',  'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'email', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'age',   'primarykey' => '0', 'type' => 'int'],
];
createxmltable($db, $tbl, $fields, $tmpdir);
$table = xmetadb_table($db, $tbl, $tmpdir);

echo "=== XMETATable_xmlphp Driver Tests ===\n";

// ── INSERT ───────────────────────────────────────────────────────────────────
$t->section('INSERT');

$r1 = $table->InsertRecord(['name' => 'Alice',   'email' => 'alice@test.com',   'age' => '30']);
$t->ok(is_array($r1),          'Insert Alice returns array');
$t->eq('1', (string)$r1['id'], 'Autoincrement: first id = 1');

$r2 = $table->InsertRecord(['name' => 'Bob',     'email' => 'bob@test.com',     'age' => '25']);
$t->eq('2', (string)$r2['id'], 'Autoincrement: second id = 2');

$r3 = $table->InsertRecord(['name' => 'Charlie', 'email' => 'charlie@test.com', 'age' => '35']);
$t->eq('3', (string)$r3['id'], 'Autoincrement: third id = 3');

// Duplicate PK must return an error string, not silently overwrite
$dup = $table->InsertRecord(['id' => '1', 'name' => 'Dup', 'email' => 'dup@test.com', 'age' => '1']);
$t->ok(is_string($dup) && strpos($dup, 'error') !== false, 'Duplicate PK returns error string');

// ── GET RECORDS ──────────────────────────────────────────────────────────────
$t->section('GET RECORDS');

$all = $table->GetRecords();
$t->cnt(3, $all, 'GetRecords() returns all 3 records');

$byName = $table->GetRecords(['name' => 'Bob']);
$t->cnt(1, $byName, 'GetRecords([name=Bob]) returns 1 record');
$t->eq('Bob', $byName[0]['name'], 'Filtered record has correct name');

// LIKE patterns
$mid   = $table->GetRecords(['name' => '%li%']);
$t->cnt(2, $mid, 'GetRecords([name=%li%]) matches Alice and Charlie');

$end   = $table->GetRecords(['name' => '%ice']);
$t->cnt(1, $end, 'GetRecords([name=%ice]) matches Alice only');

$start = $table->GetRecords(['name' => 'Bo%']);
$t->cnt(1, $start, 'GetRecords([name=Bo%]) matches Bob only');

// ── GET RECORD / GET BY PK ───────────────────────────────────────────────────
$t->section('GET RECORD / GET BY PK');

$rec = $table->GetRecord(['name' => 'Alice']);
$t->ok(is_array($rec),             'GetRecord() returns array');
$t->eq('alice@test.com', $rec['email'], 'GetRecord() returns correct email');

$byPk = $table->GetRecordByPrimaryKey('2');
$t->ok(is_array($byPk),      'GetRecordByPrimaryKey(2) returns array');
$t->eq('Bob', $byPk['name'], 'GetRecordByPrimaryKey(2) returns Bob');

$miss = $table->GetRecordByPrimaryKey('999');
$t->notOk($miss, 'GetRecordByPrimaryKey(999) returns falsy for missing record');

// ── GET NUM RECORDS ──────────────────────────────────────────────────────────
$t->section('GET NUM RECORDS');

$t->eq(3, $table->GetNumRecords(),                 'GetNumRecords() = 3');
$t->eq(1, $table->GetNumRecords(['name' => 'Alice']), 'GetNumRecords([name=Alice]) = 1');
$t->eq(0, $table->GetNumRecords(['name' => 'Zzz']),   'GetNumRecords([name=Zzz]) = 0 (not found)');

// ── SORTING AND PAGINATION ───────────────────────────────────────────────────
$t->section('SORTING AND PAGINATION');

$asc = $table->GetRecords(false, false, false, 'name');
$t->eq('Alice',   $asc[0]['name'], 'Sorted ASC by name: first = Alice');
$t->eq('Bob',     $asc[1]['name'], 'Sorted ASC by name: second = Bob');
$t->eq('Charlie', $asc[2]['name'], 'Sorted ASC by name: third = Charlie');

// Sort + reverse = DESC order (deterministic)
$rev = $table->GetRecords(false, false, false, 'name', true);
$t->eq('Charlie', $rev[0]['name'], 'Sorted DESC by name: first = Charlie');
$t->eq('Alice',   $rev[2]['name'], 'Sorted DESC by name: last = Alice');

// Pagination: skip 1, take 2
$page = $table->GetRecords(false, 2, 2);
$t->cnt(2, $page, 'Pagination min=2 length=2 returns 2 records');

// ── UPDATE ───────────────────────────────────────────────────────────────────
$t->section('UPDATE');

$upd = $table->UpdateRecord(['id' => '1', 'name' => 'Alicia', 'email' => 'alicia@test.com', 'age' => '31']);
$t->ok(is_array($upd),           'UpdateRecord returns array on success');
$t->eq('Alicia', $upd['name'],   'UpdateRecord returns updated name');

$chk = $table->GetRecordByPrimaryKey('1');
$t->eq('Alicia',           $chk['name'],  'GetByPk confirms updated name');
$t->eq('alicia@test.com',  $chk['email'], 'GetByPk confirms updated email');
$t->eq('31',    (string)$chk['age'],      'GetByPk confirms updated age');

// Update non-existent record should return false
$bad = $table->UpdateRecord(['id' => '999', 'name' => 'Ghost', 'email' => 'g@t.com', 'age' => '0']);
$t->notOk($bad, 'UpdateRecord on missing PK returns falsy');

// ── SPECIAL CHARACTERS (XML encoding) ────────────────────────────────────────
$t->section('SPECIAL CHARACTERS');

$rSpc = $table->InsertRecord(['name' => 'A&B <C> "D"', 'email' => 'spc@test.com', 'age' => '40']);
$t->ok(is_array($rSpc), 'Insert record with &, <, > in values');
$t->eq('4', (string)$rSpc['id'], 'Special chars record gets id=4');

$spcBack = $table->GetRecordByPrimaryKey($rSpc['id']);
$t->eq('A&B <C> "D"', $spcBack['name'], 'Special chars decoded correctly on retrieval');

// ── DELETE ───────────────────────────────────────────────────────────────────
$t->section('DELETE');

$del = $table->DelRecord('2');
$t->ok($del, 'DelRecord(2) returns true');

$t->eq(3, $table->GetNumRecords(), 'Record count = 3 after deleting one of 4 records');

$gone = $table->GetRecordByPrimaryKey('2');
$t->notOk($gone, 'Deleted record (id=2) no longer retrievable');

// Delete again – should return false (file not found)
$del2 = $table->DelRecord('2');
$t->notOk($del2, 'DelRecord on already-deleted record returns false');

// ── AUTOINCREMENT AFTER DELETE ────────────────────────────────────────────────
$t->section('AUTOINCREMENT AFTER DELETE');

$r5 = $table->InsertRecord(['name' => 'Eve', 'email' => 'eve@test.com', 'age' => '22']);
$t->eq('5', (string)$r5['id'], 'Autoincrement after delete continues from max id (5)');

// ── TRUNCATE ─────────────────────────────────────────────────────────────────
$t->section('TRUNCATE');

$table->Truncate();
$t->eq(0, $table->GetNumRecords(), 'GetNumRecords() = 0 after Truncate');

$rNew = $table->InsertRecord(['name' => 'Fresh', 'email' => 'fresh@test.com', 'age' => '10']);
$t->ok(is_array($rNew), 'Insert after Truncate succeeds');
$t->eq('1', (string)$rNew['id'], 'Autoincrement restarts at 1 after Truncate');

// ── DATABASE / TABLE EXISTENCE ────────────────────────────────────────────────
$t->section('DATABASE / TABLE EXISTENCE');

$t->ok(xmldatabaseexists($db, $tmpdir),         'xmldatabaseexists() = true for existing db');
$t->ok(xmltableexists($db, $tbl, $tmpdir),      'xmltableexists()    = true for existing table');
$t->notOk(xmldatabaseexists('nope', $tmpdir),   'xmldatabaseexists() = false for missing db');
$t->notOk(xmltableexists($db, 'nope', $tmpdir), 'xmltableexists()    = false for missing table');

// ── Teardown ──────────────────────────────────────────────────────────────────
xmetadb_remove_dir_rec($tmpdir);

exit($t->summary('XMETATable_xmlphp') ? 0 : 1);
