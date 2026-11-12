<?php

namespace App\Enums;

enum MediaPurpose: string
{
    case Image = 'image';
    case File = 'file';
    case AccountAvatar = 'account_avatar';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::File => 'PDF file',
            self::AccountAvatar => 'Account avatar',
        };
    }

    public function retainsIndefinitely(): bool
    {
        return $this === self::AccountAvatar;
    }
}
