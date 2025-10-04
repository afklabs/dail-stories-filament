<?php

namespace App\Filament\Resources\MemberStorySubmissionResource\Pages;

use App\Filament\Resources\MemberStorySubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMemberStorySubmission extends EditRecord
{
    protected static string $resource = MemberStorySubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
