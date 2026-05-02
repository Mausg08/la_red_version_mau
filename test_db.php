<?php
/**
 * Archivo de diagnóstico — ELIMINAR después de usarlo
 * Accede en: http://localhost:8012/RedSocial_BUAP/test_db.php
 */

$configs = [
    ['host'=>'127.0.0.1', 'port'=>'3307', 'user'=>'root', 'pass'=>''],
    ['host'=>'localhost',  'port'=>'3307', 'user'=>'root', 'pass'=>''],
    ['host'=>'127.0.0.1', 'port'=>'3306', 'user'=>'root', 'pass'=>''],
    ['host'=>'localhost',  'port'=>'3306', 'user'=>'root', 'pass'=>''],
];

echo "<h2>Diagnóstico de conexión MySQL</h2>";
echo "<style>body{font-family:monospace;padding:20px} .ok{color:green} .fail{color:red} pre{background:#f0f0f0;padding:10px}</style>";

foreach ($configs as $c) {
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname=red_social;charset=utf8mb4";
    echo "<hr><b>Probando:</b> host={$c['host']} port={$c['port']} user={$c['user']}<br>";
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
        $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "<span class='ok'>✅ CONEXIÓN EXITOSA</span><br>";
        echo "MySQL version: <b>$ver</b><br>";
        echo "Usuarios en BD: <b>$cnt</b><br>";
        echo "<pre>DSN que funciona:\nmysql:host={$c['host']};port={$c['port']};dbname=red_social;charset=utf8mb4\nUser: {$c['user']}\nPass: '{$c['pass']}'</pre>";
    } catch (PDOException $e) {
        echo "<span class='fail'>❌ FALLÓ: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
}

echo "<hr><b>PHP version:</b> " . PHP_VERSION . "<br>";
echo "<b>PDO drivers:</b> " . implode(', ', PDO::getAvailableDrivers()) . "<br>";
echo "<br><span style='color:orange'>⚠️ Elimina este archivo después de usarlo</span>";
?>
