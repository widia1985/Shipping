<?php

namespace Widia\Shipping\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class CreateApiToken extends Command
{
    protected $signature = 'shipping:create-token 
                            {email : User email}
                            {--name=API Token : Token name}
                            {--expires= : Token expiration date (optional)}';

    protected $description = 'Create an API token for shipping service';

    public function handle()
    {
        $email = $this->argument('email');
        $tokenName = $this->option('name');
        $expires = $this->option('expires');

        // 查找用户
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        // 生成 token
        $token = Str::random(60);
        $hashedToken = Hash::make($token);

        // 保存 token 到数据库
        $apiToken = \App\Models\ApiToken::create([
            'user_id' => $user->id,
            'name' => $tokenName,
            'token' => $hashedToken,
            'expires_at' => $expires ? \Carbon\Carbon::parse($expires) : null,
        ]);

        $this->info("API Token created successfully!");
        $this->line("Token: {$token}");
        $this->line("Name: {$tokenName}");
        $this->line("User: {$user->name} ({$user->email})");
        
        if ($expires) {
            $this->line("Expires: {$expires}");
        }

        return 0;
    }
} 