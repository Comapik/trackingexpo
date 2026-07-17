<?php
require_once __DIR__ . '/db.php';

const AUTH_COOKIE = 'expo_auth';
const AUTH_LIFETIME = 60 * 60 * 24 * 365; // 1 an, pour ne pas redemander le mot de passe

function authToken(): string
{
    return hash_hmac('sha256', 'expo-photo-access', APP_PASSWORD);
}

function isAuthenticated(): bool
{
    return isset($_COOKIE[AUTH_COOKIE]) && hash_equals(authToken(), $_COOKIE[AUTH_COOKIE]);
}

function setAuthCookie(): void
{
    setcookie(AUTH_COOKIE, authToken(), [
        'expires' => time() + AUTH_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function requireAuth(): void
{
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function requireAuthApi(): void
{
    if (!isAuthenticated()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Authentification requise']);
        exit;
    }
}
