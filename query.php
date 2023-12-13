<?php

/**
 * @internal Helper class to query the JSON database
 */
final class Database
{
    private string $path;

    private array $database;

    public static function load(): Database
    {
        $database = new self(__DIR__ . '/database.json');
        $database->loadDatabase();
        return $database;
    }

    private function __construct(string $path)
    {
        $this->path = $path;
        $this->database = [];
    }

    private function loadDatabase(): void
    {
        $this->database = json_decode(file_get_contents($this->path), true);
    }

    public function getDatabase(): array
    {
        return $this->database;
    }

    public function table(string $table): Query
    {
        return new Query($this->database[$table]);
    }
}

/**
 * @internal Helper class to query the JSON database
 */
final class Query implements JsonSerializable
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function get(): array
    {
        return $this->data;
    }

    public function select(string ...$columns): Query
    {
        $result = [];

        foreach ($this->data as $row) {
            $resultRow = [];

            foreach ($columns as $column) {
                $resultRow[$column] = $row[$column];
            }

            $result[] = $resultRow;
        }

        return new Query($result);
    }

    public function column(string $column): array
    {
        $result = [];

        foreach ($this->data as $row) {
            $result[] = $row[$column];
        }

        return $result;
    }

    public function where(string $column, string $operator, string $value): Query
    {
        $result = [];

        foreach ($this->data as $row) {
            if ($row[$column] === $value) {
                $result[] = $row;
            }
        }

        return new Query($result);
    }

    public function first(): Query
    {
        return new Query([$this->data[array_key_first($this->data)]]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function sum(string $column): int
    {
        $result = 0;

        foreach ($this->data as $row) {
            $result += $row[$column];
        }

        return $result;
    }

    public function max(string $column): int
    {
        $result = 0;

        foreach ($this->data as $row) {
            $result = max($result, $row[$column]);
        }

        return $result;
    }

    public function min(string $column): int
    {
        $result = PHP_INT_MAX;

        foreach ($this->data as $row) {
            $result = min($result, $row[$column]);
        }

        return $result;
    }

    public function avg(string $column): int
    {
        return $this->sum($column) / $this->count();
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}

// Load database
$database = Database::load();
// Query database
//$result = $database->table('_database')->select('last_updated');

$result = $database->table('traffic')->select('views', 'clones')->where('date', '=', '2021-01-01T00:00:00Z')->first();
// Print result
echo json_encode($result, JSON_PRETTY_PRINT);
