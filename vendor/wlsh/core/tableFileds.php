<?php

//php tableFileds.php 127.0.0.1 wlsh root 123456 /home/hanhui/phpProject/wlsh-core/tests/example/App/Models/Filed/

// 连接数据库
$dsn      = "mysql:host={$argv[1]};dbname={$argv[2]}";
$username = "{$argv[3]}";
$password = "{$argv[4]}";
$options  = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
);
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    echo "连接数据库失败: " . $e->getMessage();
    exit;
}
// 执行SQL查询以获取所有表名
$sql  = "SHOW TABLES FROM `wlsh`";
$stmt = $pdo->query($sql);

// 遍历结果集，每一项即为一个表名
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tableName = $row[0];
    $sql       = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName";
    $stmt_sql  = $pdo->prepare($sql);
    $stmt_sql->bindParam(":tableName", $tableName);
    $stmt_sql->execute();
    $columns = $stmt_sql->fetchAll(PDO::FETCH_ASSOC);


    $enumName = ucfirst($tableName);
    $enumCode = "<?php declare(strict_types=1);\n\n namespace Models\Filed;\n\n" . "Enum " . toPascalCase($enumName) . ":string\n{\n";
    $enumCode .= "    /** @var string 数据表名称 */" . PHP_EOL;
    $enumCode .= "    const string Table = '$tableName';" . PHP_EOL . PHP_EOL;

    foreach ($columns as $column) {
        $columnName = toPascalCase($column['COLUMN_NAME']);

        if ('NO' == $column['IS_NULLABLE']) {
            $enumCode .= "    /** @var string not null {$column['COLUMN_COMMENT']} */" . PHP_EOL;
        } else {
            $enumCode .= "    /** @var string null {$column['COLUMN_COMMENT']} */" . PHP_EOL;
        }

        $enumCode .= "    const string $columnName" . " = '{$column['COLUMN_NAME']}';" . PHP_EOL . PHP_EOL;
    }
    $enumCode .= "}" . PHP_EOL;

    file_put_contents($argv[5] . toPascalCase($enumName) . '.php', $enumCode);

    echo "生成{$tableName}表数据字段结构" . PHP_EOL;
}

function toPascalCase($input)
{
    $words            = explode('_', $input);
    $capitalizedWords = array_map(function ($word) {
        return ucfirst($word);
    }, $words);

    return implode('', $capitalizedWords);
}

