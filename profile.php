<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/app-layout.php';

$userId = require_authenticated_user();
$connection = get_database_connection();

$loadUser = static function (PDO $connection, int $userId): array {
    $statement = $connection->prepare(
        'SELECT id, name, email, institution, research_interests, bio, created_at, updated_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return is_array($user) ? $user : [];
};

$user = $loadUser($connection, $userId);

if ($user === []) {
    logout_user();
    flash_message('error', 'Your account session is no longer valid.');
    redirect(app_url('login.php'));
}

$values = [
    'name' => (string) $user['name'],
    'email' => (string) $user['email'],
    'institution' => (string) ($user['institution'] ?? ''),
    'research_interests' => (string) ($user['research_interests'] ?? ''),
    'bio' => (string) ($user['bio'] ?? ''),
];
$errors = [];

if (is_post_request()) {
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $values = [
        'name' => trim(post_string('name')),
        'email' => strtolower(trim(post_string('email'))),
        'institution' => trim(post_string('institution')),
        'research_interests' => trim(post_string('research_interests')),
        'bio' => trim(post_string('bio')),
    ];

    if (!string_length_between($values['name'], 2, 100)) {
        $errors[] = 'Full name must contain 2 to 100 characters.';
    }

    if (!string_length_between($values['email'], 3, 190) || !valid_email($values['email'])) {
        $errors[] = 'Enter a valid email address containing no more than 190 characters.';
    }

    if ($values['institution'] !== '' && !string_length_between($values['institution'], 1, 150)) {
        $errors[] = 'Institution must contain no more than 150 characters.';
    }

    if (strlen($values['research_interests']) > 65535) {
        $errors[] = 'Research interests are too long.';
    }

    if (strlen($values['bio']) > 65535) {
        $errors[] = 'Bio is too long.';
    }

    if ($errors === []) {
        try {
            $emailStatement = $connection->prepare(
                'SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1'
            );
            $emailStatement->execute([
                'email' => $values['email'],
                'id' => $userId,
            ]);

            if ($emailStatement->fetchColumn() !== false) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $updateStatement = $connection->prepare(
                    'UPDATE users
                     SET name = :name,
                         email = :email,
                         institution = :institution,
                         research_interests = :research_interests,
                         bio = :bio
                     WHERE id = :id'
                );
                $updateStatement->execute([
                    'name' => $values['name'],
                    'email' => $values['email'],
                    'institution' => $values['institution'] !== '' ? $values['institution'] : null,
                    'research_interests' => $values['research_interests'] !== '' ? $values['research_interests'] : null,
                    'bio' => $values['bio'] !== '' ? $values['bio'] : null,
                    'id' => $userId,
                ]);

                flash_message('success', 'Your profile has been updated.');
                redirect(app_url('profile.php'));
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'An account with this email already exists.';
            } else {
                error_log('Profile update database error: ' . $exception->getCode());
                $errors[] = 'Unable to update your profile right now. Please try again.';
            }
        }
    }
}

$initial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr((string) $user['name'], 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr((string) $user['name'], 0, 1));
$joinedDate = new DateTimeImmutable((string) $user['created_at']);
$updatedDate = new DateTimeImmutable((string) $user['updated_at']);

render_app_page_start('User Profile', $user, 'profile');
render_app_feedback();
?>
<section class="page-heading">
    <div>
        <p class="page-kicker">Account</p>
        <h2>Your researcher profile</h2>
        <p>Keep your account identity and research information current.</p>
    </div>
    <a class="secondary-button" href="<?= e(app_url('change-password.php')) ?>">Change password</a>
</section>

<div class="profile-layout">
    <aside class="dashboard-panel profile-summary" aria-label="Profile summary">
        <span class="profile-avatar" aria-hidden="true"><?= e($initial !== '' ? $initial : 'R') ?></span>
        <h2><?= e($user['name']) ?></h2>
        <p><?= e($user['email']) ?></p>
        <dl class="profile-meta">
            <div><dt>Institution</dt><dd><?= e($user['institution'] ?: 'Not provided') ?></dd></div>
            <div><dt>Member since</dt><dd><?= e($joinedDate->format('M j, Y')) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e($updatedDate->format('M j, Y')) ?></dd></div>
        </dl>
    </aside>

    <section class="form-panel profile-form-panel">
        <div class="panel-heading">
            <h2>Edit profile</h2>
            <p>Your email address is also used to log in.</p>
        </div>

        <?php if ($errors !== []): ?>
            <div class="app-alert app-alert-error" role="alert">
                <strong>Please correct the following:</strong>
                <ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('profile.php')) ?>" novalidate>
            <?= csrf_input() ?>
            <div class="form-grid">
                <div class="form-field">
                    <label for="profile-name">Full name</label>
                    <input id="profile-name" name="name" type="text" value="<?= e($values['name']) ?>" minlength="2" maxlength="100" autocomplete="name" required>
                </div>
                <div class="form-field">
                    <label for="profile-email">Email address</label>
                    <input id="profile-email" name="email" type="email" value="<?= e($values['email']) ?>" maxlength="190" autocomplete="email" required>
                </div>
                <div class="form-field form-field-wide">
                    <label for="profile-institution">Institution</label>
                    <input id="profile-institution" name="institution" type="text" value="<?= e($values['institution']) ?>" maxlength="150" autocomplete="organization" placeholder="University or research organization">
                </div>
                <div class="form-field form-field-wide">
                    <label for="profile-interests">Research interests</label>
                    <textarea id="profile-interests" name="research_interests" rows="5" placeholder="Topics, methods, or domains you study"><?= e($values['research_interests']) ?></textarea>
                </div>
                <div class="form-field form-field-wide">
                    <label for="profile-bio">Bio</label>
                    <textarea id="profile-bio" name="bio" rows="6" placeholder="A short description of your research background"><?= e($values['bio']) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="primary-button" type="submit">Save profile</button>
                <a class="secondary-button" href="<?= e(app_url('profile.php')) ?>">Cancel changes</a>
            </div>
        </form>
    </section>
</div>
<?php render_app_page_end(); ?>
