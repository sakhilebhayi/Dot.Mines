<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateAccount;
use App\Models\User;
use App\Support\ApiPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * The documented way to add a person now that public registration is closed:
 * the same account an ordinary sign-up makes (user, personal team, admin role
 * on it), with a random password nobody sees. The person sets their own through
 * the password-reset link this sends, so no credential is typed, printed or
 * stored anywhere by the operator.
 */
class CreateUser extends Command
{
    protected $signature = 'user:create
        {email : The person\'s email address}
        {name : Their full name}
        {--no-mail : Create the account but do not send the set-password link}';

    protected $description = 'Create an account (registration is closed) and email the person a link to set their own password';

    public function handle(CreateAccount $createAccount): int
    {
        $input = [
            'email' => ApiPayload::str($this->argument('email')),
            'name' => ApiPayload::str($this->argument('name')),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $createAccount->execute($input['name'], $input['email']);

        $this->info('Created '.$user->email.' (user #'.$user->id.').');

        if ($this->option('no-mail')) {
            $this->line('No email sent: use the "Forgot password" page for this address when they are ready.');

            return self::SUCCESS;
        }

        $status = Password::broker()->sendResetLink(['email' => $user->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            $this->warn('Account created, but the set-password email was not sent ('.$status.'). Ask them to use "Forgot password".');

            return self::FAILURE;
        }

        $this->line('Set-password link emailed.');

        return self::SUCCESS;
    }
}
