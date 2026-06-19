<?php
/**
 * Unit tests – XMETATable_csv driver
 * Run: php tests/test_csv.php
 *
 * Bugs fixed before this test was written:
 *   FIX-1: readCSVDatabase() now uses an instance-level mtime+size cache instead
 *          of a PHP static variable, so the cache is automatically invalidated
 *          after any write (InsertRecord, DelRecord, UpdateRecordBypk).
 *   FIX-2: Truncate() now resets $this->maxautoincrement so autoincrement
 *          restarts from 1 after a full table clear.
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../xmetadb/xmetadb_core.php';

$t = new TestRunner();

// ── Setup ────────────────────────────────────────────────────────────────────
$tmpdir = sys_get_temp_dir() . '/xmetadb_csv_' . uniqid();
mkdir($tmpdir);
$db  = 'testdb';
$tbl = 'contacts';

createxmldatabase($db, $tmpdir);
$fields = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'name',  'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'email', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'note',  'primarykey' => '0', 'type' => 'varchar'],
];
createxmltable($db, $tbl, $fields, $tmpdir, ['driver' => 'csv']);
$table = xmetadb_table($db, $tbl, $tmpdir);

echo "=== XMETATable_csv Driver Tests ===\n";

// ── INSERT ───────────────────────────────────────────────────────────────────
$t->section('INSERT');

$r1 = $table->InsertRecord(['name' => 'Alice',   'email' => 'alice@test.com', 'note' => 'Hi']);
$t->ok(is_array($r1),          'Insert Alice returns array');
$t->eq('1', (string)$r1['id'], 'Autoincrement: first id = 1');

$r2 = $table->InsertRecord(['name' => 'Bob',     'email' => 'bob@test.com',   'note' => 'Yo']);
$t->eq('2', (string)$r2['id'], 'Autoincrement: second id = 2');

$r3 = $table->InsertRecord(['name' => 'Charlie', 'email' => 'ch@test.com',    'note' => 'Ok']);
$t->eq('3', (string)$r3['id'], 'Autoincrement: third id = 3');

// ── GET RECORDS ──────────────────────────────────────────────────────────────
$t->section('GET RECORDS');

$all = $table->GetRecords();
$t->cnt(3, $all, 'GetRecords() returns all 3 records (cache invalidated by mtime fix)');

$byName = $table->GetRecords(['name' => 'Bob']);
$t->cnt(1, $byName, 'GetRecords([name=Bob]) returns 1 record');
$t->eq('bob@test.com', $byName[0]['email'], 'Filtered record has correct email');

// ── GET RECORD / GET BY PK ───────────────────────────────────────────────────
$t->section('GET RECORD / GET BY PK');

$rec = $table->GetRecord(['name' => 'Alice']);
$t->ok(is_array($rec),                  'GetRecord() returns array');
$t->eq('alice@test.com', $rec['email'], 'GetRecord() returns correct email');

$byPk = $table->GetRecordByPrimaryKey('2');
$t->ok(is_array($byPk),      'GetRecordByPrimaryKey(2) returns array');
$t->eq('Bob', $byPk['name'], 'GetRecordByPrimaryKey(2) returns Bob (cache fix)');

$byPk3 = $table->GetRecordByPrimaryKey('3');
$t->ok(is_array($byPk3),          'GetRecordByPrimaryKey(3) returns array (was broken before fix)');
$t->eq('Charlie', $byPk3['name'], 'GetRecordByPrimaryKey(3) returns Charlie');

$miss = $table->GetRecordByPrimaryKey('999');
$t->notOk($miss, 'GetRecordByPrimaryKey(999) returns false for missing record');

// ── GET NUM RECORDS ──────────────────────────────────────────────────────────
$t->section('GET NUM RECORDS');

$t->eq(3, $table->GetNumRecords(),                    'GetNumRecords() = 3');
$t->eq(1, $table->GetNumRecords(['name' => 'Alice']), 'GetNumRecords([name=Alice]) = 1');

// ── CSV ENCODING — values with commas and quotes ──────────────────────────────
$t->section('CSV ENCODING');

$rComma = $table->InsertRecord([
    'name'  => 'Dante, Alighieri',
    'email' => 'dante@test.com',
    'note'  => 'value,with,commas',
]);
$t->ok(is_array($rComma),          'Insert record with commas in value');
$t->eq('4', (string)$rComma['id'], 'Comma-value record gets id=4');

// mtime-fix: GetRecordByPrimaryKey now reads fresh data
$commaBack = $table->GetRecordByPrimaryKey('4');
$t->ok(is_array($commaBack),                    'Comma-value record is retrievable');
$t->eq('Dante, Alighieri', $commaBack['name'],  'Comma in name decoded correctly');
$t->eq('value,with,commas', $commaBack['note'], 'Multiple commas in note decoded correctly');

$rQuote = $table->InsertRecord([
    'name'  => 'She said "hello"',
    'email' => 'q@test.com',
    'note'  => 'A "quoted" note',
]);
$t->ok(is_array($rQuote), 'Insert record with double-quotes in value');

$quoteBack = $table->GetRecordByPrimaryKey($rQuote['id']);
$t->eq('She said "hello"', $quoteBack['name'], 'Double-quotes in name decoded correctly');
$t->eq('A "quoted" note',  $quoteBack['note'], 'Double-quotes in note decoded correctly');

// ── GET RECORDS after all inserts ─────────────────────────────────────────────
$t->section('GET RECORDS (after all 5 inserts)');

$all5 = $table->GetRecords();
$t->cnt(5, $all5, 'GetRecords() returns all 5 records');

// ── UPDATE ───────────────────────────────────────────────────────────────────
$t->section('UPDATE');

$upd = $table->UpdateRecord(['id' => '1', 'name' => 'Alicia', 'email' => 'alicia@test.com', 'note' => 'Hey']);
$t->ok(is_array($upd),         'UpdateRecord returns array');
$t->eq('Alicia', $upd['name'], 'UpdateRecord returns updated name');

$chk = $table->GetRecordByPrimaryKey('1');
$t->eq('Alicia',          $chk['name'],  'GetByPk confirms updated name (cache refreshed)');
$t->eq('alicia@test.com', $chk['email'], 'GetByPk confirms updated email');

// ── DELETE ───────────────────────────────────────────────────────────────────
$t->section('DELETE');

$del = $table->DelRecord('2');
$t->ok($del, 'DelRecord(2) returns true');

$t->eq(4, $table->GetNumRecords(), 'Record count = 4 after delete (5 - 1)');

$gone = $table->GetRecordByPrimaryKey('2');
$t->notOk($gone, 'Deleted record no longer retrievable');

// ── ORDER BY AND PAGINATION ──────────────────────────────────────────────────
// At this point 4 records remain: Alicia(1), Charlie(3), Dante(4), She said...(5)

$t->section('ORDER BY');

$asc = $table->GetRecords(false, false, false, 'name');
$t->ok(is_array($asc),              'ORDER BY name returns array');
$t->eq('Alicia', $asc[0]['name'],   'ORDER BY name ASC: first = Alicia');
$t->eq('Charlie', $asc[1]['name'],  'ORDER BY name ASC: second = Charlie');

$desc = $table->GetRecords(false, false, false, 'name', true);
$t->ok(is_array($desc),             'ORDER BY name DESC returns array');
// Lexicographic: 'She said "hello"' > 'Dante, Alighieri' > 'Charlie' > 'Alicia'
$t->eq('Alicia', $desc[3]['name'],  'ORDER BY name DESC: last = Alicia');

$t->section('PAGINATION');

$page1 = $table->GetRecords(false, 1, 2, 'name');
$t->cnt(2, $page1, 'Pagination min=1 length=2 returns 2 records');
$t->eq('Alicia', $page1[0]['name'],  'Page 1 item 1 = Alicia (sorted by name)');
$t->eq('Charlie', $page1[1]['name'], 'Page 1 item 2 = Charlie');

$page2 = $table->GetRecords(false, 3, 2, 'name');
$t->cnt(2, $page2, 'Pagination min=3 length=2 returns 2 records');

// Ensure the two pages contain distinct records (no duplicates)
$names1 = array_column($page1, 'name');
$names2 = array_column($page2, 'name');
$t->cnt(0, array_intersect($names1, $names2), 'Pages 1 and 2 have no overlapping records');

// ── TRUNCATE ─────────────────────────────────────────────────────────────────
$t->section('TRUNCATE');

$table->Truncate();

$rNew = $table->InsertRecord(['name' => 'Fresh', 'email' => 'fresh@test.com', 'note' => 'New']);
$t->ok(is_array($rNew), 'Insert after Truncate succeeds');
$t->eq('1', (string)$rNew['id'], 'First insert after Truncate gets id=1 (maxautoincrement reset fix)');

$t->eq(1, $table->GetNumRecords(), 'GetNumRecords() = 1 after Truncate + 1 insert');

// ── Teardown ──────────────────────────────────────────────────────────────────
xmetadb_remove_dir_rec($tmpdir);

exit($t->summary('XMETATable_csv') ? 0 : 1);
