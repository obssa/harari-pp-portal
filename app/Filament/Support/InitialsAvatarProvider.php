<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff avatars drawn locally as an SVG with the person's initials (ADR-0010).
 * Filament's default sends every staff name to ui-avatars.com and is blocked
 * by the admin Content-Security-Policy; this keeps names on the server.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $words = preg_split('/\s+/u', trim((string) Filament::getNameForDefaultAvatar($record))) ?: [];

        $initials = '';
        foreach (array_slice(array_values(array_filter($words)), 0, 2) as $word) {
            $letters = (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $word);
            $initials .= mb_strtoupper(mb_substr($letters, 0, 1));
        }

        $text = htmlspecialchars($initials !== '' ? $initials : '?', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#2539c9"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="Inter, \'Noto Sans Ethiopic\', sans-serif" font-size="26" font-weight="600">'
            .$text.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
