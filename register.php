<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth-layout.php';

require_guest_user();

$errors = [];
$name = '';
$email = '';

if (is_post_request()) {
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $name = trim(post_string('name'));
    $email = strtolower(trim(post_string('email')));
    $password = post_string('password');
    $passwordConfirmation = post_string('password_confirmation');

    if (!string_length_between($name, 2, 100)) {
        $errors[] = 'Full name must contain 2 to 100 characters.';
    }

    if (!valid_email($email)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!valid_password($password)) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        try {
            $connection = get_database_connection();
            $existingUser = $connection->prepare(
                'SELECT id FROM users WHERE email = :email LIMIT 1'
            );
            $existingUser->execute(['email' => $email]);

            if ($existingUser->fetchColumn() !== false) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $insertUser = $connection->prepare(
                    'INSERT INTO users (name, email, password_hash)
                     VALUES (:name, :email, :password_hash)'
                );
                $insertUser->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                flash_message('success', 'Registration successful. You can now log in.');
                redirect(app_url('login.php'));
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'An account with this email already exists.';
            } else {
                error_log('Registration database error: ' . $exception->getCode());
                $errors[] = 'Unable to create the account right now. Please try again.';
            }
        } catch (RuntimeException $exception) {
            $errors[] = 'Unable to create the account right now. Please try again.';
        }
    }
}

render_auth_page_start('Create your account');
render_auth_feedback($errors);
?>
<p class="intro">Start organizing your research projects in one secure workspace.</p>

<form class="auth-form" method="post" action="<?= e(app_url('register.php')) ?>" novalidate>
    <?= csrf_input() ?>

    <div class="field">
        <label for="name">Full name</label>
        <input id="name" name="name" type="text" value="<?= e($name) ?>" minlength="2" maxlength="100" autocomplete="name" required>
    </div>

    <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="190" autocomplete="email" required>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
        <small class="form-note">Use at least 8 characters with uppercase, lowercase, and a number.</small>
    </div>

    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
    </div>

    <button class="button" type="submit">Create account</button>
</form>

<p class="auth-switch">Already registered? <a href="<?= e(app_url('login.php')) ?>">Log in</a></p>
<?php render_auth_page_end(); ?>
