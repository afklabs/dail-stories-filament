{{-- resources/views/filament/pages/organized-dashboard.blade.php --}}
<x-filament-panels::page>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
                Real-time analytics and insights for Daily Stories platform
            </p>
        </div>
        
        {{-- System Status --}}
        <div class="flex items-center space-x-4">
            <div class="bg-green-50 dark:bg-green-900/20 px-4 py-2 rounded-lg flex items-center">
                <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                <span class="text-green-700 dark:text-green-300 text-sm font-medium">System Online</span>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 px-4 py-2 rounded-lg">
                <span class="text-blue-700 dark:text-blue-300 text-sm font-medium">
                    Last Updated: {{ now()->format('H:i') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Real-time Summary Bar --}}
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 rounded-xl p-6 mb-8 text-white shadow-lg">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="text-center">
                <div class="text-3xl font-bold mb-1" id="realtime-views">--</div>
                <div class="text-blue-100 text-sm">Views Today</div>
                <div class="text-blue-200 text-xs mt-1" id="views-growth">--</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold mb-1" id="realtime-interactions">--</div>
                <div class="text-blue-100 text-sm">Interactions</div>
                <div class="text-blue-200 text-xs mt-1" id="interactions-growth">--</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold mb-1" id="realtime-members">--</div>
                <div class="text-blue-100 text-sm">Active Members</div>
                <div class="text-blue-200 text-xs mt-1" id="members-status">--</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold mb-1" id="realtime-rating">--</div>
                <div class="text-blue-100 text-sm">Avg Rating</div>
                <div class="text-blue-200 text-xs mt-1" id="rating-trend">--</div>
            </div>
        </div>
    </div>

    {{-- Dashboard Sections --}}
    <div class="space-y-8">
        {{-- Section 1: Overview Statistics --}}
        <div class="space-y-6">
            <div class="flex items-center">
                <x-heroicon-o-chart-bar class="w-6 h-6 text-blue-600 mr-3" />
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Performance Overview</h2>
            </div>
            
            {{-- Story Analytics Overview --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Story Analytics</h3>
                </div>
                <div class="p-6">
                    <x-filament-widgets::widget
                        :widget="\App\Filament\Widgets\StoryAnalyticsOverviewWidget::class"
                    />
                </div>
            </div>
            
            {{-- Member Analytics Overview --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Member Analytics</h3>
                </div>
                <div class="p-6">
                    <x-filament-widgets::widget
                        :widget="\App\Filament\Widgets\MemberOverviewWidget::class"
                    />
                </div>
            </div>
        </div>

        {{-- Section 2: Performance Trends --}}
        <div class="space-y-6">
            <div class="flex items-center">
                <x-heroicon-o-chart-line class="w-6 h-6 text-green-600 mr-3" />
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Trends & Activity</h2>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Story Performance</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">30-day performance trends</p>
                    </div>
                    <div class="p-6">
                        <x-filament-widgets::widget
                            :widget="\App\Filament\Widgets\StoryPerformanceChartWidget::class"
                        />
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Member Engagement</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">7-day engagement patterns</p>
                    </div>
                    <div class="p-6">
                        <x-filament-widgets::widget
                            :widget="\App\Filament\Widgets\MemberEngagementWidget::class"
                        />
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 3: Quality & Publishing --}}
        <div class="space-y-6">
            <div class="flex items-center">
                <x-heroicon-o-star class="w-6 h-6 text-yellow-600 mr-3" />
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Content Quality & Publishing</h2>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Content Quality</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Rating distribution analysis</p>
                    </div>
                    <div class="p-6">
                        <x-filament-widgets::widget
                            :widget="\App\Filament\Widgets\ContentQualityWidget::class"
                        />
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Publishing Activity</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">14-day publishing workflow</p>
                    </div>
                    <div class="p-6">
                        <x-filament-widgets::widget
                            :widget="\App\Filament\Widgets\PublishingActivityWidget::class"
                        />
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 4: Top Performance Table --}}
        <div class="space-y-6">
            <div class="flex items-center">
                <x-heroicon-o-trophy class="w-6 h-6 text-orange-600 mr-3" />
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Top Performing Content</h2>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Top Stories (Last 7 Days)</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ranked by views and engagement</p>
                </div>
                <div class="p-6">
                    <x-filament-widgets::widget
                        :widget="\App\Filament\Widgets\TopStoriesTableWidget::class"
                    />
                </div>
            </div>
        </div>

        {{-- Section 5: Member Insights --}}
        <div class="space-y-6">
            <div class="flex items-center">
                <x-heroicon-o-users class="w-6 h-6 text-purple-600 mr-3" />
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Member Insights</h2>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reading Insights</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Completion patterns</p>
                    </div>
                    <div class="p-6">
                        <x-filament-widgets::widget
                            :widget="\App\Filament\Widgets\ReadingInsightsWidget::class"
                        />
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Demographics</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Member distribution</p>
                    </div>
                    <div class="p-6">
                        <x-filament-widgets::widget
                            :widget="\App\Filament\Widgets\MemberDemographicsWidget::class"
                        />
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Top Members</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Most engaged users</p>
                    </div>
                    <div class="p-6">
                        <x-filament-widgets::widget
                            :widget="\App\Filament\Widgets\TopMembersWidget::class"
                        />
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 6: Quick Actions & System Status --}}
        <div class="space-y-6">
            <div class="flex items-center">
                <x-heroicon-o-cog-6-tooth class="w-6 h-6 text-gray-600 mr-3" />
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Quick Actions & System</h2>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Quick Actions --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Quick Actions</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <a href="{{ route('filament.admin.resources.stories.index') }}" 
                               class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group">
                                <x-heroicon-o-document-text class="w-8 h-8 text-blue-600 dark:text-blue-400 mr-3 group-hover:scale-110 transition-transform" />
                                <div>
                                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Manage Stories</p>
                                    <p class="text-xs text-blue-600 dark:text-blue-400">View all stories</p>
                                </div>
                            </a>
                            
                            <a href="{{ route('filament.admin.resources.members.index') }}" 
                               class="flex items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors group">
                                <x-heroicon-o-users class="w-8 h-8 text-green-600 dark:text-green-400 mr-3 group-hover:scale-110 transition-transform" />
                                <div>
                                    <p class="text-sm font-medium text-green-800 dark:text-green-200">Manage Members</p>
                                    <p class="text-xs text-green-600 dark:text-green-400">View all members</p>
                                </div>
                            </a>
                            
                            <button onclick="refreshDashboard()" 
                                    class="flex items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors group">
                                <x-heroicon-o-arrow-path class="w-8 h-8 text-purple-600 dark:text-purple-400 mr-3 group-hover:rotate-180 transition-transform duration-500" />
                                <div>
                                    <p class="text-sm font-medium text-purple-800 dark:text-purple-200">Refresh Data</p>
                                    <p class="text-xs text-purple-600 dark:text-purple-400">Update analytics</p>
                                </div>
                            </button>
                            
                            <a href="{{ route('filament.admin.pages.analytics-dashboard') }}" 
                               class="flex items-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors group">
                                <x-heroicon-o-chart-bar-square class="w-8 h-8 text-orange-600 dark:text-orange-400 mr-3 group-hover:scale-110 transition-transform" />
                                <div>
                                    <p class="text-sm font-medium text-orange-800 dark:text-orange-200">Advanced Analytics</p>
                                    <p class="text-xs text-orange-600 dark:text-orange-400">Detailed insights</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- System Health --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">System Health</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3 animate-pulse"></div>
                                    <div>
                                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Database</p>
                                        <p class="text-xs text-green-600 dark:text-green-400">Healthy & Responsive</p>
                                    </div>
                                </div>
                                <x-heroicon-o-check-circle class="w-6 h-6 text-green-500" />
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3 animate-pulse"></div>
                                    <div>
                                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Cache System</p>
                                        <p class="text-xs text-green-600 dark:text-green-400">Active & Optimized</p>
                                    </div>
                                </div>
                                <x-heroicon-o-check-circle class="w-6 h-6 text-green-500" />
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3 animate-pulse"></div>
                                    <div>
                                        <p class="text-sm font-medium text-green-800 dark:text-green-200">API Services</p>
                                        <p class="text-xs text-green-600 dark:text-green-400">All Systems Go</p>
                                    </div>
                                </div>
                                <x-heroicon-o-check-circle class="w-6 h-6 text-green-500" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Real-time Updates Script --}}
    <script>
        function updateRealtimeMetrics() {
            fetch('/admin/api/dashboard/overview')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const metrics = data.data;
                        
                        // Update main metrics
                        document.getElementById('realtime-views').textContent = 
                            new Intl.NumberFormat().format(metrics.today_activity?.views || 0);
                        document.getElementById('realtime-interactions').textContent = 
                            new Intl.NumberFormat().format(metrics.today_activity?.interactions || 0);
                        document.getElementById('realtime-members').textContent = 
                            new Intl.NumberFormat().format(metrics.key_metrics?.active_members || 0);
                        document.getElementById('realtime-rating').textContent = 
                            (metrics.key_metrics?.average_rating || 0).toFixed(1);
                        
                        // Update growth indicators
                        const viewsGrowth = metrics.growth_metrics?.growth_percentage?.views || 0;
                        document.getElementById('views-growth').textContent = 
                            viewsGrowth >= 0 ? `+${viewsGrowth}% vs yesterday` : `${viewsGrowth}% vs yesterday`;
                        
                        document.getElementById('interactions-growth').textContent = 
                            `${metrics.today_activity?.interactions || 0} today`;
                        document.getElementById('members-status').textContent = 
                            `${metrics.key_metrics?.total_members || 0} total`;
                        document.getElementById('rating-trend').textContent = 
                            `${metrics.key_metrics?.total_ratings || 0} ratings`;
                    }
                })
                .catch(error => {
                    console.error('Failed to update real-time metrics:', error);
                });
        }

        function refreshDashboard() {
            const refreshBtn = event.target.closest('button');
            const icon = refreshBtn.querySelector('svg');
            
            // Add spinning animation
            icon.classList.add('animate-spin');
            
            fetch('/admin/api/dashboard/refresh-cache', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRealtimeMetrics();
                    showNotification('Dashboard refreshed successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('Failed to refresh dashboard', 'error');
                }
            })
            .catch(error => {
                console.error('Refresh failed:', error);
                showNotification('Failed to refresh dashboard', 'error');
            })
            .finally(() => {
                icon.classList.remove('animate-spin');
            });
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateRealtimeMetrics();
            setInterval(updateRealtimeMetrics, 30000);
            
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    updateRealtimeMetrics();
                }
            });
        });
    </script>

    {{-- Custom Styles --}}
    <style>
        /* Smooth transitions */
        .transition-colors {
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
        }
        
        /* Widget container improvements */
        .fi-wi-widget {
            @apply bg-transparent shadow-none border-0;
        }
        
        /* Card hover effects */
        .group:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Pulse animation for status indicators */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .dark ::-webkit-scrollbar-track {
            background: #374151;
        }
        
        .dark ::-webkit-scrollbar-thumb {
            background: #6b7280;
        }
    </style>
</x-filament-panels::page>