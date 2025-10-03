<x-filament-panels::page>
    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Notifications --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Notifications</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                        {{ number_format($stats['total']) }}
                    </p>
                </div>
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Sent Today --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Sent Today</p>
                    <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-2">
                        {{ number_format($stats['sent_today']) }}
                    </p>
                </div>
                <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Scheduled --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Scheduled</p>
                    <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-2">
                        {{ number_format($stats['scheduled']) }}
                    </p>
                </div>
                <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Total Delivered --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Delivered</p>
                    <p class="text-3xl font-bold text-purple-600 dark:text-purple-400 mt-2">
                        {{ number_format($stats['total_delivered']) }}
                    </p>
                </div>
                <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Two Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Sent Notifications --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Recently Sent
                </h3>
            </div>
            <div class="p-6">
                @forelse($recentNotifications as $notification)
                <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700 last:border-0">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $notification->title }}</h4>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $notification->sent_at->diffForHumans() }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ Str::limit($notification->body, 100) }}</p>
                    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            {{ number_format($notification->success_count) }} delivered
                        </span>
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            by {{ $notification->creator->name }}
                        </span>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-500 dark:text-gray-400 py-8">No notifications sent yet</p>
                @endforelse

                @if($recentNotifications->isNotEmpty())
                <div class="mt-4 text-center">
                    <a href="{{ route('filament.admin.resources.push-notifications.index', ['tableFilters' => ['status' => ['value' => 'sent']]]) }}"
                        class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium">
                        View all sent notifications →
                    </a>
                </div>
                @endif
            </div>
        </div>

        {{-- Upcoming Scheduled Notifications --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Upcoming Scheduled
                </h3>
            </div>
            <div class="p-6">
                @forelse($scheduledNotifications as $notification)
                <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700 last:border-0">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $notification->title }}</h4>
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                            {{ $notification->scheduled_at->format('M j, H:i') }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ Str::limit($notification->body, 100) }}</p>
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ $notification->time_until_send }}
                        </span>
                        <span>by {{ $notification->creator->name }}</span>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-500 dark:text-gray-400 py-8">No scheduled notifications</p>
                @endforelse

                @if($scheduledNotifications->isNotEmpty())
                <div class="mt-4 text-center">
                    <a href="{{ route('filament.admin.resources.push-notifications.index', ['tableFilters' => ['status' => ['value' => 'scheduled']]]) }}"
                        class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium">
                        View all scheduled notifications →
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-6 bg-gradient-to-r from-primary-50 to-primary-100 dark:from-gray-800 dark:to-gray-700 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('filament.admin.resources.push-notifications.create') }}"
                class="flex items-center p-4 bg-white dark:bg-gray-800 rounded-lg hover:shadow-md transition">
                <div class="bg-primary-100 dark:bg-primary-900 p-3 rounded-full mr-4">
                    <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Create New</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Send or schedule notification</p>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.push-notifications.index') }}"
                class="flex items-center p-4 bg-white dark:bg-gray-800 rounded-lg hover:shadow-md transition">
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full mr-4">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">View All</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Manage all notifications</p>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.push-notifications.index', ['tableFilters' => ['status' => ['value' => 'scheduled']]]) }}"
                class="flex items-center p-4 bg-white dark:bg-gray-800 rounded-lg hover:shadow-md transition">
                <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full mr-4">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Scheduled</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">View scheduled notifications</p>
                </div>
            </a>
        </div>
    </div>
</x-filament-panels::page>