<?php

namespace App\Enums;

enum PlanMetric: string
{
    case Links = 'links';
    case Folders = 'folders';
    case QrCampaigns = 'qr_campaigns';
    case BioPages = 'bio_pages';
    case Domains = 'domains';
    case Pixels = 'pixels';
    case Members = 'members';
    case Webhooks = 'webhooks';
    case LinkPages = 'link_pages';
    case ApiKeys = 'api_keys';
    case StorageBytes = 'storage_bytes';

    public function label(): string
    {
        return match ($this) {
            self::Links => 'Short links',
            self::Folders => 'Folders',
            self::QrCampaigns => 'Direct QR campaigns',
            self::BioPages => 'Bio pages',
            self::Domains => 'Custom domains',
            self::Pixels => 'Tracking pixels',
            self::Members => 'Workspace members',
            self::Webhooks => 'Webhooks',
            self::LinkPages => 'Link pages',
            self::ApiKeys => 'Active API keys',
            self::StorageBytes => 'Retained media storage',
        };
    }

    public function planColumn(): string
    {
        return match ($this) {
            self::Links => 'link_limit',
            self::Folders => 'folder_limit',
            self::QrCampaigns => 'qr_campaign_limit',
            self::BioPages => 'bio_page_limit',
            self::Domains => 'domain_limit',
            self::Pixels => 'pixel_limit',
            self::Members => 'member_limit',
            self::Webhooks => 'webhook_limit',
            self::LinkPages => 'link_page_limit',
            self::ApiKeys => 'api_key_limit',
            self::StorageBytes => 'storage_byte_limit',
        };
    }

    public function isByteMetric(): bool
    {
        return $this === self::StorageBytes;
    }
}
