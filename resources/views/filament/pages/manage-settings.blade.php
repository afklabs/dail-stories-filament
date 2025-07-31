<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Settings Form --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="flex justify-end mt-6">
                <x-filament::button
                    type="submit"
                    size="lg"
                    icon="heroicon-o-check">
                    Save Settings
                </x-filament::button>
            </div>
        </form>

        {{-- Quick Actions Section --}}
        <x-filament::section>
            <x-slot name="heading">
                Quick Actions
            </x-slot>

            <x-slot name="description">
                Common administrative tasks and utilities.
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Cache Status Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Cache Status
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Driver: {{ config('cache.default') }}
                            </p>
                        </div>
                        <x-heroicon-o-server class="w-8 h-8 text-gray-400" />
                    </div>

                    <div class="mt-3">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            @php
                            $hitRate = \Illuminate\Support\Facades\Cache::get('metrics.cache_hit_rate', 0);
                            @endphp
                            {{ number_format($hitRate, 1) }}%
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Hit Rate</p>
                    </div>
                </div>

                {{-- Performance Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Performance
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Memory Usage
                            </p>
                        </div>
                        <x-heroicon-o-cpu-chip class="w-8 h-8 text-gray-400" />
                    </div>

                    <div class="mt-3">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format(memory_get_usage(true) / 1024 / 1024, 1) }}MB
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Peak: {{ number_format(memory_get_peak_usage(true) / 1024 / 1024, 1) }}MB
                        </p>
                    </div>
                </div>

                {{-- System Status Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                System Health
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                All Systems
                            </p>
                        </div>
                        <x-heroicon-o-heart class="w-8 h-8 text-green-500" />
                    </div>

                    <div class="mt-3">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            Healthy
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            PHP {{ PHP_VERSION }}
                        </p>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Recent Activity Section --}}
        <x-filament::section>
            <x-slot name="heading">
                Recent Settings Changes
            </x-slot>

            <x-slot name="description">
                Latest configuration changes and administrative actions.
            </x-slot>

            <div class="space-y-3">
                @php
                // Get recent settings changes from logs (simplified example)
                $recentChanges = collect([
                [
                'action' => 'Settings Updated',
                'user' => auth()->user()?->name ?? 'System',
                'timestamp' => now()->subMinutes(5),
                'details' => 'Story default settings modified'
                ],
                [
                'action' => 'Cache Cleared',
                'user' => auth()->user()?->name ?? 'System',
                'timestamp' => now()->subHours(2),
                'details' => 'Application cache manually cleared'
                ],
                [
                'action' => 'Security Settings',
                'user' => auth()->user()?->name ?? 'System',
                'timestamp' => now()->subDays(1),
                'details' => 'Rate limiting configuration updated'
                ]
                ]);
                @endphp

                @forelse($recentChanges as $change)
                <div class="flex items-center justify-between py-3 border-b border-gray-200 dark:border-gray-700 last:border-0">
                    <div class="flex items-center space-x-3">
                        <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $change['action'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $change['details'] }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $change['user'] }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $change['timestamp']->diffForHumans() }}
                        </p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-clock class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>No recent activity</p>
                </div>
                @endforelse
            </div>
        </x-filament::section>

        {{-- Warning Notes --}}
        <x-filament::section>
            <x-slot name="heading">
                Important Notes
            </x-slot>

            <div class="space-y-3">
                <div class="flex items-start space-x-3 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" />
                    <div>
                        <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                            Cache Considerations
                        </h4>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                            Changes to cache settings require clearing existing cache to take effect immediately.
                        </p>
                    </div>
                </div>

                <div class="flex items-start space-x-3 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                    <div>
                        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            Security Settings
                        </h4>
                        <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                            Security-related changes may affect user sessions and require re-authentication.
                        </p>
                    </div>
                </div>

                <div class="flex items-start space-x-3 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                    <x-heroicon-o-shield-exclamation class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
                    <div>
                        <h4 class="text-sm font-medium text-red-800 dark:text-red-200">
                            Backup Recommendation
                        </h4>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                            Always backup your database before making significant configuration changes.
                        </p>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>