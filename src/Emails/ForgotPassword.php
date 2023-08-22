<?php

namespace Encore\Admin\Emails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;

class ForgotPassword implements Mailable
{
    public $email;
    public $token;

    public function __construct($email, $token) {
        $this->email = $email;
        $this->token = $token;
    }

    public function content(): Content
    {
        $url = config('admin.route.base_url') . '/' . config('admin.route.prefix') . '/auth/reset-password?email=' . $this->email . '&token=' . $this->token;

        return new Content(
            view: 'admin::emails.forgot_password',
            with: [
                'email' => $this->email,
                'url' => $url,
            ],
        );
    }
}