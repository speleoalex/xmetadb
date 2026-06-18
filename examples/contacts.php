<?php
/**
 * xmetadb example — Contact book
 *
 * Demonstrates full CRUD, filtering, pagination, ordering,
 * and the SQL-like query interface using the xmlphp driver.
 *
 * Run from CLI:  php examples/contacts.php
 * No server or extension required beyond PHP 7.2+.
 */

require_once __DIR__ . '/../xmetadb/xmetadb_core.php';
require_once __DIR__ . '/../xmetadb/XMETADatabase.php';

// ---------------------------------------------------------------------------
// Setup — database and table
// ---------------------------------------------------------------------------

$db_path = __DIR__ . '/data';
$db_name = 'addressbook';
$tbl_name = 'contacts';

// Create database directory if it does not exist
if (!xmldatabaseexists($db_name, $db_path)) {
    $err = createxmldatabase($db_name, $db_path);
    if ($err) {
        die("Could not create database: $err\n");
    }
    echo "Database '$db_name' created.\n";
}

// Create table if it does not exist
if (!xmltableexists($db_name, $tbl_name, $db_path)) {
    $fields = [
        ['name' => 'id',      'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
        ['name' => 'name',    'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
        ['name' => 'email',   'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
        ['name' => 'phone',   'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
        ['name' => 'city',    'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
        ['name' => 'active',  'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => '1'],
    ];
    $err = createxmltable($db_name, $tbl_name, $fields, $db_path);
    if ($err) {
        die("Could not create table: $err\n");
    }
    echo "Table '$tbl_name' created.\n";
}

$contacts = xmetadb_table($db_name, $tbl_name, $db_path);

// ---------------------------------------------------------------------------
// INSERT — add sample contacts
// ---------------------------------------------------------------------------

echo "\n--- INSERT ---\n";

// Start fresh for this demo
$contacts->Truncate();

$sample = [
    ['name' => 'Alice Rossi',    'email' => 'alice@example.com',  'phone' => '555-0101', 'city' => 'Milan',  'active' => '1'],
    ['name' => 'Bob Bianchi',    'email' => 'bob@example.com',    'phone' => '555-0102', 'city' => 'Rome',   'active' => '1'],
    ['name' => 'Carla Verdi',    'email' => 'carla@example.com',  'phone' => '555-0103', 'city' => 'Milan',  'active' => '1'],
    ['name' => 'David Neri',     'email' => 'david@example.com',  'phone' => '555-0104', 'city' => 'Naples', 'active' => '0'],
    ['name' => 'Elena Ferrari',  'email' => 'elena@example.com',  'phone' => '555-0105', 'city' => 'Turin',  'active' => '1'],
];

foreach ($sample as $row) {
    $r = $contacts->InsertRecord($row);
    if (is_array($r)) {
        echo "  Inserted: [{$r['id']}] {$r['name']} <{$r['email']}>\n";
    } else {
        echo "  Error: $r\n";
    }
}

// ---------------------------------------------------------------------------
// READ ALL
// ---------------------------------------------------------------------------

echo "\n--- GET ALL ({$contacts->GetNumRecords()} records) ---\n";

foreach ($contacts->GetRecords() as $row) {
    $status = $row['active'] === '1' ? 'active' : 'inactive';
    echo "  [{$row['id']}] {$row['name']} — {$row['city']} — $status\n";
}

// ---------------------------------------------------------------------------
// FILTER — exact match and LIKE wildcard
// ---------------------------------------------------------------------------

echo "\n--- FILTER: city = Milan ---\n";

$milan = $contacts->GetRecords(['city' => 'Milan']);
foreach ($milan as $row) {
    echo "  {$row['name']}\n";
}

echo "\n--- FILTER: name contains 'a' (LIKE %a%) ---\n";

$contains_a = $contacts->GetRecords(['name' => '%a%']);
foreach ($contains_a as $row) {
    echo "  {$row['name']}\n";
}

echo "\n--- FILTER: active only, ordered by name ---\n";

$active = $contacts->GetRecords(['active' => '1'], false, false, 'name');
foreach ($active as $row) {
    echo "  {$row['name']}\n";
}

// ---------------------------------------------------------------------------
// PAGINATION
// ---------------------------------------------------------------------------

echo "\n--- PAGINATION: page 1 (2 per page), ordered by name ---\n";

$page1 = $contacts->GetRecords(false, 1, 2, 'name');
foreach ($page1 as $row) {
    echo "  {$row['name']}\n";
}

echo "\n--- PAGINATION: page 2 ---\n";

$page2 = $contacts->GetRecords(false, 3, 2, 'name');
foreach ($page2 as $row) {
    echo "  {$row['name']}\n";
}

// ---------------------------------------------------------------------------
// GET SINGLE RECORD
// ---------------------------------------------------------------------------

echo "\n--- GET SINGLE RECORD (by primary key) ---\n";

$alice = $contacts->GetRecordByPrimaryKey('1');
echo "  Found: {$alice['name']} — {$alice['email']}\n";

echo "\n--- GET SINGLE RECORD (by filter) ---\n";

$bob = $contacts->GetRecord(['email' => 'bob@example.com']);
echo "  Found: {$bob['name']} in {$bob['city']}\n";

// ---------------------------------------------------------------------------
// UPDATE
// ---------------------------------------------------------------------------

echo "\n--- UPDATE: deactivate Alice, change her city ---\n";

$contacts->UpdateRecord(['id' => '1', 'city' => 'Florence', 'active' => '0']);
$alice_updated = $contacts->GetRecordByPrimaryKey('1');
echo "  Alice now: city={$alice_updated['city']}, active={$alice_updated['active']}\n";

// ---------------------------------------------------------------------------
// DELETE
// ---------------------------------------------------------------------------

echo "\n--- DELETE: remove David (id=4) ---\n";

$contacts->DelRecord('4');
echo "  Records remaining: {$contacts->GetNumRecords()}\n";

// ---------------------------------------------------------------------------
// SQL-LIKE QUERY INTERFACE (XMETADatabase)
// ---------------------------------------------------------------------------

echo "\n--- SQL QUERIES (XMETADatabase) ---\n";

$db = new XMETADatabase($db_name, $db_path);

// SELECT with WHERE and ORDER BY
$rows = $db->Query("SELECT id, name, city FROM $tbl_name WHERE active = '1' ORDER BY name");
echo "  Active contacts (SQL SELECT):\n";
if (is_array($rows)) {
    foreach ($rows as $row) {
        echo "    [{$row['id']}] {$row['name']} — {$row['city']}\n";
    }
}

// COUNT
$count = $db->Query("SELECT COUNT(*) AS total FROM $tbl_name WHERE active = '1'");
echo "  Active count: {$count[0]['total']}\n";

// DESCRIBE
$cols = $db->Query("DESCRIBE $tbl_name");
echo "  Table columns: " . implode(', ', array_column($cols, 'Field')) . "\n";

// SHOW TABLES
$tables = $db->Query("SHOW TABLES");
echo "  Tables in '$db_name': " . implode(', ', array_column($tables, "Tables_in_$db_name")) . "\n";

// ---------------------------------------------------------------------------
// ADD A NEW FIELD at runtime
// ---------------------------------------------------------------------------

echo "\n--- ADD FIELD: 'notes' (varchar) ---\n";

if (!getxmltablefield($db_name, $tbl_name, 'notes', $db_path)) {
    addxmltablefield($db_name, $tbl_name, [
        'name' => 'notes',
        'type' => 'varchar',
        'defaultvalue' => '',
    ], $db_path);
    echo "  Field 'notes' added.\n";

    // Re-load the table handle to pick up the new field
    $contacts = xmetadb_table($db_name, $tbl_name, $db_path, ['_reload' => uniqid()]);
    $contacts->UpdateRecord(['id' => '2', 'notes' => 'Met at conference 2024']);
    $bob2 = $contacts->GetRecordByPrimaryKey('2');
    echo "  Bob notes: {$bob2['notes']}\n";
}

echo "\nDone.\n";
