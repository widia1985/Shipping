<?php

namespace Widia\Shipping\Console\Commands;

use Illuminate\Console\Command;
use Widia\Shipping\Models\ApiToken;

class ListApiTokens extends Command
{
    protected $signature = 'shipping:list-tokens {--user= : Filter by user email}';
    protected $description = 'List all API tokens';

    public function handle()
    {
        $query = ApiToken::with('user');

        if ($userEmail = $this->option('user')) {
            $query->whereHas('user', function ($q) use ($userEmail) {
                $q->where('email', $userEmail);
            });
        }

        $tokens = $query->get();

        if ($tokens->isEmpty()) {
            $this->info('No API tokens found.');
            return 0;
        }

        $this->table(
            ['ID', 'User', 'Name', 'Created', 'Expires', 'Last Used', 'Status'],
            $tokens->map(function ($token) {
                return [
                    $token->id,
                    $token->user->email,
                    $token->name,
                    $token->created_at->format('Y-m-d H:i:s'),
                    $token->expires_at ? $token->expires_at->format('Y-m-d H:i:s') : 'Never',
                    $token->last_used_at ? $token->last_used_at->format('Y-m-d H:i:s') : 'Never',
                    $token->isValid() ? 'Valid' : 'Expired'
                ];
            })
        );

        return 0;
    }
} 