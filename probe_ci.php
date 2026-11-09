<?php

// Probe A: database Postgres dengan ICU nondeterministic sebagai collation default.

$admin = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', '');
$admin->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$admin->exec('drop database if exists ci_probe');

try {
    $admin->exec("create database ci_probe locale_provider = icu icu_locale = 'und-u-ks-level2' encoding 'UTF8' template template0");
} catch (Exception $e) {
    echo "create FAIL: ".explode("\n", $e->getMessage())[0]."\n";
    exit(1);
}

$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=ci_probe', 'postgres', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo 'db created. datlocale='.$pdo->query("select datlocale from pg_database where datname = current_database()")->fetchColumn()."\n";

$pdo->exec('create table probe_t (id bigint primary key, name text, email varchar(255) unique, meta json)');
$pdo->exec("insert into probe_t values (1,'Pelaminan Royal','A@Mail.COM','{\"a\":1}'::json)");

$checks = [
    'LIKE ci            ' => "select count(*) from probe_t where name like '%pelaminan%'",
    'eq ci              ' => "select count(*) from probe_t where name = 'pelaminan royal'",
    'unique eq ci       ' => "select count(*) from probe_t where email = 'a@mail.com'",
    'ILIKE              ' => "select count(*) from probe_t where name ilike '%PELAMINAN%'",
    'order by text      ' => "select string_agg(name, ',' order by name) from probe_t",
    'json ->>           ' => "select meta->>'a' from probe_t where id = 1",
];

foreach ($checks as $label => $sql) {
    try {
        echo $label.': '.$pdo->query($sql)->fetchColumn()."\n";
    } catch (Exception $e) {
        echo $label.': FAIL '.explode("\n", $e->getMessage())[0]."\n";
    }
}

try {
    $pdo->exec("insert into probe_t values (2,'BUKET','a@mail.com','{}'::json)");
    echo "dup email (beda kapital)     : DITERIMA  <-- bahaya kalau expecting case-sensitive\n";
} catch (Exception $e) {
    echo "dup email (beda kapital)     : ditolak (case-insensitive unique, sama seperti MySQL)\n";
}

$pdo->exec('create index probe_name_idx on probe_t (name)');
echo "btree index on ci column       : ok\n";

$pdo = null;
$admin->exec('drop database ci_probe');
echo "probe dibersihkan\n";
