<?php
// tools/seed-demo.php – Demo-Daten für die lokale Entwicklung (NICHT auf einer echten Installation ausführen!).
// Aufruf:  php tools/seed-demo.php            (nur mit APP_ENV=development/local/test in der .env)
// Löscht ALLE Daten in teilnehmer, disziplinen, resultate und legt den Benutzer «demo» an
// (Passwort aus DEMO_PASSWORD in der .env, sonst «demo-passwort»). Alle Namen sind erfunden.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config.php';
requireDevelopmentEnvironment();

mt_srand(20261123);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['resultate', 'teilnehmer', 'disziplinen'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// Disziplinen: name, aktiv, hoch_ist_besser, einheit_winter, einheit_sommer, [winter-Generator, sommer-Generator]
$disc = [
    ['Weitsprung', 1, 1, 'Stufe', 'm'],
    ['Sprint', 1, 1, 'Stufe', ''],
    ['Hindernislauf', 1, 0, 's', ''],
    ['Schleuderball', 1, 1, 'm', ''],
    ['Ballweitwurf', 1, 1, 'm', ''],
    ['Seilspringen', 1, 1, 'Sprünge', ''],
    ['Kugelstossen', 1, 1, 'm', ''],
    ['Beugehang', 1, 1, 's', ''],
    ['Biathlon', 1, 1, 'Punkte', ''],
    ['60m Sprint', 0, 0, 's', ''],
    ['Balancieren', 0, 1, '', ''],
    ['Klettern', 0, 1, '', ''],
    ['Geschicklichkeitschallenge', 0, 0, 's', ''],
    ['10min Lauf', 0, 1, 'm', ''],
];
$st = $pdo->prepare('INSERT INTO disziplinen (name, aktiv, hoch_ist_besser, einheit_winter, einheit_sommer) VALUES (?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'))');
foreach ($disc as $d) {
    $st->execute($d);
}

$first = ['m' => ['Noah', 'Luca', 'Elias', 'Levin', 'Jonas', 'Nils', 'Tim', 'Finn', 'Matteo', 'Samuel', 'Leon', 'Jan', 'Ben', 'Liam', 'Paul', 'Louis', 'David', 'Nico', 'Robin', 'Aaron', 'Lars', 'Yannick', 'Dario', 'Kevin', 'Luca'],
          'w' => ['Lina', 'Mia', 'Elin', 'Nora', 'Zoe', 'Lea', 'Sara', 'Alina', 'Emma', 'Lia', 'Ella', 'Mila', 'Anna', 'Julia', 'Laura', 'Selina', 'Chiara', 'Nina', 'Lena', 'Sophie', 'Ava']];
$last = ['Aebi', 'Bärtschi', 'Christen', 'Dubach', 'Egger', 'Flückiger', 'Gerber', 'Häfliger', 'Ingold', 'Jost', 'Käser', 'Lanz', 'Marti', 'Neuenschwander', 'Oppliger', 'Pfister', 'Ryser', 'Schär', 'Tschanz', 'Wyss', 'Zaugg', 'Ammann', 'Brand', 'Cattaneo', 'Dietrich', 'Eggimann', 'Fankhauser', 'Graber', 'Hofer', 'Iseli', 'Kohler', 'Lehmann', 'Moser', 'Nyffeler', 'Probst', 'Rüfenacht', 'Stalder', 'Thomet', 'Uebersax', 'Vogel', 'Widmer', 'Zurflüh', 'Müller', 'Meier', 'Schneider'];
$days = [['montag'], ['dienstag'], ['freitag'], ['montag'], ['freitag'], ['montag', 'freitag']];
$kids = [];
$stk = $pdo->prepare('INSERT INTO teilnehmer (vorname, nachname, geschlecht, jahrgang, montag, dienstag, mittwoch, donnerstag, freitag) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
for ($i = 0; $i < 48; $i++) {
    $g = $i % 2 ? 'w' : 'm';
    $vn = $first[$g][$i % count($first[$g])];
    $nn = $last[$i % count($last)];
    $jg = 2011 + ($i * 7) % 10; // 2011–2020
    $dd = $i < 44 ? $days[$i % count($days)] : []; // 4 ausgetretene Kinder (kein Trainingstag)
    $stk->execute([$vn, $nn, $g, $jg, (int)in_array('montag', $dd), (int)in_array('dienstag', $dd), 0, 0, (int)in_array('freitag', $dd)]);
    $kids[] = ['id' => (int)$pdo->lastInsertId(), 'name' => "$vn $nn", 'age' => 2027 - $jg, 'g' => $g, 'talent' => mt_rand(-50, 50) / 100];
}
// Doppelter Vorname mit anderem Nachnamen (Suche testen): «Luca» kommt zweimal vor.

function value(string $disc, string $season, array $k): ?float {
    $a = $k['age']; $t = $k['talent']; $r = mt_rand(-30, 30) / 100;
    switch ($disc . '|' . $season) {
        case 'Weitsprung|w': return max(0.5, min(9, round((1 + ($a - 6) * 0.55 + $t * 2 + $r) * 2) / 2));
        case 'Weitsprung|s': return round(1.4 + ($a - 6) * 0.28 + $t * 0.6 + $r * 0.3, 2);
        case 'Sprint|w': return max(0.5, min(6, round((1 + ($a - 6) * 0.4 + $t * 1.5 + $r) * 2) / 2));
        case 'Hindernislauf|w': case 'Hindernislauf|s': return round(max(30, 120 - ($a - 6) * 7 - $t * 25 + $r * 10), 1);
        case 'Schleuderball|w': case 'Schleuderball|s': return round(max(2.3, 5 + ($a - 6) * 2.4 + $t * 5 + $r * 2), 1);
        case 'Ballweitwurf|w': case 'Ballweitwurf|s': return round(max(3, 8 + ($a - 6) * 2 + $t * 5 + $r * 2), 1);
        case 'Seilspringen|w': case 'Seilspringen|s': return (float)max(0, round(40 + ($a - 6) * 14 + $t * 50 + $r * 20));
        case 'Kugelstossen|w': case 'Kugelstossen|s': return round(max(1, 2.5 + ($a - 6) * 0.6 + $t * 1.5 + $r), 2);
        case 'Beugehang|w': return (float)max(1, round(8 + ($a - 6) * 4 + $t * 20 + $r * 5));
        case 'Biathlon|w': return (float)max(9, round(16 + ($a - 6) * 1.5 + $t * 6 + $r * 3));
        case '60m Sprint|s': return round(max(8.7, 15 - ($a - 6) * 0.6 - $t * 1.5 + $r * 0.4), 2);
        case 'Balancieren|s': return max(0.5, round((3 + ($a - 6) * 0.9 + $t * 3 + $r) * 2) / 2);
    }

    return null;
}

// Semester: WS 2025/26 (alt), SoSe 2026, WS 2026/27 (laufend, bis gestern + ein paar Einträge von heute)
$semesters = [
    ['w', '2025-10-06', '2026-03-30', ['Weitsprung', 'Sprint', 'Hindernislauf', 'Schleuderball', 'Ballweitwurf', 'Seilspringen', 'Kugelstossen', 'Beugehang', 'Biathlon']],
    ['s', '2026-04-20', '2026-09-21', ['Weitsprung', '60m Sprint', 'Hindernislauf', 'Schleuderball', 'Ballweitwurf', 'Seilspringen', 'Kugelstossen', 'Balancieren']],
    ['w', '2026-10-12', date('Y-m-d', strtotime('-1 day')), ['Weitsprung', 'Sprint', 'Hindernislauf', 'Schleuderball', 'Seilspringen', 'Kugelstossen', 'Biathlon']],
];
$str = $pdo->prepare('INSERT INTO resultate (teilnehmer_id, name, disziplin, resultat, pruefer, datum) VALUES (?, ?, ?, ?, ?, ?)');
$n = 0;
foreach ($semesters as [$season, $from, $to, $list]) {
    $span = max(1, (strtotime($to) - strtotime($from)) / 86400);
    foreach ($kids as $k) {
        if ($k['age'] < 6 || $k['age'] > 17) {
            continue;
        }
        foreach ($list as $d) {
            if (mt_rand(1, 100) <= 14) {
                continue; // fehlt
            }
            $tries = mt_rand(1, 2);
            for ($i = 0; $i < $tries; $i++) {
                $v = value($d, $season, $k);
                if ($v === null) {
                    continue;
                }
                $date = date('Y-m-d', (int)(strtotime($from) + mt_rand(0, (int)$span) * 86400));
                $str->execute([$k['id'], $k['name'], $d, $v, mt_rand(0, 1) ? 'demo' : 'leiterin', $date]);
                $n++;
            }
        }
    }
}

$pw = trim((string)getenv('DEMO_PASSWORD')) ?: 'demo-passwort';
$pdo->prepare('DELETE FROM users WHERE username = ?')->execute(['demo']);
$pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)')->execute(['demo', password_hash($pw, PASSWORD_DEFAULT)]);

echo count($disc) . " Disziplinen, " . count($kids) . " Kinder, $n Resultate, Benutzer «demo» angelegt.\n";
