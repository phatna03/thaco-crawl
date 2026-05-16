<?php

namespace App\Filament\Admin\Resources\PostResource\Pages;

use App\Filament\Admin\Resources\PostResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;

use function Filament\Support\is_app_url;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    public function mount(): void
    {
        parent::mount();

        /*
         * Sort đồng bộ #[Url] gây lỗi (redirect/query) trên một số môi trường; bảng chỉ dùng defaultSort.
         * Xóa sort khỏi URL nếu còn bookmark/liên cũ.
         */
        if (request()->hasAny(['tableSortColumn', 'tableSortDirection'])) {
            $this->tableSortColumn = null;
            $this->tableSortDirection = null;

            $url = PostResource::getUrl('index');
            $this->redirect($url, navigate: FilamentView::hasSpaMode() && is_app_url($url));
        }
    }

    /**
     * Không cho đổi sort từ UI; tránh cập nhật tableSortColumn/tableSortDirection + query string.
     */
    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
