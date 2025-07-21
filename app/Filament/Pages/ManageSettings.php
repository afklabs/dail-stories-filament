<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Cache;

class ManageSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static string $view = 'filament.pages.manage-settings';
    
    protected static ?string $navigationGroup = 'System';
    
    protected static ?int $navigationSort = 99;
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $this->loadSettings();
    }
    
    protected function loadSettings(): void
    {
        $this->data = [
            // Story Settings
            'story_active_from_default' => Setting::get('story_active_from_default', 'now'),
            'story_active_until_hours' => Setting::get('story_active_until_hours', 24),
            
            // Cache Settings
            'cache_default_duration' => Setting::get('cache.default_duration', 900),
            'cache_analytics_duration' => Setting::get('cache.analytics_duration', 1800),
            'cache_dashboard_duration' => Setting::get('cache.dashboard_duration', 300),
            'cache_enabled' => Setting::get('cache.enabled', true),
            
            // Performance Settings
            'performance_query_cache' => Setting::get('performance.query_cache', true),
            'performance_image_optimization' => Setting::get('performance.image_optimization', true),
            'performance_lazy_loading' => Setting::get('performance.lazy_loading', true),
            'performance_compression' => Setting::get('performance.compression', true),
            
            // Security Settings
            'security_rate_limiting' => Setting::get('security.rate_limiting', true),
            'security_audit_logging' => Setting::get('security.audit_logging', true),
            'security_2fa_required' => Setting::get('security.2fa_required', false),
            'security_session_timeout' => Setting::get('security.session_timeout', 120),
            
            // Notification Settings
            'notifications_new_member' => Setting::get('notifications.new_member', true),
            'notifications_story_approval' => Setting::get('notifications.story_approval', true),
            'notifications_low_engagement' => Setting::get('notifications.low_engagement', true),
            'notifications_expiry_warning' => Setting::get('notifications.expiry_warning', true),
        ];
        
        $this->form->fill($this->data);
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Story Defaults')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Section::make('Default Values')
                                    ->description('Configure default values for new stories')
                                    ->schema([
                                        Forms\Components\Select::make('story_active_from_default')
                                            ->label('Active From Default')
                                            ->options([
                                                'now' => 'Now (Current Date & Time)',
                                                'today' => 'Today (Current Date at 00:00)',
                                            ])
                                            ->helperText('Choose the default value for "Active From" when creating new stories')
                                            ->required(),
                                            
                                        Forms\Components\TextInput::make('story_active_until_hours')
                                            ->label('Story Active Duration (Hours)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(8760)
                                            ->suffix('hours')
                                            ->helperText('Number of hours to add to "Active From" date (1-8760 hours)')
                                            ->hint('Examples: 24 hours = 1 day, 168 hours = 1 week')
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Cache')
                            ->icon('heroicon-o-bolt')
                            ->schema([
                                Forms\Components\Section::make('Cache Configuration')
                                    ->description('Configure caching settings for better performance')
                                    ->schema([
                                        Forms\Components\Toggle::make('cache_enabled')
                                            ->label('Enable Caching')
                                            ->columnSpanFull(),
                                            
                                        Forms\Components\TextInput::make('cache_default_duration')
                                            ->label('Default Cache Duration')
                                            ->numeric()
                                            ->minValue(60)
                                            ->maxValue(86400)
                                            ->suffix('seconds')
                                            ->helperText('Default cache duration (60-86400 seconds)'),
                                            
                                        Forms\Components\TextInput::make('cache_analytics_duration')
                                            ->label('Analytics Cache Duration')
                                            ->numeric()
                                            ->minValue(60)
                                            ->maxValue(86400)
                                            ->suffix('seconds')
                                            ->helperText('Analytics cache duration'),
                                            
                                        Forms\Components\TextInput::make('cache_dashboard_duration')
                                            ->label('Dashboard Cache Duration')
                                            ->numeric()
                                            ->minValue(60)
                                            ->maxValue(86400)
                                            ->suffix('seconds')
                                            ->helperText('Dashboard widgets cache duration'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Performance')
                            ->icon('heroicon-o-rocket-launch')
                            ->schema([
                                Forms\Components\Section::make('Performance Optimization')
                                    ->description('Configure performance optimization settings')
                                    ->schema([
                                        Forms\Components\Toggle::make('performance_query_cache')
                                            ->label('Enable Query Caching'),
                                            
                                        Forms\Components\Toggle::make('performance_image_optimization')
                                            ->label('Enable Image Optimization'),
                                            
                                        Forms\Components\Toggle::make('performance_lazy_loading')
                                            ->label('Enable Lazy Loading'),
                                            
                                        Forms\Components\Toggle::make('performance_compression')
                                            ->label('Enable Compression'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Security')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Section::make('Security Settings')
                                    ->description('Configure security settings')
                                    ->schema([
                                        Forms\Components\Toggle::make('security_rate_limiting')
                                            ->label('Enable Rate Limiting'),
                                            
                                        Forms\Components\Toggle::make('security_audit_logging')
                                            ->label('Enable Audit Logging'),
                                            
                                        Forms\Components\Toggle::make('security_2fa_required')
                                            ->label('Require Two-Factor Authentication'),
                                            
                                        Forms\Components\TextInput::make('security_session_timeout')
                                            ->label('Session Timeout')
                                            ->numeric()
                                            ->minValue(15)
                                            ->maxValue(480)
                                            ->suffix('minutes')
                                            ->helperText('Session timeout in minutes (15-480)'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Notifications')
                            ->icon('heroicon-o-bell')
                            ->schema([
                                Forms\Components\Section::make('Notification Settings')
                                    ->description('Configure notification preferences')
                                    ->schema([
                                        Forms\Components\Toggle::make('notifications_new_member')
                                            ->label('New Member Registration'),
                                            
                                        Forms\Components\Toggle::make('notifications_story_approval')
                                            ->label('Story Needs Approval'),
                                            
                                        Forms\Components\Toggle::make('notifications_low_engagement')
                                            ->label('Low Story Engagement'),
                                            
                                        Forms\Components\Toggle::make('notifications_expiry_warning')
                                            ->label('Story Expiry Warning'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
            ])
            ->statePath('data');
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
                
            Action::make('clearCache')
                ->label('Clear All Cache')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->action('clearCache'),
        ];
    }
    
    public function save(): void
    {
        try {
            $data = $this->form->getState();
            
            // Story Settings
            Setting::set('story_active_from_default', $data['story_active_from_default']);
            Setting::set('story_active_until_hours', $data['story_active_until_hours']);
            
            // Cache Settings
            Setting::set('cache.default_duration', $data['cache_default_duration']);
            Setting::set('cache.analytics_duration', $data['cache_analytics_duration']);
            Setting::set('cache.dashboard_duration', $data['cache_dashboard_duration']);
            Setting::set('cache.enabled', $data['cache_enabled']);
            
            // Performance Settings
            Setting::set('performance.query_cache', $data['performance_query_cache']);
            Setting::set('performance.image_optimization', $data['performance_image_optimization']);
            Setting::set('performance.lazy_loading', $data['performance_lazy_loading']);
            Setting::set('performance.compression', $data['performance_compression']);
            
            // Security Settings
            Setting::set('security.rate_limiting', $data['security_rate_limiting']);
            Setting::set('security.audit_logging', $data['security_audit_logging']);
            Setting::set('security.2fa_required', $data['security_2fa_required']);
            Setting::set('security.session_timeout', $data['security_session_timeout']);
            
            // Notification Settings
            Setting::set('notifications.new_member', $data['notifications_new_member']);
            Setting::set('notifications.story_approval', $data['notifications_story_approval']);
            Setting::set('notifications.low_engagement', $data['notifications_low_engagement']);
            Setting::set('notifications.expiry_warning', $data['notifications_expiry_warning']);
            
            Notification::make()
                ->title('Settings saved successfully')
                ->success()
                ->send();
                
        } catch (Halt $exception) {
            return;
        }
    }
    
    public function clearCache(): void
    {
        Cache::flush();
        Setting::clearCache();
        
        Notification::make()
            ->title('Cache cleared successfully')
            ->success()
            ->send();
    }
}
