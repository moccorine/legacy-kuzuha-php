<?php

namespace App;

class Config
{
    private static ?Config $instance = null;
    private array $config = [];

    private function __construct()
    {
        // Load .env if available
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            if (class_exists('Dotenv\Dotenv')) {
                $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
                $dotenv->safeLoad();
            }
        }

        // Load conf.php array
        $CONF = [];
        require __DIR__ . '/../conf.php';
        $this->config = $CONF;
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }
}
