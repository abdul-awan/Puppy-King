<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('platform:admin
    {email? : The e-mail address of the account}
    {--revoke : Take the platform admin rights away instead}
    {--list : Show the platform administrators}')]
#[Description('Create a platform administrator account, or give (or take) platform admin rights to an existing account')]
class PlatformAdminCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command.
     *
     * An existing account simply gets (or loses) the rights. A new account asks for a name and
     * a password in the terminal (the password is typed hidden and never shown), like signing up:
     * it gets its own company workspace and a verified e-mail address.
     */
    public function handle(CreateTeam $createTeam): int
    {
        if ($this->option('list')) {
            return $this->list();
        }

        $email = strtolower(trim((string) ($this->argument('email') ?? $this->ask('E-mail address of the platform administrator'))));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->components->error("\"{$email}\" is not a valid e-mail address.");

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($this->option('revoke')) {
            return $this->revoke($user, $email);
        }

        if ($user !== null) {
            if ($user->isPlatformAdmin()) {
                $this->components->info("{$user->name} <{$user->email}> is already a platform administrator.");

                return self::SUCCESS;
            }

            $user->forceFill(['is_platform_admin' => true])->save();
            $this->components->info("{$user->name} <{$user->email}> is now a platform administrator. After logging in, open /admin.");

            return self::SUCCESS;
        }

        return $this->createAccount($createTeam, $email);
    }

    /**
     * Create a new account with platform admin rights.
     */
    protected function createAccount(CreateTeam $createTeam, string $email): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error("There is no account for {$email}. Run this command in a terminal to create one: it asks for a name and a password.");

            return self::FAILURE;
        }

        $this->components->info("There is no account for {$email} yet; a new one will be created.");

        $input = [
            'name' => trim((string) $this->ask('Name')),
            'email' => $email,
            'password' => (string) $this->secret('Password'),
            'password_confirmation' => (string) $this->secret('Password again'),
        ];

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($createTeam, $input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->forceFill(['email_verified_at' => now(), 'is_platform_admin' => true])->save();

            // Like every account it gets its own company workspace (where it lands after logging in).
            $createTeam->handle($user, $user->name."'s Team", isPersonal: true);

            return $user;
        });

        $this->components->info("Platform administrator {$user->name} <{$user->email}> created. Log in with this e-mail address and open /admin.");

        return self::SUCCESS;
    }

    /**
     * Take the platform admin rights away (never from the last administrator).
     */
    protected function revoke(?User $user, string $email): int
    {
        if ($user === null || ! $user->isPlatformAdmin()) {
            $this->components->error("{$email} is not a platform administrator.");

            return self::FAILURE;
        }

        if (User::query()->where('is_platform_admin', true)->count() <= 1) {
            $this->components->error('This is the last platform administrator. Make someone else an administrator first.');

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => false])->save();
        $this->components->info("{$user->name} <{$user->email}> is no longer a platform administrator.");

        return self::SUCCESS;
    }

    /**
     * Show the platform administrators.
     */
    protected function list(): int
    {
        $admins = User::query()->where('is_platform_admin', true)->orderBy('name')->get(['id', 'name', 'email']);

        if ($admins->isEmpty()) {
            $this->components->warn('There are no platform administrators yet. Run: php artisan platform:admin you@example.com');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'E-mail'], $admins->map(fn (User $user) => [$user->id, $user->name, $user->email])->all());

        return self::SUCCESS;
    }
}
