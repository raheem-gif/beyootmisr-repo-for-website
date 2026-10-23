<?php
/**
 * Admin accounts and roles. Users live in app/storage/users.php (a PHP file
 * that returns an array, so it is never readable over HTTP).
 *
 *   adm_roles(): array                                 role => Arabic name
 *   adm_can(?array $user, string $perm): bool          server-side permission check (section keys + 'rebuild')
 *   adm_users(): array                                 username => record (no caching: always fresh)
 *   adm_user_get(string $username): ?array
 *   adm_users_exist(): bool
 *   adm_authenticate(string $username, string $password): ?array   (timing-safe; dummy hash for unknown users)
 *   adm_user_create(string $username, string $name, string $role, string $password): array
 *   adm_user_update(string $username, array $changes): array        name / role
 *   adm_user_set_password(string $username, string $password): void (bumps "pwv" → other sessions end)
 *   adm_user_delete(string $username): void
 *   adm_password_problem(string $password, string $username = ''): string  '' when acceptable
 *   adm_username_problem(string $username): string
 *
 * Record: {username, name, role, hash, pwv, created_at, updated_at, last_login}
 */
declare(strict_types=1);
defined('BM_APP') || exit;

const ADM_PASSWORD_MIN = 10;
const ADM_PASSWORD_MAX = 128;
/** bcrypt hash of a random string: verified when the user does not exist so timing does not leak usernames. */
const ADM_DUMMY_HASH = '$2y$10$9vMnKB8VDr/8RkYeJm9ukOYYIcdpZfXXmnGLZEz9bKBtn/PShKKE2';

function adm_roles(): array
{
    return [
        'admin' => 'مدير',
        'editor' => 'محرر',
    ];
}

/** What each role may open/do. Keys are section keys from sections.php plus 'rebuild'. */
function adm_role_permissions(): array
{
    return [
        'admin' => ['*'],
        'editor' => ['dashboard', 'account', 'texts', 'stats', 'projects', 'stories', 'reports', 'galleries', 'rebuild'],
    ];
}

function adm_can(?array $user, string $perm): bool
{
    if ($user === null) {
        return false;
    }
    $map = adm_role_permissions();
    $allowed = $map[$user['role'] ?? ''] ?? [];
    return in_array('*', $allowed, true) || in_array($perm, $allowed, true);
}

function adm_users_file(): string
{
    return adm_storage('users.php');
}

function adm_users(): array
{
    $out = [];
    foreach (adm_store_php_read(adm_users_file()) as $name => $u) {
        if (is_array($u) && !empty($u['username']) && !empty($u['hash'])) {
            $u += ['name' => '', 'role' => 'editor', 'pwv' => 1, 'created_at' => '', 'updated_at' => '', 'last_login' => ''];
            if (!isset(adm_roles()[$u['role']])) {
                $u['role'] = 'editor';
            }
            $out[(string) $u['username']] = $u;
        }
    }
    ksort($out);
    return $out;
}

function adm_users_exist(): bool
{
    return count(adm_users()) > 0;
}

function adm_user_get(string $username): ?array
{
    $users = adm_users();
    return $users[strtolower($username)] ?? null;
}

function adm_users_save(array $users): void
{
    adm_store_php_write(adm_users_file(), $users);
}

function adm_username_problem(string $username): string
{
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $username)) {
        return 'اسم المستخدم من 3 إلى 32 حرفًا إنجليزيًا صغيرًا أو أرقامًا، ويمكن أن يحتوي على . أو _ أو - (مثل: sara.ali).';
    }
    return '';
}

function adm_password_problem(string $password, string $username = ''): string
{
    $len = bm_strlen($password);
    if ($len < ADM_PASSWORD_MIN) {
        return 'كلمة المرور يجب ألا تقل عن ' . ADM_PASSWORD_MIN . ' أحرف.';
    }
    if ($len > ADM_PASSWORD_MAX) {
        return 'كلمة المرور طويلة جدًا (الحد الأقصى ' . ADM_PASSWORD_MAX . ' حرفًا).';
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        return 'لا تستخدم اسم المستخدم داخل كلمة المرور.';
    }
    if (preg_match('/^(.)\1+$/u', $password)) {
        return 'كلمة المرور ضعيفة جدًا: لا تكرر الحرف نفسه.';
    }
    $weak = ['1234567890', '0123456789', '12345678910', 'password123', 'qwertyuiop', 'beyootmisr', 'beyoot-misr', 'admin12345', '1111111111'];
    if (in_array(strtolower($password), $weak, true)) {
        return 'كلمة المرور شائعة وسهلة التخمين؛ اختر عبارة أطول وأصعب.';
    }
    return '';
}

function adm_hash_password(string $password): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('password_hash failed');
    }
    return $hash;
}

/** Verify credentials. Always runs password_verify so response time does not reveal valid usernames. */
function adm_authenticate(string $username, string $password): ?array
{
    $username = strtolower(trim($username));
    $user = $username !== '' ? adm_user_get($username) : null;
    $hash = $user !== null ? (string) $user['hash'] : ADM_DUMMY_HASH;
    $ok = password_verify($password, $hash);
    if (!$ok || $user === null) {
        return null;
    }
    adm_with_lock('users', function () use ($username, $password) {
        $users = adm_users();
        if (!isset($users[$username])) {
            return;
        }
        if (password_needs_rehash($users[$username]['hash'], PASSWORD_DEFAULT)) {
            $users[$username]['hash'] = adm_hash_password($password);
        }
        $users[$username]['last_login'] = date('c');
        adm_users_save($users);
    });
    return adm_user_get($username);
}

function adm_user_create(string $username, string $name, string $role, string $password): array
{
    $username = strtolower(trim($username));
    if (($p = adm_username_problem($username)) !== '') {
        throw new AdmUserError($p);
    }
    if (!isset(adm_roles()[$role])) {
        throw new AdmUserError('الدور غير معروف.');
    }
    if (($p = adm_password_problem($password, $username)) !== '') {
        throw new AdmUserError($p);
    }
    return adm_with_lock('users', function () use ($username, $name, $role, $password) {
        $users = adm_users();
        if (isset($users[$username])) {
            throw new AdmUserError('اسم المستخدم مستخدم بالفعل، اختر اسمًا آخر.');
        }
        $now = date('c');
        $users[$username] = [
            'username' => $username,
            'name' => $name,
            'role' => $role,
            'hash' => adm_hash_password($password),
            'pwv' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'last_login' => '',
        ];
        adm_users_save($users);
        return $users[$username];
    });
}

/** Change name and/or role. Refuses to remove the last admin. */
function adm_user_update(string $username, array $changes): array
{
    return adm_with_lock('users', function () use ($username, $changes) {
        $users = adm_users();
        if (!isset($users[$username])) {
            throw new AdmUserError('المستخدم غير موجود.');
        }
        $u = $users[$username];
        if (array_key_exists('name', $changes)) {
            $u['name'] = (string) $changes['name'];
        }
        if (array_key_exists('role', $changes)) {
            $role = (string) $changes['role'];
            if (!isset(adm_roles()[$role])) {
                throw new AdmUserError('الدور غير معروف.');
            }
            if ($u['role'] === 'admin' && $role !== 'admin' && adm_admin_count($users) <= 1) {
                throw new AdmUserError('لا يمكن تغيير دور آخر مدير؛ أضف مديرًا آخر أولًا.');
            }
            $u['role'] = $role;
        }
        $u['updated_at'] = date('c');
        $users[$username] = $u;
        adm_users_save($users);
        return $u;
    });
}

function adm_user_set_password(string $username, string $password): void
{
    if (($p = adm_password_problem($password, $username)) !== '') {
        throw new AdmUserError($p);
    }
    adm_with_lock('users', function () use ($username, $password) {
        $users = adm_users();
        if (!isset($users[$username])) {
            throw new AdmUserError('المستخدم غير موجود.');
        }
        $users[$username]['hash'] = adm_hash_password($password);
        $users[$username]['pwv'] = (int) $users[$username]['pwv'] + 1;
        $users[$username]['updated_at'] = date('c');
        adm_users_save($users);
    });
}

function adm_user_delete(string $username): void
{
    adm_with_lock('users', function () use ($username) {
        $users = adm_users();
        if (!isset($users[$username])) {
            throw new AdmUserError('المستخدم غير موجود.');
        }
        if ($users[$username]['role'] === 'admin' && adm_admin_count($users) <= 1) {
            throw new AdmUserError('لا يمكن حذف آخر مدير.');
        }
        unset($users[$username]);
        adm_users_save($users);
    });
}

function adm_admin_count(array $users): int
{
    $n = 0;
    foreach ($users as $u) {
        if (($u['role'] ?? '') === 'admin') {
            $n++;
        }
    }
    return $n;
}

/** Display name, falling back to the username. */
function adm_user_label(?array $user): string
{
    if ($user === null) {
        return '';
    }
    return trim((string) $user['name']) !== '' ? (string) $user['name'] : (string) $user['username'];
}
