<?php

namespace App\Filament\Resources\SearchTerms\Pages;

use App\Filament\Resources\SearchTerms\SearchTermResource;
use Filament\Resources\Pages\ListRecords;

class ListSearchTerms extends ListRecords
{
    protected static string $resource = SearchTermResource::class;
}
