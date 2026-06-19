<?php
/**
 * Test file/image field types — upload, storage, retrieval, cleanup.
 * Run: php tests/test_files.php
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../xmetadb/xmetadb_core.php';

$t = new TestRunner();

$tmpdir = sys_get_temp_dir() . '/xmetadb_files_' . uniqid();
mkdir($tmpdir);
$db  = 'testdb';
$tbl = 'documents';

createxmldatabase($db, $tmpdir);

$srcDir = $tmpdir . '/src';
mkdir($srcDir);
file_put_contents("$srcDir/report.pdf", "fake pdf content");
file_put_contents("$srcDir/photo.jpg",  "fake jpeg data");

$fields = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'title', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'doc',   'primarykey' => '0', 'type' => 'file'],
    ['name' => 'img',   'primarykey' => '0', 'type' => 'image'],
];
createxmltable($db, $tbl, $fields, $tmpdir);
$table = xmetadb_table($db, $tbl, $tmpdir);

echo "=== File/Image Field Tests ===\n";

$t->section('INSERT WITH FILE');

$table->SetFile('doc', "$srcDir/report.pdf", 'report.pdf');
$table->SetFile('img', "$srcDir/photo.jpg",  'photo.jpg');
$r1 = $table->InsertRecord([
    'title' => 'My Report',
    'doc'   => 'report.pdf',
    'img'   => 'photo.jpg',
]);
$t->ok(is_array($r1), 'Insert record with file/image fields returns array');
$t->eq('1', (string)$r1['id'], 'Autoincrement: first id = 1');
$t->eq('report.pdf', $r1['doc'], 'Filename stored in doc field');
$t->eq('photo.jpg',  $r1['img'], 'Filename stored in img field');

$t->section('FILES ON DISK');

$docPath = $table->getFilePath($r1, 'doc');
$imgPath = $table->getFilePath($r1, 'img');
$t->ok($docPath !== false, 'getFilePath(doc) returns a path');
$t->ok($imgPath !== false, 'getFilePath(img) returns a path');
$t->ok(file_exists($docPath), 'Document file exists on disk');
$t->ok(file_exists($imgPath), 'Image file exists on disk');
$t->eq('fake pdf content', file_get_contents($docPath), 'Document content preserved');
$t->eq('fake jpeg data',   file_get_contents($imgPath), 'Image content preserved');

$t->section('RECORD RETRIEVAL');

$rec = $table->GetRecordByPrimaryKey('1');
$t->ok(is_array($rec), 'GetRecordByPrimaryKey returns record');
$t->eq('report.pdf', $rec['doc'], 'Record retains filename');
$t->eq('photo.jpg',  $rec['img'], 'Record retains image name');

$t->section('UPDATE REPLACE FILE');

file_put_contents("$srcDir/report-v2.pdf", "updated content");
$table->SetFile('doc', "$srcDir/report-v2.pdf", 'report-v2.pdf');
$upd = $table->UpdateRecord([
    'id'    => '1',
    'title' => 'My Report v2',
    'doc'   => 'report-v2.pdf',
]);
$t->ok(is_array($upd), 'UpdateRecord after file replacement returns array');

$newDocPath = $table->getFilePath($upd, 'doc');
$t->ok($newDocPath !== false, 'getFilePath returns path after update');
$t->ok(file_exists($newDocPath), 'New file exists on disk');
$t->eq('updated content', file_get_contents($newDocPath), 'New file content correct');
$t->notOk(file_exists($docPath), 'Old file removed from disk after replacement');

$t->section('DELETE CLEANUP');

$del = $table->DelRecord('1');
$t->ok($del, 'DelRecord returns true');
$t->notOk(file_exists($newDocPath), 'Document file removed after record delete');
$t->notOk(file_exists($imgPath), 'Image file removed after record delete');

$t->section('INSERT MULTIPLE FILES');

$table->SetFile('doc', "$srcDir/report.pdf", 'report.pdf');
$r2 = $table->InsertRecord([
    'title' => 'Second Doc',
    'doc'   => 'report.pdf',
]);
$t->ok(is_array($r2), 'Second insert with file returns array');
$docPath2 = $table->getFilePath($r2, 'doc');
$t->ok($docPath2 !== false, 'getFilePath works for second record');
$t->ok(file_exists($docPath2), 'Second record file exists on disk');

$table->Truncate();
$t->eq(0, $table->GetNumRecords(), 'After Truncate, record count is 0');
$t->notOk(file_exists(dirname($docPath2)), 'Record directory removed after Truncate');

// ── SINGLE-FILE STORAGE MODE ─────────────────────────────────────────────────
// Same file/image operations but with a descriptor that stores all record XML
// in one file (singlefilename option) instead of one file per record.

$t->section('SINGLE-FILE STORAGE — SETUP');

$db2  = 'testdb2';
$tbl2 = 'docs_sf';

createxmldatabase($db2, $tmpdir);
$fields2 = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'title', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'doc',   'primarykey' => '0', 'type' => 'file'],
    ['name' => 'img',   'primarykey' => '0', 'type' => 'image'],
];
// 'allrecords' (no .php) → descriptor <filename>allrecords</filename>
// → all record XML goes to {table_dir}/allrecords.php
createxmltable($db2, $tbl2, $fields2, $tmpdir, 'allrecords');
$sfTable = xmetadb_table($db2, $tbl2, $tmpdir);
$t->ok(is_object($sfTable), 'Single-file table created and loaded');

$singleFile = "$tmpdir/$db2/$tbl2/allrecords.php";

$t->section('SINGLE-FILE: INSERT WITH FILES');

$sfTable->SetFile('doc', "$srcDir/report.pdf", 'report.pdf');
$sfTable->SetFile('img', "$srcDir/photo.jpg",  'photo.jpg');
$sf1 = $sfTable->InsertRecord([
    'title' => 'SF Report 1',
    'doc'   => 'report.pdf',
    'img'   => 'photo.jpg',
]);
$t->ok(is_array($sf1),            'Single-file: InsertRecord returns array');
$t->eq('1', (string)$sf1['id'],   'Single-file: first record id = 1');
$t->ok(file_exists($singleFile),  'Single-file: all records written to allrecords.php');

$sfTable->SetFile('doc', "$srcDir/report.pdf", 'report2.pdf');
$sf2 = $sfTable->InsertRecord([
    'title' => 'SF Report 2',
    'doc'   => 'report2.pdf',
]);
$t->eq('2', (string)$sf2['id'],   'Single-file: second record id = 2');
// Both records must still be in the same single file (not a new file per record)
$t->ok(file_exists($singleFile),  'Single-file: still one file after second insert');
$t->notOk(file_exists("$tmpdir/$db2/$tbl2/1.php"), 'Single-file: no per-record 1.php file created');
$t->notOk(file_exists("$tmpdir/$db2/$tbl2/2.php"), 'Single-file: no per-record 2.php file created');

$t->section('SINGLE-FILE: GET RECORDS');

$sfAll = $sfTable->GetRecords();
$t->cnt(2, $sfAll, 'Single-file: GetRecords returns both records');

$sfRec1 = $sfTable->GetRecordByPrimaryKey('1');
$t->ok(is_array($sfRec1),               'Single-file: GetRecordByPrimaryKey(1) returns array');
$t->eq('SF Report 1', $sfRec1['title'], 'Single-file: record 1 has correct title');

$sfRec2 = $sfTable->GetRecordByPrimaryKey('2');
$t->ok(is_array($sfRec2),               'Single-file: GetRecordByPrimaryKey(2) returns array');
$t->eq('SF Report 2', $sfRec2['title'], 'Single-file: record 2 has correct title');

$t->section('SINGLE-FILE: FILES ON DISK');

$sfDoc1 = $sfTable->getFilePath($sf1, 'doc');
$sfImg1 = $sfTable->getFilePath($sf1, 'img');
$sfDoc2 = $sfTable->getFilePath($sf2, 'doc');
$t->ok($sfDoc1 !== false,                          'Single-file: getFilePath(doc) returns a path');
$t->ok(file_exists($sfDoc1),                       'Single-file: doc file exists on disk');
$t->ok(file_exists($sfImg1),                       'Single-file: img file exists on disk');
$t->ok(file_exists($sfDoc2),                       'Single-file: second record doc exists on disk');
$t->eq('fake pdf content', file_get_contents($sfDoc1), 'Single-file: doc content preserved');

$t->section('SINGLE-FILE: UPDATE');

file_put_contents("$srcDir/report-v2.pdf", "updated v2 content");
$sfTable->SetFile('doc', "$srcDir/report-v2.pdf", 'report-v2.pdf');
$sfUpd = $sfTable->UpdateRecord([
    'id'    => '1',
    'title' => 'SF Report 1 Updated',
    'doc'   => 'report-v2.pdf',
]);
$t->ok(is_array($sfUpd), 'Single-file: UpdateRecord returns array');
$sfNewDoc1 = $sfTable->getFilePath($sfUpd, 'doc');
$t->ok(file_exists($sfNewDoc1),                          'Single-file: updated file exists on disk');
$t->eq('updated v2 content', file_get_contents($sfNewDoc1), 'Single-file: updated content correct');
$t->notOk(file_exists($sfDoc1),                          'Single-file: old file removed after update');

$sfChk = $sfTable->GetRecordByPrimaryKey('1');
$t->eq('SF Report 1 Updated', $sfChk['title'], 'Single-file: updated title persisted');

$t->section('SINGLE-FILE: DELETE');

$sfDel = $sfTable->DelRecord('1');
$t->ok($sfDel, 'Single-file: DelRecord(1) returns true');
$t->eq(1, $sfTable->GetNumRecords(), 'Single-file: record count = 1 after delete');
$t->notOk($sfTable->GetRecordByPrimaryKey('1'), 'Single-file: deleted record not retrievable');
$t->notOk(file_exists($sfNewDoc1), 'Single-file: doc file removed after delete');
$t->ok(file_exists($sfDoc2),       'Single-file: other record file still present');

$t->section('SINGLE-FILE: TRUNCATE');

$sfTable->Truncate();
$t->eq(0, $sfTable->GetNumRecords(), 'Single-file: GetNumRecords = 0 after Truncate');

$sfFresh = $sfTable->InsertRecord(['title' => 'After Truncate']);
$t->ok(is_array($sfFresh),            'Single-file: Insert after Truncate succeeds');
$t->eq('1', (string)$sfFresh['id'],   'Single-file: autoincrement restarts at 1 after Truncate');
$t->eq(1, $sfTable->GetNumRecords(),  'Single-file: record count = 1 after Truncate + insert');

xmetadb_remove_dir_rec($tmpdir);
exit($t->summary('File/Image Fields') ? 0 : 1);
