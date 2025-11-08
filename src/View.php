<?php

namespace App;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class View
{
    private static $instance = null;
    private $twig;

    private function __construct()
    {
        $templateDir = getcwd() . '/resources/views';
        $cacheDir = getcwd() . '/storage/cache/twig';

        $loader = new FilesystemLoader($templateDir);
        $this->twig = new Environment($loader, [
            'cache' => $cacheDir,
            'auto_reload' => true,
            'debug' => true,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
