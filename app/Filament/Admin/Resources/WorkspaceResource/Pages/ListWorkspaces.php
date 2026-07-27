<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WorkspaceResource\Pages;

use App\Filament\Admin\Resources\WorkspaceResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaces extends ListRecords
{
    protected static string $resource = WorkspaceResource::class;
}
