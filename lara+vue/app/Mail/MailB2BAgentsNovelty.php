<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailB2BAgentsNovelty extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new message instance.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.novelty_created_to_b2b_agents')
            ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
            ->subject($this->data['header'])
            ->with([
                'header' => $this->data['header'],
                'text' => $this->data['preview'],
                'link' => $this->data['link'],
                'published' => $this->data['published_at'],
            ]);
    }
}
