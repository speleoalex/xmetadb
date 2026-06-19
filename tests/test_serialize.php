<?php
/**
 * Unit tests – XMETATable_serialize driver (PHP serialized files)
 * Run: php tests/test_serialize.php
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../xmetadb/xmetadb_core.php';

$t = new TestRunner();

// ── Setup ────────────────────────────────────────────────────────────────────
$tmpdir = sys_get_temp_dir() . '/xmetadb_ser_' . uniqid();
mkdir($tmpdir);
$db  = 'testdb';
$tbl = 'items';

createxmldatabase($db, $tmpdir);

$fields = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'name',  'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'value', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'qty',   'primarykey' => '0', 'type' => 'int'],
];

createxmltable($db, $tbl, $fields, $tmpdir, ['driver' => 'serialize']);

$table = xmetadb_table($db, $tbl, $tmpdir);

echo "=== XMETATable_serialize Driver Tests ===\n";

// ── INSERT ───────────────────────────────────────────────────────────────────
$t->section('INSERT');

$r1 = $table->InsertRecord(['name' => 'Apple',  'value' => '1.50', 'qty' => '10']);
$t->ok(is_array($r1),          'Insert Apple returns array');
$t->eq('1', (string)$r1['id'], 'Autoincrement: first id = 1');

$r2 = $table->InsertRecord(['name' => 'Banana', 'value' => '0.75', 'qty' => '20']);
$t->eq('2', (string)$r2['id'], 'Autoincrement: second id = 2');

$r3 = $table->InsertRecord(['name' => 'Cherry', 'value' => '3.00', 'qty' => '5']);
$t->eq('3', (string)$r3['id'], 'Autoincrement: third id = 3');

// Each record is stored as a separate .s.php file
$t->ok(
    file_exists("$tmpdir/$db/$tbl/1.s.php"),
    'Record id=1 stored as 1.s.php on disk'
);

// ── GET RECORDS ──────────────────────────────────────────────────────────────
$t->section('GET RECORDS');

$all = $table->GetRecords();
$t->cnt(3, $all, 'GetRecords() returns all 3 records');

$byName = $table->GetRecords(['name' => 'Banana']);
$t->cnt(1, $byName, 'GetRecords([name=Banana]) returns 1 record');
$t->eq('Banana', $byName[0]['name'], 'Filtered record has correct name');

// ── GET RECORD / GET BY PK ───────────────────────────────────────────────────
$t->section('GET RECORD / GET BY PK');

$rec = $table->GetRecord(['name' => 'Apple']);
$t->ok(is_array($rec),        'GetRecord() returns array');
$t->eq('1.50', $rec['value'], 'GetRecord() returns correct value');

$byPk = $table->GetRecordByPrimaryKey('2');
$t->ok(is_array($byPk),        'GetRecordByPrimaryKey(2) returns array');
$t->eq('Banana', $byPk['name'], 'GetRecordByPrimaryKey(2) returns Banana');

$miss = $table->GetRecordByPrimaryKey('999');
$t->notOk($miss, 'GetRecordByPrimaryKey(999) returns falsy for missing record');

// ── GET NUM RECORDS ──────────────────────────────────────────────────────────
$t->section('GET NUM RECORDS');

$t->eq(3, $table->GetNumRecords(), 'GetNumRecords() = 3');
$t->eq(1, $table->GetNumRecords(['name' => 'Apple']), 'GetNumRecords([name=Apple]) = 1');

// ── UPDATE ───────────────────────────────────────────────────────────────────
$t->section('UPDATE');

$upd = $table->UpdateRecord(['id' => '1', 'name' => 'Green Apple', 'value' => '1.80', 'qty' => '8']);
$t->ok(is_array($upd),              'UpdateRecord returns array');
$t->eq('Green Apple', $upd['name'], 'UpdateRecord returns updated name');

$chk = $table->GetRecordByPrimaryKey('1');
$t->eq('Green Apple', $chk['name'],  'GetByPk confirms updated name');
$t->eq('1.80',        $chk['value'], 'GetByPk confirms updated value');

// Verify serialized file was overwritten (not a second file)
$t->ok(
    file_exists("$tmpdir/$db/$tbl/1.s.php"),
    'Serialized file 1.s.php still exists after update'
);

// ── SPECIAL CHARACTERS ───────────────────────────────────────────────────────
$t->section('SPECIAL CHARACTERS');

$rSpc = $table->InsertRecord(['name' => 'A&B <C> "D"', 'value' => '0', 'qty' => '1']);
$t->ok(is_array($rSpc), 'Insert record with &, <, > in name');

$spcBack = $table->GetRecordByPrimaryKey($rSpc['id']);
$t->eq('A&B <C> "D"', $spcBack['name'], 'Special chars stored and retrieved correctly via serialize');

// ── DELETE ───────────────────────────────────────────────────────────────────
$t->section('DELETE');

$del = $table->DelRecord('2');
$t->ok($del, 'DelRecord(2) returns true');

$t->eq(3, $table->GetNumRecords(), 'Record count = 3 after delete (had 4, deleted 1)');

$gone = $table->GetRecordByPrimaryKey('2');
$t->notOk($gone, 'Deleted record no longer retrievable');

$t->notOk(
    file_exists("$tmpdir/$db/$tbl/2.s.php"),
    'Serialized file 2.s.php removed from disk after delete'
);

// ── ORDER BY AND PAGINATION ──────────────────────────────────────────────────
$t->section('ORDER BY');

// Insert an extra record so we have 4 total with varied names
$r4 = $table->InsertRecord(['name' => 'Avocado', 'value' => '2.00', 'qty' => '3']);
$t->ok(is_array($r4), 'Insert Avocado for sorting tests');

// Still 4 records: Green Apple (id=1), Cherry (id=3), A&B... (id=4), Avocado (id=5)
$asc = $table->GetRecords(false, false, false, 'name');
$t->ok(is_array($asc),                   'ORDER BY name returns array');
$t->eq('A&B <C> "D"', $asc[0]['name'],   'ORDER BY name ASC: first = A&B (lowest ASCII)');
$t->eq('Avocado',      $asc[1]['name'],   'ORDER BY name ASC: second = Avocado');

$desc = $table->GetRecords(false, false, false, 'name', true);
$t->eq('Green Apple', $desc[0]['name'],   'ORDER BY name DESC: first = Green Apple');

$byValue = $table->GetRecords(false, false, false, 'value');
$t->ok(is_array($byValue),               'ORDER BY value returns array');
$t->eq('0', $byValue[0]['value'],        'ORDER BY value ASC: first has value=0');

$t->section('PAGINATION');

$page1 = $table->GetRecords(false, 1, 2, 'name');
$t->cnt(2, $page1, 'Pagination min=1 length=2 returns 2 records');

$page2 = $table->GetRecords(false, 3, 2, 'name');
$t->cnt(2, $page2, 'Pagination min=3 length=2 returns 2 records');

// The two pages together must cover all 4 records (no overlap, no gap)
$allNames = array_map(fn($r) => $r['name'], array_merge($page1, $page2));
$t->cnt(4, $allNames, 'Both pages together cover all 4 records');

// ── TRUNCATE ─────────────────────────────────────────────────────────────────
$t->section('TRUNCATE');

$table->Truncate();
$t->eq(0, $table->GetNumRecords(), 'GetNumRecords() = 0 after Truncate');

$rNew = $table->InsertRecord(['name' => 'Fresh', 'value' => '0', 'qty' => '1']);
$t->ok(is_array($rNew),           'Insert after Truncate succeeds');
$t->eq('1', (string)$rNew['id'], 'Autoincrement restarts at 1 after Truncate');

// ── Teardown ──────────────────────────────────────────────────────────────────
xmetadb_remove_dir_rec($tmpdir);

exit($t->summary('XMETATable_serialize') ? 0 : 1);
