<?php
// php/services/MigrationService.php

class MigrationService {
    private PDO $db;
    private string $migrations_dir;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->migrations_dir = __DIR__ . '/../migrations';
        $this->ensureMigrationsTableExists();
    }

    private function ensureMigrationsTableExists(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT NOT NULL UNIQUE,
            ran_at TEXT NOT NULL
        )");
    }

    public function getRanMigrations(): array {
        $stmt = $this->db->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function getAvailableMigrations(): array {
        if (!is_dir($this->migrations_dir)) {
            return [];
        }
        $allFiles = scandir($this->migrations_dir);
        $ranMigrations = $this->getRanMigrations();
        
        $available = [];
        foreach ($allFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                if (!in_array($file, $ranMigrations)) {
                    $available[] = $file;
                }
            }
        }
        sort($available);
        return $available;
    }

    public function runMigration(string $filename): void {
        $filepath = $this->migrations_dir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            throw new Exception("Migration file not found: {$filename}");
        }

        $ranMigrations = $this->getRanMigrations();
        if (in_array($filename, $ranMigrations)) {
            throw new Exception("Migration already ran: {$filename}");
        }

        debug_log("Running migration: {$filename}");

        // The migration file is expected to return a function that takes a PDO object
        $migration_function = require $filepath;

        if (!is_callable($migration_function)) {
            throw new Exception("Migration file '{$filename}' must return a callable function.");
        }

        try {
            $this->db->beginTransaction();
            
            // Execute the migration logic
            $migration_function($this->db);

            // Record the migration
            $stmt = $this->db->prepare("INSERT INTO migrations (migration, ran_at) VALUES (?, ?)");
            $stmt->execute([$filename, date('Y-m-d H:i:s')]);

            $this->db->commit();
            debug_log("Successfully ran and recorded migration: {$filename}");
        } catch (Exception $e) {
            $this->db->rollBack();
            debug_log("Failed to run migration '{$filename}': " . $e->getMessage(), 'MIGRATION_ERROR', 1, 'ERROR');
            throw $e;
        }
    }
}
