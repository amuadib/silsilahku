<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:register';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pendaftara pengguna baru';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Pendaftaran Pengguna Baru ===');
        $this->newLine();

        // Nama
        $name = $this->ask('Nama lengkap');

        if (! $name) {
            $this->error('Nama tidak boleh kosong.');
            return self::FAILURE;
        }

        // Email
        $email = $this->ask('Email');

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'unique:users,email']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        // Password
        $password = $this->secret('Password (min. 8 karakter)');

        if (! $password || strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');
            return self::FAILURE;
        }

        $passwordConfirm = $this->secret('Konfirmasi password');

        if ($password !== $passwordConfirm) {
            $this->error('Konfirmasi password tidak cocok.');
            return self::FAILURE;
        }

        // Konfirmasi
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Nama', $name],
                ['Email', $email],
                ['Password', $password],
            ]
        );

        if (! $this->confirm('Simpan user baru ini?', true)) {
            $this->warn('Pendaftaran dibatalkan.');
            return self::FAILURE;
        }

        // Simpan user
        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password, // otomatis di-hash via cast 'hashed'
        ]);

        $this->newLine();
        $this->info("✓ User berhasil didaftarkan!");
        $this->line("  ID    : {$user->id}");
        $this->line("  Nama  : {$user->name}");
        $this->line("  Email : {$user->email}");

        return self::SUCCESS;
    }
}
