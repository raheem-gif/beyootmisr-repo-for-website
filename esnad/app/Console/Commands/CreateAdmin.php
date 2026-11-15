<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Creates a super admin. Section 5 allows two named people only. */
class CreateAdmin extends Command
{
    protected $signature = 'esnad:create-admin {email} {--name=} {--password=}';

    protected $description = 'Create a super admin account (two-factor setup is forced at first sign-in)';

    public function handle(): int
    {
        $count = User::role('super_admin')->count();
        if ($count >= 2 && ! $this->confirm("There are already {$count} super admins. Section 5 allows two. Continue?")) {
            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Password (12 characters or more, letters and numbers)');
        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::default()]]
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'email' => $this->argument('email'),
            'name_en' => $this->option('name') ?? $this->argument('email'),
            'password' => $password,
            'status' => 'active',
        ]);
        $user->assignRole('super_admin');
        Audit::record('users.super_admin_created', $user, null, ['email' => $user->email]);

        $this->info("Super admin {$user->email} created. Sign in at /admin and set up the authenticator app.");

        return self::SUCCESS;
    }
}
