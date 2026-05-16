<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PostStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        return [
            Stat::make('Tổng bài', Post::query()->count())
                ->description('Đã lưu trong CMS')
                ->icon('heroicon-o-document-text'),
            Stat::make('Nháp', Post::query()->where('status', Post::STATUS_DRAFT)->count())
                ->description('Cần xử lý')
                ->color('gray'),
            Stat::make('Sẵn sàng duyệt', Post::query()->where('status', Post::STATUS_READY)->count())
                ->description('Chờ bạn xem')
                ->color('warning'),
            Stat::make('Đã xuất bản', Post::query()->where('status', Post::STATUS_PUBLISHED)->count())
                ->description('Hiển thị nội bộ')
                ->color('success'),
        ];
    }
}
