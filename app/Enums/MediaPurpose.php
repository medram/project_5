<?php

namespace App\Enums;

enum MediaPurpose: string
{
    case Image = 'image';
    case File = 'file';
    case AccountAvatar = 'account_avatar';
    case PlatformAsset = 'platform_asset';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::File => 'PDF file',
            self::AccountAvatar => 'Account avatar',
            self::PlatformAsset => 'Platform asset',
        };
    }

    public function retainsIndefinitely(): bool
    {
        return in_array($this, [self::AccountAvatar, self::PlatformAsset], true);
    }
}
