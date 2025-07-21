{{-- resources/views/filament/pages/analytics-dashboard.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            {{ $this->form }}
        </div>

        {{-- Overview Metrics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @php
                $metrics = $this->getOverviewMetrics();
            @endphp

            {{-- Total Stories --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-md flex items-center justify-center">
                            <x-heroicon-o-document-text class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Stories</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['total_stories']) }}</p>
                    </div>
                </div>
            </div>

            {{-- Total Views --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-md flex items-center justify-center">
                            <x-heroicon-o-eye class="w-5 h-5 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Views</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['total_views']) }}</p>
                        <p class="text-xs text-gray-500">{{ number_format($metrics['unique_viewers']) }} unique</p>
                    </div>
                </div>
            </div>

            {{-- Engagement Rate --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-md flex items-center justify-center">
                            <x-heroicon-o-heart class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Engagement Rate</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $metrics['engagement_rate'] }}%</p>
                        <p class="text-xs text-gray-500">{{ number_format($metrics['total_interactions']) }} interactions</p>
                    </div>
                </div>
            </div>

            {{-- Average Rating --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-md flex items-center justify-center">
                            <x-heroicon-o-star class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Rating</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $metrics['average_rating'] }}/5</p>
                        <div class="flex items-center">
                            @for($i = 1; $i <= 5; $i++)
                                <x-heroicon-s-star class="w-3 h-3 {{ $i <= $metrics['average_rating'] ? 'text-yellow-400' : 'text-gray-300' }}" />
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Views Trend Chart --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Views Trend</h3>
                <div class="h-80">
                    <canvas id="viewsTrendChart"></canvas>
                </div>
            </div>

            {{-- Engagement Breakdown --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Engagement Breakdown</h3>
                <div class="h-80">
                    <canvas id="engagementChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Device Analytics & Rating Distribution --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Device Analytics --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Device Analytics</h3>
                @php
                    $deviceData = $this->getDeviceAnalytics();
                @endphp
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <x-heroicon-o-device-phone-mobile class="w-5 h-5 text-gray-400 mr-2" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Mobile</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($deviceData['mobile']) }}</span>
                            <span class="text-xs text-gray-500 ml-1">({{ $deviceData['mobile_percentage'] }}%)</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $deviceData['mobile_percentage'] }}%"></div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <x-heroicon-o-computer-desktop class="w-5 h-5 text-gray-400 mr-2" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Desktop</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($deviceData['desktop']) }}</span>
                            <span class="text-xs text-gray-500 ml-1">({{ $deviceData['desktop_percentage'] }}%)</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ $deviceData['desktop_percentage'] }}%"></div>
                    </div>
                </div>
            </div>

            {{-- Rating Distribution --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Rating Distribution</h3>
                <div class="h-64">
                    <canvas id="ratingChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Top Stories Table --}}
        @if (!$this->selectedStory)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Top Performing Stories</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Story</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Views</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rating</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ratings Count</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->getTopStories() as $story)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $story['title'] }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            {{ $story['category'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ number_format($story['views']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white mr-1">{{ number_format($story['rating'], 1) }}</span>
                                            <x-heroicon-s-star class="w-4 h-4 text-yellow-400" />
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ number_format($story['total_ratings']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Publishing Activity --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                @if ($this->selectedStory)
                    Story Publishing History
                @else
                    Publishing Activity
                @endif
            </h3>
            
            @if ($this->selectedStory)
                <div class="space-y-3">
                    @foreach ($this->getPublishingActivity() as $activity)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ ucfirst($activity['action']) }}</p>
                                    <p class="text-xs text-gray-500">by {{ $activity['user'] }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">{{ $activity['date'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach ($this->getPublishingActivity() as $action => $count)
                        <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($count) }}</p>
                            <p class="text-sm text-gray-500 capitalize">{{ str_replace('_', ' ', $action) }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Chart.js Scripts --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Views Trend Chart
            const viewsTrendData = @json($this->getViewTrends());
            
            new Chart(document.getElementById('viewsTrendChart'), {
                type: 'line',
                data: {
                    labels: viewsTrendData.map(d => d.date),
                    datasets: [
                        {
                            label: 'Total Views',
                            data: viewsTrendData.map(d => d.views),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.2,
                            fill: true
                        },
                        {
                            label: 'Unique Views',
                            data: viewsTrendData.map(d => d.unique_views),
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.2,
                            fill: true
                        },
                        {
                            label: 'Member Views',
                            data: viewsTrendData.map(d => d.member_views),
                            borderColor: 'rgb(245, 158, 11)',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            tension: 0.2,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Engagement Breakdown Chart
            const engagementData = @json($this->getEngagementBreakdown());
            
            new Chart(document.getElementById('engagementChart'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(engagementData).map(action => action.charAt(0).toUpperCase() + action.slice(1)),
                    datasets: [{
                        data: Object.values(engagementData),
                        backgroundColor: [
                            '#3B82F6', // blue
                            '#10B981', // green
                            '#F59E0B', // yellow
                            '#EF4444', // red
                            '#8B5CF6', // purple
                            '#F97316', // orange
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Rating Distribution Chart
            const ratingData = @json($this->getRatingDistribution());
            
            new Chart(document.getElementById('ratingChart'), {
                type: 'bar',
                data: {
                    labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                    datasets: [{
                        label: 'Number of Ratings',
                        data: [
                            ratingData[1] || 0,
                            ratingData[2] || 0,
                            ratingData[3] || 0,
                            ratingData[4] || 0,
                            ratingData[5] || 0
                        ],
                        backgroundColor: [
                            '#EF4444', // red
                            '#F97316', // orange
                            '#F59E0B', // yellow
                            '#10B981', // green
                            '#059669'  // dark green
                        ],
                        borderRadius: 4,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</x-filament-panels::page>