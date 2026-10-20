<?php

namespace App\Filament\Resources\SocialPosts\Pages;

use App\Filament\Resources\SocialPosts\SocialPostResource;
use Filament\Resources\Pages\ListRecords;

class ListSocialPosts extends ListRecords
{
    protected static string $resource = SocialPostResource::class;
}
