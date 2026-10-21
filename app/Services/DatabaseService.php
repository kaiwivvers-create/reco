<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DatabaseService
{
    public const FRAMEWORK_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens', 'personal_access_tokens',
    ];

    public function tables(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("select name from sqlite_master where type='table' and name not like 'sqlite_%'"))
                ->pluck('name')->sort()->values()->all();
        }

        if ($driver === 'pgsql') {
            return collect(DB::select("select table_name from information_schema.tables where table_schema = 'public'"))
                ->pluck('table_name')->sort()->values()->all();
        }

        return collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->sort()->values()->all();
    }

    public function dataTables(): array
    {
        return array_values(array_diff($this->tables(), self::FRAMEWORK_TABLES));
    }

    /**
     * Foreign-key friendly insertion order.
     */
    public function orderedTables(): array
    {
        $preferred = [
            'settings', 'users', 'criteria', 'suppliers', 'supplier_scores',
            'evaluations', 'evaluation_results', 'activity_logs', 'role_permissions',
        ];

        $available = $this->dataTables();
        $ordered = array_values(array_intersect($preferred, $available));
        $extras = array_values(array_diff($available, $ordered));

        return array_merge($ordered, $extras);
    }

    public function counts(): array
    {
        $counts = [];
        foreach ($this->dataTables() as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    public function connectionInfo(): array
    {
        return [
            'driver' => DB::connection()->getDriverName(),
            'database' => DB::connection()->getDatabaseName(),
            'host' => config('database.connections.'.config('database.default').'.host', '-'),
        ];
    }

    public function exportData(): array
    {
        $tables = [];
        foreach ($this->orderedTables() as $table) {
            $tables[$table] = array_map(
                fn ($row) => (array) $row,
                DB::table($table)->orderBy(Schema::getColumnListing($table)[0] ?? 'id')->get()->all()
            );
        }

        return [
            'meta' => [
                'application' => 'SuppliRank',
                'company' => setting('company_name'),
                'generated_at' => now()->toIso8601String(),
                'driver' => DB::connection()->getDriverName(),
                'database' => DB::connection()->getDatabaseName(),
                'version' => 1,
            ],
            'tables' => $tables,
        ];
    }

    public function sqlDump(): string
    {
        $pdo = DB::connection()->getPdo();
        $sql = "-- SuppliRank database export\n-- Generated: ".now()->toDateTimeString()."\n-- Driver: ".DB::connection()->getDriverName()."\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->orderedTables() as $table) {
            $columns = Schema::getColumnListing($table);
            $rows = DB::table($table)->get();
            if ($rows->isEmpty()) {
                continue;
            }

            $columnList = implode(', ', array_map(fn ($column) => '`'.$column.'`', $columns));
            $sql .= "-- Table: {$table} ({$rows->count()} rows)\n";

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $value = $row->{$column};
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $values[] = (string) $value;
                    } else {
                        $values[] = $pdo->quote((string) $value);
                    }
                }
                $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES (".implode(', ', $values).");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    public function csv(string $table): string
    {
        if (! in_array($table, $this->dataTables(), true)) {
            throw new InvalidArgumentException('Tabel tidak diizinkan.');
        }

        $columns = Schema::getColumnListing($table);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $columns);

        DB::table($table)->orderBy($columns[0] ?? 'id')->chunk(500, function ($rows) use ($handle, $columns) {
            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $value = $row->{$column};
                    $line[] = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
                }
                fputcsv($handle, $line);
            }
        });

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    /**
     * @return array<string,int> number of rows processed per table
     */
    public function import(array $payload, string $mode = 'replace'): array
    {
        $tables = $payload['tables'] ?? $payload;

        if (! is_array($tables) || $tables === []) {
            throw new InvalidArgumentException('Berkas tidak berisi data tabel yang valid.');
        }

        $ordered = $this->orderedTables();
        $targets = array_values(array_intersect($ordered, array_keys($tables)));

        if ($targets === []) {
            throw new InvalidArgumentException('Tidak ada tabel yang dikenali pada berkas impor.');
        }

        $driver = DB::connection()->getDriverName();
        $summary = [];

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            DB::transaction(function () use ($targets, $tables, $mode, &$summary) {
                if ($mode === 'replace') {
                    foreach (array_reverse($targets) as $table) {
                        DB::table($table)->delete();
                    }
                }

                foreach ($targets as $table) {
                    $rows = array_values(array_filter((array) $tables[$table], 'is_array'));
                    $columns = Schema::getColumnListing($table);
                    $clean = [];

                    foreach ($rows as $row) {
                        $filtered = array_intersect_key($row, array_flip($columns));
                        if ($filtered !== []) {
                            $clean[] = $filtered;
                        }
                    }

                    foreach (array_chunk($clean, 200) as $chunk) {
                        if ($mode === 'replace') {
                            DB::table($table)->insert($chunk);
                        } else {
                            DB::table($table)->insertOrIgnore($chunk);
                        }
                    }

                    $summary[$table] = count($clean);
                }
            });
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }

        return $summary;
    }
}
