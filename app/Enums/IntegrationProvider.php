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

    /**
     * The provider whose OAuth flow actually grants this capability.
     *
     * There is no separate Google Calendar connection: one Google authorisation
     * covers Gmail, Calendar and the Meet links Calendar mints. Listing them as
     * separate connectable tiles produced links to a connect route that only
     * ever accepted gmail and microsoft, so they 404'd.
     */
    public function connectsVia(): ?self
    {
        return match ($this) {
            self::Gmail, self::GoogleCalendar, self::GoogleMeet => self::Gmail,
            self::Microsoft => self::Microsoft,
            // Shared-credential providers: configured by an administrator in the
            // environment, never through a per-user OAuth handshake.
            self::TwilioSms, self::WhatsAppCloud => null,
            self::Zoom => null,
        };
    }

    /** Whether this is the tile a person clicks to start an OAuth handshake. */
    public function isConnectable(): bool
    {
        return $this->connectsVia() === $this;
    }

    /**
     * Why a tile cannot be clicked, in words a user can act on. Null when it can.
     */
    public function unavailableReason(): ?string
    {
        if ($this->isConnectable()) {
            return null;
        }

        return match ($this) {
            self::GoogleCalendar, self::GoogleMeet => 'Included when you connect Gmail',
            self::TwilioSms, self::WhatsAppCloud => 'Set up by your administrator',
            default => 'Coming soon',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Gmail => 'Gmail & Google Calendar',
            self::Microsoft => 'Microsoft 365',
            self::GoogleCalendar => 'Google Calendar',
            self::GoogleMeet => 'Google Meet',
            self::Zoom => 'Zoom',
            self::TwilioSms => 'Twilio SMS',
            self::WhatsAppCloud => 'WhatsApp',
        };
    }
}
