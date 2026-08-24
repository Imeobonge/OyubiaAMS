<?php
/** Authentication controller. */

function login_form(): void
{
    if (current_user()) {
        redirect('/');
    }
    view_raw('auth/login', ['title' => 'Sign in', 'error' => null]);
}

function login_submit(): void
{
    verify_csrf();
    $email = input('email');
    $pass  = input('password');
    if (attempt_login($email, $pass)) {
        flash('Welcome back.');
        redirect('/');
    }
    http_response_code(401);
    view_raw('auth/login', ['title' => 'Sign in', 'error' => 'Invalid email or password.']);
}

function logout_action(): void
{
    logout();
    redirect('/login');
}
