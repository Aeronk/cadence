<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Gmail = 'gmail';
    case Microsoft = 'microsoft';
    case GoogleCalendar = 'google_calendar';
    case GoogleMeet = 'google_meet';
    case Zoom = 'zoom';
    case TwilioSms = 'twilio_sms';
    case WhatsAppCloud = 'whatsapp_cloud';

    public function channel(): MessageChannel
    {
        return match ($this) {
            self::Gmail, self::Microsoft => MessageChannel::Email,
            self::GoogleCalendar, self::GoogleMeet, self::Zoom => MessageChannel::Calendar,
            self::TwilioSms => MessageChannel::Sms,
            self::WhatsAppCloud => MessageChannel::WhatsApp,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Gmail => 'Gmail',
            self::Microsoft => 'Microsoft 365',
            self::GoogleCalendar => 'Google Calendar',
            self::GoogleMeet => 'Google Meet',
            self::Zoom => 'Zoom',
            self::TwilioSms => 'Twilio SMS',
            self::WhatsAppCloud => 'WhatsApp',
        };
    }
}
