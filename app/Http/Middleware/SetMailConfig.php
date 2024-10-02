<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use App\Models\MailSetting;

class SetMailConfig
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if($user->role == 'user'){
                $businessId = $request->header('X-Business-ID');

            // Retrieve mail settings for the user's business
            $mailSetting = MailSetting::first();

            if ($mailSetting) {
                $mailConfig = [
                    'default' => $mailSetting->mail_mailer,
                    'mailers' => [
                        $mailSetting->mail_mailer => [
                            'transport' => $mailSetting->mail_mailer,
                            'host' => $mailSetting->mail_host,
                            'port' => $mailSetting->mail_port,
                            'encryption' => $mailSetting->mail_encryption,
                            'username' => $mailSetting->mail_username,
                            'password' => $mailSetting->mail_password,
                            'timeout' => null,
                            'auth_mode' => null,
                        ],
                    ],
                    'from' => [
                        'address' => $mailSetting->mail_from,
                        'name' => $mailSetting->mail_from_name ?? 'Eeman ERP',
                    ],
                ];

                // Merge with existing mail configuration
                Config::set('mail', array_merge(Config::get('mail'), $mailConfig));
            }
            }
            else{
                $mailConfig = [
                    'default' => 'smtp',
                    'mailers' => [
                        'smtp' => [
                            'transport' => 'smtp',
                            'host' => 'smtp.gmail.com',
                            'port' => 587,
                            'encryption' => 'tls',
                            'username' => 'huzaifa.zetdigi@gmail.com',
                            'password' => 'ucls yjgg scti zlgu',
                            'timeout' => null,
                            'auth_mode' => null,
                        ],
                    ],
                    'from' => [
                        'address' => 'huzaifa.zetdigi@gmail.com',
                        'name' => 'Eeman ERP',
                    ],
                ];
            }
        }

        return $next($request);
    }
}
