<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Cache;
use App\Models\{Story, StoryView, Member, StoryRatingAggregate, MemberStoryInteraction, StoryPublishingHistory};
use Carbon\Carbon;

class AnalyticsDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics Dashboard';
    protected static ?string $title = 'Analytics Dashboard';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.analytics-dashboard';

    public $selectedPeriod = '30';
    public $selectedStory = null;
    public $dateFrom = null;
    public $dateTo = null;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(4)
                    ->schema([
                        Select::make('selectedPeriod')
                            ->label('Time Period')
                            ->options([
                                '7' => 'Last 7 days',
                                '30' => 'Last 30 days',
                                '90' => 'Last 90 days',
                                'custom' => 'Custom Range'
                            ])
                            ->default('30')
                            ->reactive()
                            ->afterStateUpdated(fn ($state) => $this->updateDateRange($state)),

                        Select::make('selectedStory')
                            ->label('Specific Story')
                            ->options(
                                Story::active()
                                    ->limit(50)
                                    ->pluck('title', 'id')
                                    ->prepend('All Stories', null)
                            )
                            ->searchable()
                            ->nullable(),

                        DatePicker::make('dateFrom')
                            ->label('From Date')
                            ->visible(fn ($get) => $get('selectedPeriod') === 'custom'),

                        DatePicker::make('dateTo')
                            ->label('To Date')
                            ->visible(fn ($get) => $get('selectedPeriod') === 'custom'),
                    ])
            ]);
    }

    protected function updateDateRange($period): void
    {
        if ($period !== 'custom') {
            $this->dateFrom = now()->subDays((int) $period)->toDateString();
            $this->dateTo = now()->toDateString();
        }
    }

    public function getOverviewMetrics(): array
    {
        $cacheKey = "analytics_overview_{$this->selectedPeriod}_{$this->selectedStory}";
        
        return Cache::remember($cacheKey, 600, function () {
            $dateFrom = Carbon::parse($this->dateFrom);
            $dateTo = Carbon::parse($this->dateTo);

            $storiesQuery = $this->selectedStory 
                ? Story::where('id', $this->selectedStory)
                : Story::query();

            $viewsQuery = StoryView::whereBetween('viewed_at', [$dateFrom, $dateTo]);
            if ($this->selectedStory) {
                $viewsQuery->where('story_id', $this->selectedStory);
            }

            $interactionsQuery = MemberStoryInteraction::whereBetween('created_at', [$dateFrom, $dateTo]);
            if ($this->selectedStory) {
                $interactionsQuery->where('story_id', $this->selectedStory);
            }

            return [
                'total_stories' => $storiesQuery->count(),
                'total_views' => $viewsQuery->count(),
                'unique_viewers' => $viewsQuery->distinct('device_id')->count(),
                'member_views' => $viewsQuery->whereNotNull('member_id')->count(),
                'guest_views' => $viewsQuery->whereNull('member_id')->count(),
                'total_interactions' => $interactionsQuery->count(),
                'engagement_rate' => $this->calculateEngagementRate($viewsQuery, $interactionsQuery),
                'average_rating' => $this->getAverageRating(),
            ];
        });
    }

    protected function calculateEngagementRate($viewsQuery, $interactionsQuery): float
    {
        $views = $viewsQuery->count();
        $interactions = $interactionsQuery->count();
        
        return $views > 0 ? round(($interactions / $views) * 100, 2) : 0;
    }

    protected function getAverageRating(): float
    {
        $query = StoryRatingAggregate::query();
        
        if ($this->selectedStory) {
            $query->where('story_id', $this->selectedStory);
        }
        
        return round($query->avg('average_rating') ?? 0, 2);
    }

    public function getViewTrends(): array
    {
        $cacheKey = "view_trends_{$this->selectedPeriod}_{$this->selectedStory}";
        
        return Cache::remember($cacheKey, 600, function () {
            $dateFrom = Carbon::parse($this->dateFrom);
            $dateTo = Carbon::parse($this->dateTo);
            
            $days = $dateFrom->diffInDays($dateTo) + 1;
            $data = [];
            
            for ($i = 0; $i < $days; $i++) {
                $date = $dateFrom->copy()->addDays($i);
                
                $viewsQuery = StoryView::whereDate('viewed_at', $date);
                if ($this->selectedStory) {
                    $viewsQuery->where('story_id', $this->selectedStory);
                }
                
                $data[] = [
                    'date' => $date->format('M j'),
                    'views' => $viewsQuery->count(),
                    'unique_views' => $viewsQuery->distinct('device_id')->count(),
                    'member_views' => $viewsQuery->whereNotNull('member_id')->count(),
                ];
            }
            
            return $data;
        });
    }

    public function getTopStories(): array
    {
        if ($this->selectedStory) {
            return [];
        }

        $cacheKey = "top_stories_{$this->selectedPeriod}";
        
        return Cache::remember($cacheKey, 600, function () {
            $dateFrom = Carbon::parse($this->dateFrom);
            $dateTo = Carbon::parse($this->dateTo);
            
            return Story::withCount(['storyViews as period_views' => function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('viewed_at', [$dateFrom, $dateTo]);
                }])
                ->with(['category', 'ratingAggregate'])
                ->orderByDesc('period_views')
                ->limit(10)
                ->get()
                ->map(function ($story) {
                    return [
                        'id' => $story->id,
                        'title' => $story->title,
                        'category' => $story->category->name ?? 'Uncategorized',
                        'views' => $story->period_views,
                        'rating' => $story->ratingAggregate?->average_rating ?? 0,
                        'total_ratings' => $story->ratingAggregate?->total_ratings ?? 0,
                    ];
                });
        });
    }

    public function getEngagementBreakdown(): array
    {
        $cacheKey = "engagement_breakdown_{$this->selectedPeriod}_{$this->selectedStory}";
        
        return Cache::remember($cacheKey, 600, function () {
            $dateFrom = Carbon::parse($this->dateFrom);
            $dateTo = Carbon::parse($this->dateTo);
            
            $query = MemberStoryInteraction::whereBetween('created_at', [$dateFrom, $dateTo]);
            if ($this->selectedStory) {
                $query->where('story_id', $this->selectedStory);
            }
            
            return $query->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get()
                ->pluck('count', 'action')
                ->toArray();
        });
    }

    public function getDeviceAnalytics(): array
    {
        $cacheKey = "device_analytics_{$this->selectedPeriod}_{$this->selectedStory}";
        
        return Cache::remember($cacheKey, 600, function () {
            $dateFrom = Carbon::parse($this->dateFrom);
            $dateTo = Carbon::parse($this->dateTo);
            
            $query = StoryView::whereBetween('viewed_at', [$dateFrom, $dateTo]);
            if ($this->selectedStory) {
                $query->where('story_id', $this->selectedStory);
            }
            
            // Simplified device detection based on user agent
            $mobileCount = $query->clone()
                ->whereRaw("LOWER(user_agent) LIKE '%mobile%' OR LOWER(user_agent) LIKE '%android%' OR LOWER(user_agent) LIKE '%iphone%'")
                ->count();
            
            $totalViews = $query->count();
            $desktopCount = $totalViews - $mobileCount;
            
            return [
                'mobile' => $mobileCount,
                'desktop' => $desktopCount,
                'mobile_percentage' => $totalViews > 0 ? round(($mobileCount / $totalViews) * 100, 1) : 0,
                'desktop_percentage' => $totalViews > 0 ? round(($desktopCount / $totalViews) * 100, 1) : 0,
            ];
        });
    }

    public function getPublishingActivity(): array
    {
        if ($this->selectedStory) {
            return $this->getStoryPublishingHistory();
        }

        $cacheKey = "publishing_activity_{$this->selectedPeriod}";
        
        return Cache::remember($cacheKey, 600, function () {
            $dateFrom = Carbon::parse($this->dateFrom);
            $dateTo = Carbon::parse($this->dateTo);
            
            return StoryPublishingHistory::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get()
                ->pluck('count', 'action')
                ->toArray();
        });
    }

    protected function getStoryPublishingHistory(): array
    {
        return StoryPublishingHistory::where('story_id', $this->selectedStory)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($history) {
                return [
                    'action' => $history->action,
                    'user' => $history->user->name ?? 'System',
                    'date' => $history->created_at->format('M j, Y H:i'),
                    'notes' => $history->notes,
                ];
            })
            ->toArray();
    }

    public function getRatingDistribution(): array
    {
        $cacheKey = "rating_distribution_{$this->selectedStory}";
        
        return Cache::remember($cacheKey, 600, function () {
            $query = StoryRatingAggregate::query();
            
            if ($this->selectedStory) {
                $ratingAggregate = $query->where('story_id', $this->selectedStory)->first();
                return $ratingAggregate?->rating_distribution ?? [];
            }
            
            // Global rating distribution
            $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            
            $aggregates = $query->get();
            foreach ($aggregates as $aggregate) {
                $storyDistribution = $aggregate->rating_distribution ?? [];
                foreach ($storyDistribution as $rating => $count) {
                    $distribution[$rating] = ($distribution[$rating] ?? 0) + $count;
                }
            }
            
            return $distribution;
        });
    }

    public function getContentQualityMetrics(): array
    {
        $cacheKey = "content_quality_{$this->selectedStory}";
        
        return Cache::remember($cacheKey, 600, function () {
            $query = StoryRatingAggregate::query();
            
            if ($this->selectedStory) {
                $aggregate = $query->where('story_id', $this->selectedStory)->first();
                if (!$aggregate) return [];
                
                return [
                    'average_rating' => $aggregate->average_rating,
                    'total_ratings' => $aggregate->total_ratings,
                    'quality_score' => $this->calculateQualityScore($aggregate),
                ];
            }
            
            // Global quality metrics
            $avgRating = $query->avg('average_rating') ?? 0;
            $totalRatings = $query->sum('total_ratings');
            $excellent = $query->where('average_rating', '>=', 4.5)->count();
            $good = $query->whereBetween('average_rating', [3.5, 4.49])->count();
            $total = $query->count();
            
            return [
                'average_rating' => round($avgRating, 2),
                'total_ratings' => $totalRatings,
                'excellent_percentage' => $total > 0 ? round(($excellent / $total) * 100, 1) : 0,
                'good_percentage' => $total > 0 ? round(($good / $total) * 100, 1) : 0,
                'total_stories' => $total,
            ];
        });
    }

    protected function calculateQualityScore($aggregate): float
    {
        $ratingScore = ($aggregate->average_rating / 5) * 70; // 70% weight on rating
        $volumeScore = min(($aggregate->total_ratings / 50) * 30, 30); // 30% weight on volume, max at 50 ratings
        
        return round($ratingScore + $volumeScore, 1);
    }
}