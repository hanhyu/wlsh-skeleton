<?php
declare(strict_types=1);

namespace Wlsh;

use PDO;
use PDOStatement;

/**
 * from()：指定查询的表格
 * select()：指定查询的列
 * where()：添加 WHERE 条件
 * whereIn()：WHERE IN 查询
 * whereNotIn()：WHERE NOT IN 查询
 * whereNull()：WHERE NULL 查询
 * whereNotNull()：WHERE NOT NULL 查询
 * like()：LIKE 查询
 * notLike()：NOT LIKE 查询
 * join()：连接查询
 * leftJoin()：左连接查询
 * rightJoin()：右连接查询，默认不支持
 * orderBy()：排序查询
 * groupBy()：分组查询
 * having()：HAVING 查询
 * limit()：LIMIT 查询
 * offset()：OFFSET 查询
 * query()：生成查询sql语句
 * getSql()：获取查询sql
 * setSql()：手动设置查询sql
 * execute(): 执行sql查询
 * fetchAll(): 批量查询
 * fetchOne(): 单行查询
 * fetchColumn(): 单列查询
 * insert()：插入数据
 * update()：更新数据
 * delete()：删除数据
 * beginTransaction()：开启事务
 * commit()：提交事务
 * rollback()：回滚事务
 *
 */
class Db
{
    protected PDO $pdo;
    protected string $table;
    protected array|string $select;
    protected array $where = [];
    protected array $params = [];
    protected array $joins = [];
    protected array $orderBy = [];
    protected array $groupBy = [];
    protected array $having = [];
    protected int|string $limit;
    protected int|string $offset;
    protected string $sql = '';
    public bool $isSaveLog = false;
    public string $saveLogPath = "/tmp/wlsh-mysql-sql.txt";

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function from(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * 本类不支持的方法需要使用原生sql实现，直接使用原生sql查询，需要在测试阶段先评审才能上线。
     *
     * @param array|string $columns
     * @return Db
     */
    public function select(array|string $columns): static
    {
        $this->select = $columns;
        return $this;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function join(string $table, string $localKey, string $operator, string $foreignKey): static
    {
        $this->joins[] = "JOIN $table ON $localKey $operator $foreignKey";
        return $this;
    }

    public function leftJoin(string $table, string $localKey, string $operator, string $foreignKey): static
    {
        $this->joins[] = "LEFT JOIN $table ON $localKey $operator $foreignKey";
        return $this;
    }

    public function rightJoin(string $table, string $localKey, string $operator, string $foreignKey): static
    {
        $this->joins[] = "RIGHT JOIN $table ON $localKey $operator $foreignKey";
        return $this;
    }

    /**
     * 默认为and
     * 不支持or，如果非要使用or语句请用原生sql实现，注意or用法有很多坑
     *
     * @param array|string $column
     * @param string|null $operator
     * @param string|int|null $value
     * @return Db
     */
    public function where(array|string|null $column, string|null $operator = null, string|int|null $value = null): static
    {
        if (empty($column)) {
            return $this;
        }

        if (is_array($column)) {
            foreach ($column as $key => $value) {
                $this->where[]  = "$key = ?";
                $this->params[] = $value;
            }
        } else {
            $this->where[]  = "$column $operator ?";
            $this->params[] = $value;
        }

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $in = rtrim(str_repeat('?,', count($values)), ',');

        $this->where[] = "$column IN ($in)";
        $this->params  = array_merge($this->params, $values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $in = rtrim(str_repeat('?,', count($values)), ',');

        $this->where[] = "$column NOT IN ($in)";
        $this->params  = array_merge($this->params, $values);
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->where[] = "$column IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->where[] = "$column IS NOT NULL";
        return $this;
    }

    public function like(string $column, string $value): static
    {
        $this->where[]  = "$column LIKE ?";
        $this->params[] = $value;
        return $this;
    }

    public function notLike(string $column, string $value): static
    {
        $this->where[]  = "$column NOT LIKE ?";
        $this->params[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    public function groupBy(string $column): static
    {
        $this->groupBy[] = $column;
        return $this;
    }

    public function having(string $column, string $operator, $value): static
    {
        $this->having[] = "$column $operator ?";
        $this->params[] = $value;
        return $this;
    }

    public function limit(int|string $limit): static
    {
        $this->limit = is_string($limit) ? (int)$limit : $limit;
        return $this;
    }

    public function offset(int|string $offset): static
    {
        $this->offset = is_string($offset) ? (int)$offset : $offset;;
        return $this;
    }

    private function query(): static
    {
        if (is_array($this->select)) {
            $select = empty($this->select) ? "*" : implode(",", $this->select);
        } else {
            $select = empty($this->select) ? "*" : $this->select;
        }
        $where   = empty($this->where) ? "" : "WHERE " . implode(" AND ", $this->where);
        $join    = empty($this->joins) ? "" : implode(" ", $this->joins);
        $orderBy = empty($this->orderBy) ? "" : "ORDER BY " . implode(", ", $this->orderBy);
        $groupBy = empty($this->groupBy) ? "" : "GROUP BY " . implode(", ", $this->groupBy);
        $having  = empty($this->having) ? "" : "HAVING " . implode(" AND ", $this->having);
        $limit   = empty($this->limit) ? "" : "LIMIT {$this->limit}";
        $offset  = empty($this->offset) ? "" : "OFFSET {$this->offset}";

        $this->sql = "SELECT {$select} FROM {$this->table} {$join} {$where} {$groupBy} {$having} {$orderBy} {$limit} {$offset}";

        return $this;
    }

    private function joinSql(string $prepare_sql, array $params): ?string
    {
        return preg_replace_callback('/\?/', function () use (&$params) {
            $let = array_shift($params);
            return is_string($let) ? "'" . $let . "'" : $let;
        }, $prepare_sql);
    }

    private function saveSqlLog(string $prepare_sql, array $params): void
    {
        if ($this->isSaveLog) {
            file_put_contents(
                $this->saveLogPath,
                '[' . date("Y-m-d H:i:s") . '] ' . $this->joinSql($prepare_sql, $params) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    public function getSql(): ?string
    {
        $this->query();
        return $this->joinSql($this->sql, $this->params);
    }

    public function setSql(string $sql): static
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * 本类不支持的方法需要使用原生sql实现，直接使用原生sql查询，需要在测试阶段先评审才能上线。
     *
     * @param $sql
     * @return false|PDOStatement
     */
    public function execute($sql): bool|PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);

        $this->saveSqlLog($sql, $this->params);

        $this->select = $this->table = $this->limit = $this->offset = '';
        $this->where  = $this->params = $this->joins = $this->orderBy = $this->groupBy = $this->having = [];
        return $stmt;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        $stmt = $this->query()->execute($this->sql);
        return $stmt->fetchAll($mode);
    }

    /**
     * @param int $mode
     * @return mixed 如果失败或数据为空，则返回false
     */
    public function fetchOne(int $mode = PDO::FETCH_ASSOC): mixed
    {
        $stmt = $this->query()->execute($this->sql);
        return $stmt->fetch($mode);
    }

    public function fetchColumn(): mixed
    {
        $stmt = $this->query()->execute($this->sql);
        return $stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $columns      = array_keys($data);
        $values       = array_values($data);
        $placeholders = implode(',', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO $this->table (" . implode(',', $columns) . ") VALUES ($placeholders)";

        $this->saveSqlLog($sql, $values);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return (int)$this->pdo->lastInsertId();
    }

    public function batchInsert(array $data): int
    {
        $columns = array_keys($data[0]);

        $valuesPlaceholder = rtrim(str_repeat('(?' . str_repeat(', ?', count($columns) - 1) . '), ', count($data)), ', ');

        $sql = "INSERT INTO $this->table (" . implode(',', $columns) . ") VALUES $valuesPlaceholder";

        $stmt = $this->pdo->prepare($sql);

        $i = 1;
        foreach ($data as $row) {
            foreach ($row as $value) {
                $stmt->bindValue($i++, $value);
            }
        }

        unset($data, $valuesPlaceholder, $sql);

        $stmt->execute();
        return $stmt->rowCount();
    }


    public function update(array $data): int
    {
        $sets = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = ?";
            //todo 这里是否不需要加入
            $this->params[] = $value;
        }
        $where     = empty($this->where) ? "" : "WHERE " . implode(" AND ", $this->where);
        $this->sql = "UPDATE {$this->table} SET " . implode(",", $sets) . " {$where}";
        $stmt      = $this->pdo->prepare($this->sql);
        $stmt->execute(array_merge(array_values($data), $this->params));
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        if (empty($this->where)) {
            return 0;
        }

        $sql = "DELETE FROM {$this->table} WHERE " . implode(" AND ", $this->where);

        $this->saveSqlLog($sql, $this->params);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);

        return $stmt->rowCount();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
