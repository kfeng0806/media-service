<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTemporaryAudioUploadJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $uploadSessionId,
    ) {}

    public function handle(): void
    {
        //
    }
}
