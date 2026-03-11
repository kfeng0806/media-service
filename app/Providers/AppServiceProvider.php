<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureMorphMap();
    }

    protected function configureMorphMap(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'post' => Post::class,
            'thread' => Thread::class,
        ]);
    }
}
