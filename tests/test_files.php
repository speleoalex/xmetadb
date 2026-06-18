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

xmetadb_remove_dir_rec($tmpdir);
exit($t->summary('File/Image Fields') ? 0 : 1);
