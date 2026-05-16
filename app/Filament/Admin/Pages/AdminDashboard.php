<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class AdminDashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Tổng quan';

    protected static ?string $title = 'Tổng quan';

    protected static ?int $navigationSort = -5;

    public function getHeading(): string
    {
        return 'CMS Crawler';
    }

    public function getSubheading(): ?string
    {
        return 'Import từ web → phân tích AI → duyệt và xuất bản.';
    }
}
