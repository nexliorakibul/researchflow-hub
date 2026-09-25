<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/app-layout.php';

$userId = require_authenticated_user();
$connection = get_database_connection();
$userStatement = $connection->prepare(
    'SELECT id, name, email, password_hash FROM users WHERE id = :id LIMIT 1'
);
$userStatement->execute(['id' => $userId]);
$user = $userStatement->fetch();

if (!is_array($user)) {
    logout_user();
    flash_message('error', 'Your account session is no longer valid.');
    redirect(app_url('login.php'));
}

$errors = [];

if (is_post_request()) {
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $currentPassword = post_string('current_password');
    $newPassword = post_string('new_password');
    $passwordConfirmation = post_string('password_confirmation');

    if ($currentPassword === '' || !password_verify($currentPassword, (string) $user['password_hash'])) {
        $errors[] = 'Your current password is incorrect.';
    }

    if (!valid_password($newPassword)) {
        $errors[] = 'New password must be at least 8 characters and include uppercase, lowercase, and a number.';
    }

    if ($newPassword !== $passwordConfirmation) {
        $errors[] = 'New password confirmation does not match.';
    }

    if ($currentPassword !== '' && $newPassword !== '' && hash_equals($currentPassword, $newPassword)) {
        $errors[] = 'New password must be different from your current password.';
    }

    if ($errors === []) {
        try {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            if (!is_string($newPasswordHash) || $newPasswordHash === '') {
                throw new RuntimeException('Password hashing failed.');
            }

            $updateStatement = $connection->prepare(
                'UPDATE users SET password_hash = :password_hash WHERE id = :id'
            );
            $updateStatement->execute([
                'password_hash' => $newPasswordHash,
                'id' => $userId,
            ]);

            session_regenerate_id(true);
            rotate_csrf_token();
            flash_message('success', 'Your password has been changed.');
            redirect(app_url('change-password.php'));
        } catch (PDOException | RuntimeException $exception) {
            error_log('Password change error: ' . $exception->getCode());
            $errors[] = 'Unable to change your password right now. Please try again.';
        }
    }

    unset($currentPassword, $newPassword, $passwordConfirmation);
}

render_app_page_start('Change Password', $user, 'password');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <a href="<?= e(app_url('profile.php')) ?>">User Profile</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page">Change Password</span>
</nav>

<section class="page-heading">
    <div>
        <p class="page-kicker">Account security</p>
        <h2>Change your password</h2>
        <p>Confirm your current password before choosing a new one.</p>
    </div>
</section>

<section class="form-panel">
    <?php if ($errors !== []): ?>
        <div class="app-alert app-alert-error" role="alert">
            <strong>Please correct the following:</strong>
            <ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(app_url('change-password.php')) ?>" novalidate>
        <?= csrf_input() ?>
        <div class="form-grid">
            <div class="form-field form-field-wide">
                <label for="current-password">Current password</label>
                <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="form-field">
                <label for="new-password">New password</label>
                <input id="new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" aria-describedby="password-requirements" required>
                <small id="password-requirements" class="form-note">Use at least 8 characters with uppercase, lowercase, and a number.</small>
            </div>
            <div class="form-field">
                <label for="password-confirmation">Confirm new password</label>
                <input id="password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            </div>
        </div>
        <div class="form-actions">
            <button class="primary-button" type="submit">Change password</button>
            <a class="secondary-button" href="<?= e(app_url('profile.php')) ?>">Back to profile</a>
        </div>
    </form>
</section>
<?php render_app_page_end(); ?>
