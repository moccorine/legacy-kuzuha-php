<?php

/**
 * Generate URL for a route with query parameters
 *
 * @param string $path Route path (e.g., 'search', 'follow')
 * @param array $params Query parameters
 * @return string Full URL
 */
function route(string $path, array $params = []): string
{
    $config = App\Config::getInstance();
    $baseUrl = rtrim($config->get('CGIURL'), '/');

    // Remove leading slash from path
    $path = ltrim($path, '/');

    $url = $baseUrl . '/' . $path;

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

/**
 * Generate URL for home page
 *
 * @param array $params Query parameters
 * @return string Full URL
 */
function home(array $params = []): string
{
    $config = App\Config::getInstance();
    $baseUrl = rtrim($config->get('CGIURL'), '/');

    if (!empty($params)) {
        $baseUrl .= '?' . http_build_query($params);
    }

    return $baseUrl;
}
