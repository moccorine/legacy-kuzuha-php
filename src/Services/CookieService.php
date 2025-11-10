<?php

namespace App\Services;

use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CookieService
{
    /**
     * Set user info cookie (name, email, color)
     */
    public function setUserCookie(ResponseInterface $response, string $name, string $email, string $color): ResponseInterface
    {
        $cookieStr = "u={$name}&i={$email}&c={$color}";

        return FigResponseCookies::set(
            $response,
            SetCookie::create('c')
                ->withValue($cookieStr)
                ->withExpires(time() + 7776000) // 90 days
                ->withPath('/')
                ->withSecure(false) // Set to true in production with HTTPS
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
        );
    }

    /**
     * Get user info from cookie
     */
    public function getUserCookie(ServerRequestInterface $request): ?array
    {
        $cookies = $request->getCookieParams();
        $value = $cookies['c'] ?? null;

        if (!$value) {
            return null;
        }

        if (preg_match('/u=([^&]*)&i=([^&]*)&c=([^&]*)/', $value, $matches)) {
            return [
                'name' => $matches[1] ?? '',
                'email' => $matches[2] ?? '',
                'color' => $matches[3] ?? '',
            ];
        }

        return null;
    }

    /**
     * Set undo cookie
     */
    public function setUndoCookie(ResponseInterface $response, string $undoId, string $undoKey): ResponseInterface
    {
        $cookieStr = "p={$undoId}&k={$undoKey}";

        return FigResponseCookies::set(
            $response,
            SetCookie::create('undo')
                ->withValue($cookieStr)
                ->withExpires(time() + 86400) // 24 hours
                ->withPath('/')
                ->withSecure(false)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
        );
    }

    /**
     * Get undo info from cookie
     */
    public function getUndoCookie(ServerRequestInterface $request): ?array
    {
        $cookies = $request->getCookieParams();
        $value = $cookies['undo'] ?? null;

        if (!$value) {
            return null;
        }

        if (preg_match('/p=([^&]*)&k=([^&]*)/', $value, $matches)) {
            return [
                'post_id' => $matches[1] ?? '',
                'key' => $matches[2] ?? '',
            ];
        }

        return null;
    }

    /**
     * Delete cookie
     */
    public function deleteCookie(ResponseInterface $response, string $name): ResponseInterface
    {
        return FigResponseCookies::set(
            $response,
            SetCookie::create($name)
                ->withValue('')
                ->withExpires(1) // Expired
                ->withPath('/')
        );
    }

    /**
     * Apply pending cookies to response
     */
    public function applyPendingCookies(ResponseInterface $response, array $pendingCookies): ResponseInterface
    {
        if (isset($pendingCookies['user'])) {
            $user = $pendingCookies['user'];
            $response = $this->setUserCookie(
                $response,
                urlencode($user['name'] ?? ''),
                urlencode($user['email'] ?? ''),
                $user['color'] ?? ''
            );
        }

        if (isset($pendingCookies['undo'])) {
            $undo = $pendingCookies['undo'];
            $response = $this->setUndoCookie(
                $response,
                $undo['post_id'] ?? '',
                $undo['key'] ?? ''
            );
        }

        if (isset($pendingCookies['delete'])) {
            foreach ($pendingCookies['delete'] as $cookieName) {
                $response = $this->deleteCookie($response, $cookieName);
            }
        }

        return $response;
    }

    /**
     * Get user info from $_COOKIE superglobal
     */
    public static function getUserCookieFromGlobal(): ?array
    {
        $value = $_COOKIE['c'] ?? null;

        if (!$value) {
            return null;
        }

        if (preg_match('/u=([^&]*)&i=([^&]*)&c=([^&]*)/', $value, $matches)) {
            return [
                'name' => urldecode($matches[1] ?? ''),
                'email' => urldecode($matches[2] ?? ''),
                'color' => $matches[3] ?? '',
            ];
        }

        return null;
    }

    /**
     * Get undo info from $_COOKIE superglobal
     */
    public static function getUndoCookieFromGlobal(): ?array
    {
        $value = $_COOKIE['undo'] ?? null;

        if (!$value) {
            return null;
        }

        if (preg_match('/p=([^&]*)&k=([^&]*)/', $value, $matches)) {
            return [
                'post_id' => $matches[1] ?? '',
                'key' => $matches[2] ?? '',
            ];
        }

        return null;
    }
}
