<?php

namespace App\Support;

/** Roles of section 5 and the permissions each one starts with. */
final class Roles
{
    public const PERMISSIONS = [
        'users.view', 'users.manage', 'roles.manage',
        'audit.view', 'consents.view', 'access_logs.view', 'legal_requests.manage',
        'settings.manage', 'feature_flags.manage', 'pages.manage', 'governorates.manage',
        'install.view',
    ];

    /** role => permissions. super_admin passes every check through Gate::before. */
    public const ROLES = [
        'client' => [],
        'business_client' => [],
        'business_member' => [],
        'lawyer' => [],
        'trainee_lawyer' => [],
        'firm_admin' => [],
        'office_staff' => [],
        'legal_aid_partner' => [],
        'support_agent' => ['users.view'],
        'verification_officer' => ['users.view'],
        'dispatch_operator' => ['users.view'],
        'finance_officer' => ['users.view'],
        'content_editor' => ['pages.manage', 'governorates.manage'],
        'data_protection_officer' => ['users.view', 'consents.view', 'audit.view', 'access_logs.view', 'legal_requests.manage'],
        'super_admin' => self::PERMISSIONS,
    ];
}
