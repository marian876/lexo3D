<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\Response;
use App\Support\Session;

final class AuthController
{
    public static function guest(): never
    {
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
            Session::flash('error', 'La sesión expiró. Intentá nuevamente.');
            Response::redirect('/');
        }

        $displayName = (string) ($_POST['display_name'] ?? '');
        $redirectTo = (string) ($_POST['redirect_to'] ?? '/');
        if (!str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            $redirectTo = '/';
        }

        try {
            AuthService::loginAsGuest($displayName);
            $joinCode = trim((string) ($_POST['join_code'] ?? ''));
            if ($joinCode !== '') {
                Response::redirect('/join?code=' . urlencode($joinCode));
            }
            Response::redirect($redirectTo);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/');
        }
    }

    public static function googleRedirect(): never
    {
        try {
            $url = AuthService::googleAuthUrl();
            Response::redirect($url);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/');
        }
    }

    public static function googleCallback(): never
    {
        $code = (string) ($_GET['code'] ?? '');
        $state = (string) ($_GET['state'] ?? '');
        if ($code === '') {
            Session::flash('error', 'Inicio de sesión con Google cancelado.');
            Response::redirect('/');
        }

        try {
            AuthService::handleGoogleCallback($code, $state);
            Session::flash('success', '¡Cuenta de Google conectada! Tu progreso ahora se guarda en la nube.');
            Response::redirect('/');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/');
        }
    }

    public static function updateProfile(): never
    {
        $user = AuthService::currentUser();
        if (!$user) {
            Response::redirect('/');
        }
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
            Session::flash('error', 'Token de seguridad inválido.');
            Response::redirect('/perfil');
        }

        try {
            AuthService::updateDisplayName((int) $user['id'], (string) ($_POST['display_name'] ?? ''));
            Session::flash('success', 'Apodo actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/perfil');
    }

    public static function logout(): never
    {
        if (Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
            Session::clearUser();
        }
        Response::redirect('/');
    }
}
