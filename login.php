<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth-layout.php';

require_guest_user();

$errors = [];
$email = '';

if (is_post_request()) {
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $email = strtolower(trim(post_string('email')));
    $password = post_string('password');

    if (!valid_email($email) || $password === '') {
        $errors[] = 'Enter a valid email and password.';
    }

    if ($errors === []) {
        try {
            $connection = get_database_connection();
            $statement = $connection->prepare(
                'SELECT id, password_hash FROM users WHERE email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!is_array($user) || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'The email or password is incorrect.';
            } else {
                $userId = (int) $user['id'];

                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $updatePassword = $connection->prepare(
                        'UPDATE users SET password_hash = :password_hash WHERE id = :id'
                    );
                    $updatePassword->execute([
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $userId,
                    ]);
                }

                login_user($userId);
                redirect(app_url('dashboard.php'));
            }
        } catch (PDOException | RuntimeException $exception) {
            error_log('Login error: ' . $exception->getCode());
            $errors[] = 'Unable to log in right now. Please try again.';
        }
    }
}

render_auth_page_start('Welcome back');
render_auth_feedback($errors);
?>
<p class="intro">Log in to continue to your research workspace.</p>

<form class="auth-form" method="post" action="<?= e(app_url('login.php')) ?>" novalidate>
    <?= csrf_input() ?>

    <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="190" autocomplete="email" required autofocus>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>

    <button class="button" type="submit">Log in</button>
</form>

<p class="auth-switch">Need an account? <a href="<?= e(app_url('register.php')) ?>">Register</a></p>
<?php render_auth_page_end(); ?>
