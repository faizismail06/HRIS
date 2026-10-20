<?php

namespace App\Console\Commands;

use App\Domain\Identity\AccountAccess;
use App\Models\User;
use Illuminate\Console\Command;

class RevokeAccount extends Command
{
    protected $signature = 'hris:revoke-account {user : ID akun target} {--actor= : ID admin platform penanggung jawab}';

    protected $description = 'Nonaktifkan akun, cabut grants dan batalkan semua sesi melalui terminal tepercaya';

    public function handle(): int
    {
        app(AccountAccess::class)->replace(User::findOrFail($this->option('actor')), User::findOrFail($this->argument('user')), [], false);
        $this->info('Akun dinonaktifkan dan sesi dicabut.');

        return self::SUCCESS;
    }
}
