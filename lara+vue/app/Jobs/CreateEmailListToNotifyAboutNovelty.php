<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;

class CreateEmailListToNotifyAboutNovelty implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $users;

    /**
     * Create a new job instance.
     * CreateEmailListToNotifyAboutNovelty constructor.
     * @param $data
     * @param Collection $users
     */
    public function __construct(array $data, Collection $users)
    {
        $this->data = $data;
        $this->users = $users;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $date = new Carbon($this->data['published_at']);
        $this->data['published_at'] = $date->format('d.m.Y');
        foreach ($this->users as $user) {
            if(preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', $user->email)) {
                $this->data['email'] = $user->email;
                NotifyB2BAgentsNoveltyAdded::dispatch($this->data);
            }
        }
    }
}
