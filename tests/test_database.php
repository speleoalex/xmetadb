<?php
/**
 * Unit tests – XMETADatabase SQL-like interface
 * Uses the xmlphp driver as the backend.
 * Run: php tests/test_database.php
 *
 * Known limitations/bugs documented by these tests:
 *   BUG-1: WHERE conversion applies `=` → `==` only to values matching
 *          [a-zA-Z0-9_'"]+  — values with spaces are handled by the subsequent
 *          field-level regex, but multi-word token before `=` (e.g. `first name`)
 *          would fail because the left-side group allows spaces.
 *   BUG-2: Numeric comparisons (>, <, >=, <=) convert the RHS to a quoted string,
 *          so `age > 9` becomes `$item['age'] > "9"`. For single-digit vs multi-digit
 *          values this is lexicographic ("9" > "10" = true), which is wrong.
 *   BUG-3: UPDATE with multi-value SET strips surrounding quotes from values via
 *          a plain explode(",") / explode("="), so values containing commas or
 *          equals signs are silently truncated.
 *   BUG-4: convertwhere() converts LIKE to == first, causing `LIKE '%val%'` to
 *          go through the equality path before the percent-pattern path picks it up.
 *          In practice the percent-pattern regexes fire after the equality conversion
 *          and the resulting eval string is valid, but the logic is fragile.
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../xmetadb/xmetadb_core.php';
require_once __DIR__ . '/../xmetadb/XMETADatabase.php';

$t = new TestRunner();

// ── Setup ────────────────────────────────────────────────────────────────────
$tmpdir = sys_get_temp_dir() . '/xmetadb_db_' . uniqid();
mkdir($tmpdir);
$db  = 'shop';
$tbl = 'products';

createxmldatabase($db, $tmpdir);
$fields = [
    ['name' => 'id',       'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'name',     'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'category', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'price',    'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'stock',    'primarykey' => '0', 'type' => 'int'],
];
createxmltable($db, $tbl, $fields, $tmpdir);

// Seed data via direct table API (not SQL, to isolate Insert tests later)
$seedTable = xmetadb_table($db, $tbl, $tmpdir);
$seedTable->InsertRecord(['name' => 'Apple',   'category' => 'fruit',  'price' => '1.50', 'stock' => '100']);
$seedTable->InsertRecord(['name' => 'Banana',  'category' => 'fruit',  'price' => '0.75', 'stock' => '200']);
$seedTable->InsertRecord(['name' => 'Carrot',  'category' => 'veggie', 'price' => '0.50', 'stock' => '150']);
$seedTable->InsertRecord(['name' => 'Daikon',  'category' => 'veggie', 'price' => '1.20', 'stock' => '80']);
$seedTable->InsertRecord(['name' => 'Eggplant','category' => 'veggie', 'price' => '2.00', 'stock' => '60']);

$sql = new XMETADatabase($db, $tmpdir);

echo "=== XMETADatabase SQL Interface Tests ===\n";

// ── SELECT * ──────────────────────────────────────────────────────────────────
$t->section('SELECT *');

$rows = $sql->Query("SELECT * FROM $tbl");
$t->ok(is_array($rows),  'SELECT * returns array');
$t->cnt(5, $rows,        'SELECT * returns all 5 rows');
$t->ok(isset($rows[0]['name']), 'Rows contain expected fields');

// ── SELECT specific fields ────────────────────────────────────────────────────
$t->section('SELECT fields');

$rows = $sql->Query("SELECT name, category FROM $tbl");
$t->cnt(5, $rows, 'SELECT name,category returns 5 rows');
$t->ok(isset($rows[0]['name']),                   'Field name is present');
$t->ok(isset($rows[0]['category']),               'Field category is present');
$t->ok(!isset($rows[0]['price']),                 'Field price is NOT present');

// ── WHERE equality ────────────────────────────────────────────────────────────
$t->section('WHERE =');

$rows = $sql->Query("SELECT * FROM $tbl WHERE category = 'fruit'");
$t->cnt(2, $rows, "WHERE category='fruit' returns 2 rows");
$t->eq('fruit', $rows[0]['category'], 'Returned rows have correct category');

$rows = $sql->Query("SELECT * FROM $tbl WHERE name = 'Carrot'");
$t->cnt(1, $rows, "WHERE name='Carrot' returns 1 row");
$t->eq('Carrot', $rows[0]['name'], 'Correct row returned');

$rows = $sql->Query("SELECT * FROM $tbl WHERE name = 'Zzzz'");
$t->cnt(0, $rows, "WHERE name='Zzzz' returns 0 rows (not found)");

// ── WHERE LIKE ────────────────────────────────────────────────────────────────
$t->section('WHERE LIKE');

$rows = $sql->Query("SELECT * FROM $tbl WHERE name LIKE '%an%'");
$t->cnt(2, $rows, "WHERE name LIKE '%an%' matches Banana and Daikon");

$rows = $sql->Query("SELECT * FROM $tbl WHERE name LIKE 'A%'");
$t->cnt(1, $rows, "WHERE name LIKE 'A%' matches Apple only");

$rows = $sql->Query("SELECT * FROM $tbl WHERE name LIKE '%ot'");
$t->cnt(1, $rows, "WHERE name LIKE '%ot' matches Carrot only");

// ── ORDER BY ──────────────────────────────────────────────────────────────────
$t->section('ORDER BY');

$rows = $sql->Query("SELECT * FROM $tbl ORDER BY name");
$t->cnt(5, $rows, 'ORDER BY name returns all 5 rows');
$t->eq('Apple',    $rows[0]['name'], 'ORDER BY name ASC: first = Apple');
$t->eq('Eggplant', $rows[4]['name'], 'ORDER BY name ASC: last = Eggplant');

$rows = $sql->Query("SELECT * FROM $tbl ORDER BY name DESC");
$t->eq('Eggplant', $rows[0]['name'], 'ORDER BY name DESC: first = Eggplant');
$t->eq('Apple',    $rows[4]['name'], 'ORDER BY name DESC: last = Apple');

// ── LIMIT ─────────────────────────────────────────────────────────────────────
$t->section('LIMIT');

$rows = $sql->Query("SELECT * FROM $tbl LIMIT 1,3");
$t->cnt(3, $rows, 'LIMIT 1,3 returns 3 rows');

$rows = $sql->Query("SELECT * FROM $tbl ORDER BY name LIMIT 1,2");
$t->cnt(2, $rows, 'ORDER BY + LIMIT 1,2 returns 2 rows');

// ── COUNT(*) ──────────────────────────────────────────────────────────────────
$t->section('COUNT(*)');

$rows = $sql->Query("SELECT COUNT(*) FROM $tbl");
$t->cnt(1, $rows, 'SELECT COUNT(*) returns array with 1 row');
$t->eq(5, (int)$rows[0]['COUNT(*)'], 'COUNT(*) = 5');

$rows = $sql->Query("SELECT COUNT(*) AS total FROM $tbl");
$t->ok(isset($rows[0]['total']), 'COUNT(*) AS total: alias used as key');
$t->eq(5, (int)$rows[0]['total'], 'COUNT(*) AS total = 5');

$rows = $sql->Query("SELECT COUNT(*) FROM $tbl WHERE category = 'veggie'");
$t->eq(3, (int)$rows[0]['COUNT(*)'], 'COUNT(*) WHERE category=veggie = 3');

// ── SELECT DISTINCT ───────────────────────────────────────────────────────────
$t->section('SELECT DISTINCT');

$rows = $sql->Query("SELECT DISTINCT category FROM $tbl");
$t->cnt(2, $rows, 'SELECT DISTINCT category returns 2 unique categories');

// ── INSERT ────────────────────────────────────────────────────────────────────
$t->section('INSERT');

$res = $sql->Query("INSERT INTO $tbl (name, category, price, stock) VALUES ('Fig', 'fruit', '3.00', '40')");
$t->ok(is_array($res), 'INSERT returns array of inserted record');

$check = $sql->Query("SELECT * FROM $tbl WHERE name = 'Fig'");
$t->cnt(1, $check, 'Inserted record retrievable via SELECT');
$t->eq('Fig', $check[0]['name'], 'Inserted record has correct name');
$t->eq('3.00', $check[0]['price'], 'Inserted record has correct price');

// INSERT with value containing comma inside quotes
$res2 = $sql->Query("INSERT INTO $tbl (name, category, price, stock) VALUES ('Grape, red', 'fruit', '2.50', '70')");
$t->ok(is_array($res2), "INSERT with comma inside quoted value succeeds");
$grape = $sql->Query("SELECT * FROM $tbl WHERE name = 'Grape, red'");
$t->cnt(1, $grape, "SELECT finds the comma-containing name");
$t->eq('Grape, red', $grape[0]['name'], "Comma inside value stored correctly");

// ── DELETE ────────────────────────────────────────────────────────────────────
$t->section('DELETE');

$sql->Query("DELETE FROM $tbl WHERE name = 'Fig'");
$after = $sql->Query("SELECT * FROM $tbl WHERE name = 'Fig'");
$t->cnt(0, $after, 'DELETE removes the record');

$all = $sql->Query("SELECT COUNT(*) FROM $tbl");
$t->eq(6, (int)$all[0]['COUNT(*)'], 'Total count = 6 after one delete (7 - 1)');

// ── UPDATE ────────────────────────────────────────────────────────────────────
$t->section('UPDATE');

$sql->Query("UPDATE $tbl SET price = '0.99' WHERE name = 'Carrot'");
$after = $sql->Query("SELECT * FROM $tbl WHERE name = 'Carrot'");
$t->cnt(1, $after, 'UPDATE: record still exists after update');
$t->eq('0.99', $after[0]['price'], 'UPDATE: price changed to 0.99');

// ── DESCRIBE ─────────────────────────────────────────────────────────────────
$t->section('DESCRIBE');

$desc = $sql->Query("DESCRIBE $tbl");
$t->ok(is_array($desc), 'DESCRIBE returns array');
$t->cnt(5, $desc, 'DESCRIBE returns 5 field descriptors');

$fieldNames = array_column($desc, 'Field');
$t->ok(in_array('id',       $fieldNames), 'DESCRIBE includes field id');
$t->ok(in_array('name',     $fieldNames), 'DESCRIBE includes field name');
$t->ok(in_array('category', $fieldNames), 'DESCRIBE includes field category');
$t->ok(in_array('price',    $fieldNames), 'DESCRIBE includes field price');

// ── SHOW TABLES ───────────────────────────────────────────────────────────────
$t->section('SHOW TABLES');

$tables = $sql->Query("SHOW TABLES");
$t->ok(is_array($tables), 'SHOW TABLES returns array');
$key = 'Tables_in_' . $db;
$tableNames = array_column($tables, $key);
$t->ok(in_array($tbl, $tableNames), "SHOW TABLES includes '{$tbl}'");

// ── NUMERIC COMPARISON (fixed) ───────────────────────────────────────────────
$t->section('NUMERIC > comparison (fixed)');

// After the fix, unquoted numeric RHS values use PHP numeric comparison,
// so "9" > 10 correctly evaluates as 9 > 10 = false.
// stock values: Apple=100, Banana=200, Carrot=150(updated->0.99 price, stock still 150),
// Daikon=80, Eggplant=60, Grape=70 (Grape was not deleted), 'Grape, red'=70
// Records with stock > 100: Banana(200), Carrot(150) = 2 records
$rows = $sql->Query("SELECT * FROM $tbl WHERE stock > 100");
$t->ok(is_array($rows), 'WHERE stock > 100 returns array');
// Banana (200) and Carrot (150) have stock > 100; Daikon=80, Eggplant=60 do not
foreach ((array)$rows as $row) {
    $t->ok((int)$row['stock'] > 100,
        "Record {$row['name']} has stock={$row['stock']} > 100 (numeric comparison correct)");
}

// The classic single-digit bug: "9" > "10" is true lexicographically but 9 > 10 is false.
// We verify the fix: a record with stock=60 must NOT match stock > 100.
$low = $sql->Query("SELECT * FROM $tbl WHERE stock > 100");
$names = array_column((array)$low, 'name');
$t->notOk(in_array('Eggplant', $names), 'Eggplant (stock=60) NOT returned by stock > 100 (numeric fix)');

// ── WHERE AND ────────────────────────────────────────────────────────────────
$t->section('WHERE AND');

// State: Apple(fruit,100), Banana(fruit,200), Carrot(veggie,150),
//        Daikon(veggie,80), Eggplant(veggie,60), Grape red(fruit,70)
$rows = $sql->Query("SELECT * FROM $tbl WHERE category = 'veggie' AND stock > 100");
$t->ok(is_array($rows), 'WHERE category=veggie AND stock>100 returns array');
// Only Carrot qualifies (stock=150); Daikon=80 and Eggplant=60 do not
$t->cnt(1, $rows, 'WHERE AND: exactly 1 record matches');
$t->eq('Carrot', $rows[0]['name'], 'WHERE AND: matched record is Carrot');

$rows = $sql->Query("SELECT * FROM $tbl WHERE category = 'fruit' AND stock > 100");
// Apple=100 (not > 100), Banana=200 ✓, Grape red=70 ✗ → 1 match
$t->cnt(1, $rows, 'WHERE fruit AND stock>100 returns 1 record (Banana)');
$t->eq('Banana', $rows[0]['name'], 'WHERE AND: matched record is Banana (stock=200)');

// ── WHERE OR ─────────────────────────────────────────────────────────────────
$t->section('WHERE OR');

$rows = $sql->Query("SELECT * FROM $tbl WHERE stock > 150 OR stock < 70");
$t->ok(is_array($rows), 'WHERE stock>150 OR stock<70 returns array');
// Banana=200 ✓, Eggplant=60 ✓ → 2 matches
$t->cnt(2, $rows, 'WHERE OR: 2 records match (Banana and Eggplant)');
$names = array_column($rows, 'name');
$t->ok(in_array('Banana',   $names), 'WHERE OR: Banana (200) included');
$t->ok(in_array('Eggplant', $names), 'WHERE OR: Eggplant (60) included');

// ── WHERE <> (not equal) ─────────────────────────────────────────────────────
$t->section('WHERE <> (not equal)');

$rows = $sql->Query("SELECT * FROM $tbl WHERE stock <> 100");
$t->ok(is_array($rows), 'WHERE stock <> 100 returns array');
// Apple has stock=100, all others differ → 5 records
$t->cnt(5, $rows, 'WHERE stock<>100 returns 5 records (all except Apple)');
$names = array_column($rows, 'name');
$t->notOk(in_array('Apple', $names), 'WHERE <> excludes Apple (stock=100)');

$rows = $sql->Query("SELECT * FROM $tbl WHERE category <> 'fruit'");
$t->ok(is_array($rows), 'WHERE category <> fruit returns array');
// 3 veggies: Carrot, Daikon, Eggplant
$t->cnt(3, $rows, 'WHERE category<>fruit returns 3 veggie records');

// ── Teardown ──────────────────────────────────────────────────────────────────
xmetadb_remove_dir_rec($tmpdir);

exit($t->summary('XMETADatabase') ? 0 : 1);
