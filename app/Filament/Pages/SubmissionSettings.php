<?php

namespace App\Filament\Pages;

use App\Models\SubmissionSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SubmissionSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.submission-settings';

    protected static ?string $navigationLabel = 'إعدادات القصص';

    protected static ?string $title = 'إعدادات إرسال القصص';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'guide_text' => SubmissionSetting::getGuideText(),
            'terms_text' => SubmissionSetting::getTermsText(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('نص الإرشادات')
                    ->schema([
                        Forms\Components\RichEditor::make('guide_text')
                            ->label('نص الإرشادات')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('الشروط والأحكام')
                    ->schema([
                        Forms\Components\RichEditor::make('terms_text')
                            ->label('نص الشروط والأحكام')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SubmissionSetting::updateGuideText($data['guide_text']);
        SubmissionSetting::updateTermsText($data['terms_text']);

        Notification::make()
            ->title('تم الحفظ بنجاح')
            ->success()
            ->send();
    }
}
