<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$stored = is_array($_SESSION['_orcid_oauth'] ?? null) ? $_SESSION['_orcid_oauth'] : [];
unset($_SESSION['_orcid_oauth']);
$state = trim(query_string('state'));
$storedState = (string) ($stored['state'] ?? '');
$createdAt = (int) ($stored['created_at'] ?? 0);
$destination = user_is_authenticated() ? 'profile.php' : 'login.php';

if ($state === '' || $storedState === '' || !hash_equals($storedState, $state) || $createdAt < time() - 600) {
    flash_message('error', 'The ORCID sign-in session is invalid or expired. Please try again.');
    redirect(app_url($destination));
}

if (query_string('error') !== '') {
    flash_message('error', 'ORCID authorization was cancelled or denied.');
    redirect(app_url($destination));
}

try {
    $identity = orcid_exchange_code(query_string('code'));
    $connection = get_database_connection();
    $mode = (string) ($stored['mode'] ?? 'login');

    if ($mode === 'connect') {
        $userId = require_authenticated_user();
        $statement = $connection->prepare(
            'UPDATE users SET orcid_id = :orcid_id WHERE id = :id'
        );
        $statement->execute(['orcid_id' => $identity['orcid_id'], 'id' => $userId]);
        flash_message('success', 'Your authenticated ORCID iD has been connected.');
        redirect(app_url('profile.php'));
    }

    $statement = $connection->prepare('SELECT id FROM users WHERE orcid_id = :orcid_id LIMIT 1');
    $statement->execute(['orcid_id' => $identity['orcid_id']]);
    $userId = $statement->fetchColumn();
    if ($userId === false) {
        flash_message('warning', 'No ResearchFlow Hub account is linked to that ORCID iD. Log in with email, then connect ORCID from your profile.');
        redirect(app_url('login.php'));
    }

    login_user((int) $userId);
    redirect(app_url('dashboard.php'));
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        flash_message('error', 'That ORCID iD is already connected to another account.');
    } else {
        error_log('ORCID database error: ' . $exception->getCode());
        flash_message('error', 'Unable to save the ORCID connection right now.');
    }
} catch (RuntimeException $exception) {
    error_log('ORCID callback error: ' . $exception->getMessage());
    flash_message('error', 'ORCID authentication could not be completed. Please try again.');
}

redirect(app_url($destination));
