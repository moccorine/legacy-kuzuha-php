<?php

namespace App;

use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Component\Translation\Loader\JsonFileLoader;

class Translator
{
    private static ?SymfonyTranslator $instance = null;
    private static string $locale = 'en';

    public static function getInstance(): SymfonyTranslator
    {
        if (self::$instance === null) {
            self::$instance = new SymfonyTranslator(self::$locale);
            self::$instance->addLoader('json', new JsonFileLoader());
            
            // Load translation files
            $translationsDir = __DIR__ . '/../translations';
            self::$instance->addResource('json', $translationsDir . '/messages.en.json', 'en');
            self::$instance->addResource('json', $translationsDir . '/messages.ja.json', 'ja');
        }
        return self::$instance;
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        if (self::$instance !== null) {
            self::$instance->setLocale($locale);
        }
    }

    public static function trans(string $key, array $parameters = []): string
    {
        return self::getInstance()->trans($key, $parameters);
    }
}
