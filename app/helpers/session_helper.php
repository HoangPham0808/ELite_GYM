<?php

function ensureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool
{
    ensureSession();
    return isset($_SESSION['account_id']) && !empty($_SESSION['account_id']);
}

function currentRole(): string
{
    return $_SESSION['role'] ?? '';
}

function currentPosition(): string
{
    return $_SESSION['position'] ?? '';
}
