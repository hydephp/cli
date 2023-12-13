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

    public function table(string $table): array
    {
        return $this->database[$table];
    }

    public function get(string $table, string $key): array
    {
        return $this->database[$table][$key];
    }


    /** @param array<string, mixed> $query Example: ['id' => 123] */
    public function select(array $query): array
    {
        $key = array_key_first($query);
        $value = $query[$key];

        $result = [];
        foreach ($this->database as $row) {
            if ($row[$key] === $value) {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function column(string $table, string $column): array
    {
        $result = [];
        foreach ($this->database[$table] as $row) {
            $result[] = $row[$column];
        }

        return $result;
    }
}
