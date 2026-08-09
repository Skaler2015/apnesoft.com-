<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AuthController extends Controller
{
    public function showLogin(array $args = []): never
    {
        if (Auth::check()) {
            $this->redirect(base_url('/admin'));
        }
        Response::html(View::render('admin/login', [
            'title'   => 'Admin Login',
            'noindex' => true,
            'error'   => Session::flash('login_error'),
        ], 'admin/blank'));
    }

    public function login(array $args = []): never
    {
        Csrf::check($this->request);
        $email = $this->request->str('email');
        $password = (string) $this->request->input('password', '');
        $ip = $this->request->ip();

        if (Auth::tooManyAttempts($ip)) {
            Session::flash('login_error', 'Too many attempts. Please wait a few minutes and try again.');
            $this->redirect(base_url('/admin/login'));
        }

        if (Auth::attempt($email, $password, $ip)) {
            $this->redirect(base_url('/admin'));
        }

        Session::flash('login_error', 'Invalid credentials.');
        $this->redirect(base_url('/admin/login'));
    }

    public function logout(array $args = []): never
    {
        Csrf::check($this->request);
        Auth::logout();
        $this->redirect(base_url('/admin/login'));
    }
}
