<?php

namespace App\Filament\Admin\Pages;

use App\Models\Post;
use App\Services\AIService;
use App\Services\CrawlService;
use App\Services\CrawlUrlValidator;
use App\Services\PageLinkDiscoveryService;
use App\Services\ParserService;
use App\Services\SitemapService;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ImportContent extends Page implements HasForms
{
    use InteractsWithForms;

    private const DISCOVERED_PER_PAGE = 50;

    private const DISCOVERED_MAX_PAGES = 5;

    /** Tổng URL tối đa từ sitemap = trang × mỗi trang */
    private const SITEMAP_MAX_URLS = self::DISCOVERED_PER_PAGE * self::DISCOVERED_MAX_PAGES;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string $view = 'filament.admin.pages.import-content';

    protected static ?string $navigationLabel = 'Import nội dung';

    protected static ?string $title = 'Import nội dung từ web';

    protected static ?int $navigationSort = -10;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public bool $isDiscovering = false;

    public int $discoveredUrlsPage = 1;

    /** Đã chạy ít nhất một lần phát hiện sitemap (để hiện hướng dẫn bước 2). */
    public bool $sitemapLookupAttempted = false;

    public function mount(): void
    {
        $this->resetDiscoveryUi();
        $this->form->fill([
            'domain' => '',
            'manual_url' => '',
            'discovered_urls' => [],
            'discover_source' => null,
            'selected_url' => null,
            'sitemap_hint' => '',
            'title' => '',
            'original_content' => '',
            'category' => '',
            'thumbnail' => '',
            'ai_tone' => (string) config('services.ai.default_writing_style', ''),
            'ai_summary' => '',
            'ai_tags' => '',
            'ai_rewritten_content' => '',
            'metadata' => [],
            'status' => Post::STATUS_READY,
            'last_ai_error' => '',
        ]);
    }

    protected function resetDiscoveryUi(): void
    {
        $this->discoveredUrlsPage = 1;
        $this->sitemapLookupAttempted = false;
        $this->isDiscovering = false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Bước 1 — Domain & sitemap')
                    ->description('Dán hoặc nhập domain (vd: https://ads.netsolutions.vn), nhấn Enter hoặc chờ sau khi dán — hệ thống tự kiểm tra sitemap. Có thể bấm «Kiểm tra lại» nếu cần.')
                    ->schema([
                        TextInput::make('domain')
                            ->label('Domain / trang chủ')
                            ->placeholder('vd: example.com hoặc https://example.com')
                            ->maxLength(2048)
                            ->disabled(fn (): bool => $this->isDiscovering)
                            ->extraInputAttributes(fn (): array => [
                                'id' => 'import-domain-input',
                                'wire:keydown.enter.prevent' => '$wire.discoverSitemaps()',
                                // PASTE: đợi Livewire sync giá trị rồi mới discover
                                'x-on:paste' => '$nextTick(() => setTimeout(() => $wire.discoverSitemaps(), 150))',
                            ]),
                        Placeholder::make('sitemap_hint')
                            ->label('Trạng thái')
                            ->content(fn (): string => $this->isDiscovering
                                ? 'Đang kiểm tra sitemap…'
                                : (string) ($this->data['sitemap_hint'] ?? '—')),
                        Actions::make([
                            FormAction::make('discover_step1')
                                ->label('Kiểm tra lại sitemap')
                                ->icon('heroicon-m-arrow-path')
                                ->color('gray')
                                ->disabled(fn (): bool => $this->isDiscovering)
                                ->action(fn () => $this->discoverSitemaps()),
                            FormAction::make('scan_links_homepage')
                                ->label('Quét link từ trang')
                                ->icon('heroicon-m-link')
                                ->color('primary')
                                ->visible(fn (): bool => $this->sitemapLookupAttempted
                                    && ! $this->hasDiscoveredUrls())
                                ->disabled(fn (): bool => $this->isDiscovering)
                                ->requiresConfirmation()
                                ->modalHeading('Quét liên kết từ trang chủ?')
                                ->modalDescription('Hệ thống tải trang chủ của domain ở bước 1 (HTTPS) và gom các URL cùng site có trong HTML — phù hợp khi không có sitemap XML.')
                                ->action(fn () => $this->scanLinksFromHomepage()),
                        ])->key('import-step1-actions'),
                    ])
                    ->columns(1),
                Section::make('Bước 2 — Chọn URL (sitemap hoặc thủ công)')
                    ->description(fn (): ?string => $this->sitemapLookupAttempted && ! $this->hasDiscoveredUrls()
                        ? 'Không có URL từ sitemap — bấm «Quét link từ trang» ở bước 1 để lấy danh sách từ trang chủ, hoặc dán URL đầy đủ vào ô bên dưới.'
                        : 'Chọn một URL trong danh sách (có phân trang nếu nhiều) hoặc nhập URL đầy đủ.')
                    ->schema([
                        Select::make('selected_url')
                            ->label(fn (): string => ($this->data['discover_source'] ?? null) === 'link_scan'
                                ? 'URL từ quét trang'
                                : 'URL từ sitemap')
                            ->options(fn (): array => $this->getDiscoveredSelectOptions())
                            ->searchable()
                            ->native(false)
                            ->visible(fn (): bool => $this->hasDiscoveredUrls()),
                        Placeholder::make('discovered_list_meta')
                            ->label('')
                            ->visible(fn (): bool => $this->hasDiscoveredUrls())
                            ->content(function (): string {
                                $p = $this->getDiscoveredPagination();
                                $from = $p['total'] === 0 ? 0 : (($p['page'] - 1) * self::DISCOVERED_PER_PAGE) + 1;
                                $to = min($p['page'] * self::DISCOVERED_PER_PAGE, $p['total']);

                                return "Trang {$p['page']}/{$p['total_pages']} — hiển thị {$from}–{$to} / {$p['total']} URL.";
                            }),
                        Actions::make([
                            FormAction::make('discovered_prev')
                                ->label('← Trang trước')
                                ->color('gray')
                                ->disabled(fn (): bool => $this->getDiscoveredPagination()['page'] <= 1)
                                ->action(fn () => $this->goDiscoveredPrevPage()),
                            FormAction::make('discovered_next')
                                ->label('Trang sau →')
                                ->color('gray')
                                ->disabled(fn (): bool => $this->getDiscoveredPagination()['page'] >= $this->getDiscoveredPagination()['total_pages'])
                                ->action(fn () => $this->goDiscoveredNextPage()),
                        ])->key('import-disc-pagination')
                            ->visible(fn (): bool => $this->hasDiscoveredUrls() && $this->getDiscoveredPagination()['total_pages'] > 1),
                        TextInput::make('manual_url')
                            ->label('URL thủ công (nếu không chọn trong danh sách)')
                            ->placeholder('https://...')
                            ->maxLength(2048)
                            ->extraInputAttributes([
                                'id' => 'import-manual-url',
                            ])
                            ->helperText(fn (): ?string => $this->sitemapLookupAttempted && ! $this->hasDiscoveredUrls()
                                ? 'Dán URL bài viết / trang cần crawl vào đây.'
                                : null),
                        Actions::make([
                            FormAction::make('crawl_step2')
                                ->label('Thu thập trang này')
                                ->icon('heroicon-m-arrow-down-tray')
                                ->color('primary')
                                ->action(fn () => $this->crawlPage()),
                        ])->key('import-step2-actions'),
                    ])
                    ->columns(1),
                Section::make('Bước 3 — Nội dung đã trích xuất')
                    ->schema([
                        TextInput::make('title')
                            ->label('Tiêu đề')
                            ->required()
                            ->maxLength(500),
                        TextInput::make('category')
                            ->label('Danh mục')
                            ->maxLength(500)
                            ->helperText('Cố gắng nhận từ meta / JSON-LD / breadcrumb của trang; có thể sửa tay.'),
                        Textarea::make('original_content')
                            ->label('Nội dung gốc (đã crawl)')
                            ->rows(12)
                            ->required(),
                        Textarea::make('ai_tone')
                            ->label('Văn phong cho AI (viết lại)')
                            ->helperText('Ô này được điền mặc định; chỉnh sửa trước khi bấm «Phân tích AI» nếu muốn giọng văn khác.')
                            ->rows(3)
                            ->maxLength(2000),
                        TextInput::make('thumbnail')
                            ->label('Ảnh đại diện (URL)')
                            ->maxLength(2048)
                            ->live(onBlur: true),
                        Placeholder::make('thumbnail_preview')
                            ->label('Xem trước ảnh')
                            ->visible(fn (): bool => $this->isHttpUrlForPreview($this->data['thumbnail'] ?? ''))
                            ->content(fn (): HtmlString => $this->buildThumbnailPreviewHtml((string) ($this->data['thumbnail'] ?? ''))),
                        Actions::make([
                            FormAction::make('ai_step3')
                                ->label('Phân tích AI (tóm tắt, thẻ, viết lại)')
                                ->icon('heroicon-m-sparkles')
                                ->color('warning')
                                ->action(fn () => $this->runAi()),
                        ])->key('import-step3-actions'),
                    ]),
                Section::make('Bước 4 — Kết quả AI & lưu')
                    ->schema([
                        Textarea::make('ai_summary')
                            ->label('Tóm tắt (AI)')
                            ->rows(4),
                        Textarea::make('ai_tags')
                            ->label('Thẻ (AI)')
                            ->helperText('3–5 từ khóa, cách nhau bằng dấu phẩy (AI tự đề xuất từ nội dung).')
                            ->rows(2)
                            ->placeholder('VD: Metro số 2, THACO, TP.HCM'),
                        Textarea::make('ai_rewritten_content')
                            ->label('Nội dung viết lại (AI)')
                            ->rows(12),
                        Select::make('status')
                            ->label('Trạng thái khi lưu')
                            ->options([
                                Post::STATUS_DRAFT => 'Nháp',
                                Post::STATUS_READY => 'Sẵn sàng duyệt',
                                Post::STATUS_PUBLISHED => 'Đã xuất bản',
                            ])
                            ->required(),
                        Placeholder::make('last_ai_error')
                            ->label('Lỗi AI gần nhất')
                            ->content(fn (): string => filled($this->data['last_ai_error'] ?? null)
                                ? (string) $this->data['last_ai_error']
                                : '—'),
                        Actions::make([
                            FormAction::make('save_step4')
                                ->label('Lưu bài viết')
                                ->icon('heroicon-m-check')
                                ->color('success')
                                ->action(fn () => $this->savePost()),
                        ])->key('import-step4-actions'),
                    ]),
            ])
            ->statePath('data');
    }

    private function isHttpUrlForPreview(mixed $url): bool
    {
        $u = trim((string) $url);
        if ($u === '' || ! filter_var($u, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($u, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }

    private function buildThumbnailPreviewHtml(string $url): HtmlString
    {
        $url = trim($url);
        if (! $this->isHttpUrlForPreview($url)) {
            return new HtmlString('');
        }
        $escaped = e($url);

        return new HtmlString(
            '<div class="rounded-lg border border-gray-200 p-2 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50 inline-block max-w-full">'
            .'<img src="'.$escaped.'" alt="" loading="lazy" decoding="async" '
            .'class="rounded max-w-full h-auto object-contain" '
            .'style="max-width:min(100%,420px);max-height:240px;width:auto;height:auto" '
            .'onerror="this.style.display=\'none\';this.nextElementSibling&&this.nextElementSibling.classList.remove(\'hidden\');"/>'
            .'<p class="text-xs text-danger-600 dark:text-danger-400 mt-1 hidden">Không hiển thị được ảnh (hotlink hoặc chính sách trang nguồn).</p>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Làm mới form')
                ->color('gray')
                ->icon('heroicon-m-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Xoá toàn bộ nội dung form?')
                ->modalDescription('Thao tác này không xoá bài đã lưu trong CSDL.')
                ->action(fn () => $this->mount()),
        ];
    }

    public function hasDiscoveredUrls(): bool
    {
        $urls = $this->data['discovered_urls'] ?? [];

        return is_array($urls) && $urls !== [];
    }

    /**
     * @return array{total: int, page: int, total_pages: int, slice: list<string>}
     */
    public function getDiscoveredPagination(): array
    {
        $all = is_array($this->data['discovered_urls'] ?? null) ? $this->data['discovered_urls'] : [];
        $total = count($all);
        if ($total === 0) {
            return ['total' => 0, 'page' => 1, 'total_pages' => 1, 'slice' => []];
        }

        $per = self::DISCOVERED_PER_PAGE;
        $rawPages = (int) ceil($total / $per);
        $totalPages = max(1, min(self::DISCOVERED_MAX_PAGES, $rawPages));
        $page = max(1, min($this->discoveredUrlsPage, $totalPages));
        $offset = ($page - 1) * $per;
        $slice = array_values(array_slice($all, $offset, $per));

        return [
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'slice' => $slice,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getDiscoveredSelectOptions(): array
    {
        $p = $this->getDiscoveredPagination();
        $opts = [];
        foreach ($p['slice'] as $u) {
            if (is_string($u) && $u !== '') {
                $opts[$u] = Str::limit($u, 120);
            }
        }

        $sel = isset($this->data['selected_url']) ? trim((string) $this->data['selected_url']) : '';
        if ($sel !== '' && ! isset($opts[$sel])) {
            $opts = [$sel => '★ '.Str::limit($sel, 100).' (đang chọn)'] + $opts;
        }

        return $opts;
    }

    public function goDiscoveredPrevPage(): void
    {
        $p = $this->getDiscoveredPagination();
        $this->discoveredUrlsPage = max(1, $p['page'] - 1);
    }

    public function goDiscoveredNextPage(): void
    {
        $p = $this->getDiscoveredPagination();
        $this->discoveredUrlsPage = min($p['total_pages'], $p['page'] + 1);
    }

    public function discoverSitemaps(): void
    {
        if ($this->isDiscovering) {
            return;
        }

        $this->data['domain'] = trim((string) ($this->data['domain'] ?? ''));
        if ($this->data['domain'] === '' || mb_strlen($this->data['domain']) < 3) {
            Notification::make()
                ->title('Nhập domain')
                ->body('Vui lòng dán hoặc gõ domain / URL trang chủ (tối thiểu 3 ký tự).')
                ->warning()
                ->send();

            return;
        }

        $this->isDiscovering = true;
        $this->data['selected_url'] = null;
        $this->discoveredUrlsPage = 1;

        try {
            /** @var SitemapService $sitemaps */
            $sitemaps = app(SitemapService::class);
            $result = $sitemaps->discoverUrlsFromDomain((string) $this->data['domain'], self::SITEMAP_MAX_URLS);

            $this->data['discovered_urls'] = $result['urls'];
            $count = count($result['urls']);
            $via = (string) $result['via'];
            $this->sitemapLookupAttempted = true;

            if ($count === 0) {
                $this->data['discover_source'] = null;
                $this->data['sitemap_hint'] = "Không tìm thấy URL từ sitemap ({$via}). Bấm «Quét link từ trang» để gom link từ trang chủ, hoặc dán URL trực tiếp ở bước 2.";
                Notification::make()
                    ->title('Không có sitemap')
                    ->body('Thử «Quét link từ trang» hoặc nhập URL thủ công.')
                    ->warning()
                    ->send();
                $this->focusManualUrlInput();

                return;
            }

            $this->data['discover_source'] = 'sitemap';
            $this->data['sitemap_hint'] = "Tìm thấy {$count} URL ({$via}). Chọn một dòng ở bước 2 (tối đa ".self::SITEMAP_MAX_URLS.' URL; '.self::DISCOVERED_MAX_PAGES.' trang × '.self::DISCOVERED_PER_PAGE.' dòng).';
            Notification::make()
                ->title('Đã đọc sitemap')
                ->body("{$count} URL. Chọn trang và URL cần thu thập.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::warning('sitemap.discover_failed', [
                'domain' => $this->data['domain'] ?? null,
                'message' => $e->getMessage(),
            ]);
            $this->sitemapLookupAttempted = true;
            $this->data['discovered_urls'] = [];
            $this->data['discover_source'] = null;
            $this->data['sitemap_hint'] = 'Lỗi sitemap: '.$e->getMessage().' — có thể thử «Quét link từ trang».';
            Notification::make()
                ->title('Lỗi phát hiện sitemap')
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->focusManualUrlInput();
        } finally {
            $this->isDiscovering = false;
        }
    }

    public function scanLinksFromHomepage(): void
    {
        if ($this->isDiscovering) {
            return;
        }

        $this->data['domain'] = trim((string) ($this->data['domain'] ?? ''));
        if ($this->data['domain'] === '' || mb_strlen($this->data['domain']) < 3) {
            Notification::make()
                ->title('Nhập domain')
                ->body('Vui lòng nhập domain / trang chủ ở bước 1 trước khi quét link.')
                ->warning()
                ->send();

            return;
        }

        $this->isDiscovering = true;
        $this->data['selected_url'] = null;
        $this->discoveredUrlsPage = 1;

        try {
            /** @var PageLinkDiscoveryService $linkScan */
            $linkScan = app(PageLinkDiscoveryService::class);
            $urls = $linkScan->discoverFromDomainHomepage((string) $this->data['domain'], self::SITEMAP_MAX_URLS);

            $this->data['discovered_urls'] = $urls;
            $this->data['discover_source'] = 'link_scan';
            $this->sitemapLookupAttempted = true;
            $count = count($urls);

            if ($count === 0) {
                $this->data['discover_source'] = null;
                $this->data['sitemap_hint'] = 'Quét trang chủ không thấy link HTTP/HTTPS cùng site trong HTML (hoặc trang chặn). Thử URL thủ công ở bước 2.';
                Notification::make()
                    ->title('Không thu thập được link')
                    ->body('Trang chủ có thể ít liên kết nội bộ hoặc nội dung tải bằng JavaScript.')
                    ->warning()
                    ->send();
                $this->focusManualUrlInput();

                return;
            }

            $this->data['sitemap_hint'] = "Thu được {$count} URL từ trang chủ (quét HTML). Chọn một dòng ở bước 2 (tối đa ".self::SITEMAP_MAX_URLS.' URL; '.self::DISCOVERED_MAX_PAGES.' trang × '.self::DISCOVERED_PER_PAGE.' dòng).';
            Notification::make()
                ->title('Đã quét link')
                ->body("{$count} URL. Chọn trang cần thu thập.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::warning('link_scan.homepage_failed', [
                'domain' => $this->data['domain'] ?? null,
                'message' => $e->getMessage(),
            ]);
            $this->data['discovered_urls'] = [];
            $this->data['discover_source'] = null;
            $this->data['sitemap_hint'] = 'Lỗi quét link: '.$e->getMessage();
            Notification::make()
                ->title('Lỗi quét link từ trang')
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->focusManualUrlInput();
        } finally {
            $this->isDiscovering = false;
        }
    }

    protected function focusManualUrlInput(): void
    {
        $this->js('setTimeout(() => document.getElementById("import-manual-url")?.focus(), 400)');
    }

    public function crawlPage(): void
    {
        $manual = trim((string) ($this->data['manual_url'] ?? ''));
        $picked = trim((string) ($this->data['selected_url'] ?? ''));
        $url = $manual !== '' ? $manual : $picked;

        if ($url === '') {
            Notification::make()
                ->title('Thiếu URL')
                ->body('Chọn một URL từ sitemap hoặc nhập URL thủ công.')
                ->warning()
                ->send();

            return;
        }

        try {
            CrawlUrlValidator::assertHttpUrlAllowed($url);
            /** @var CrawlService $crawl */
            $crawl = app(CrawlService::class);
            /** @var ParserService $parser */
            $parser = app(ParserService::class);

            $html = $crawl->fetch($url);
            $parsed = $parser->parse($html, $url);

            $this->data['title'] = $parsed['title'];
            $this->data['original_content'] = $parsed['content'];
            $this->data['category'] = $parsed['category'] ?? '';
            $this->data['thumbnail'] = $parsed['thumbnail'] ?? '';
            $this->data['metadata'] = $parsed['metadata'];
            $this->data['manual_url'] = $url;

            Notification::make()
                ->title('Đã thu thập trang')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::warning('crawl.page_failed', [
                'url' => $url ?? null,
                'message' => $e->getMessage(),
            ]);
            Notification::make()
                ->title('Lỗi crawl')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runAi(): void
    {
        $this->form->validate([
            'data.title' => ['required', 'string', 'min:1'],
            'data.original_content' => ['required', 'string', 'min:20'],
            'data.ai_tone' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            /** @var AIService $ai */
            $ai = app(AIService::class);
            $title = (string) $this->data['title'];
            $original = (string) $this->data['original_content'];
            $tone = isset($this->data['ai_tone']) ? trim((string) $this->data['ai_tone']) : '';

            $result = $ai->analyzeForCms($title, $original, $tone !== '' ? $tone : null);

            $this->data['ai_summary'] = $result['summary'];
            $this->data['ai_tags'] = $result['tags'];
            $this->data['ai_rewritten_content'] = $result['rewritten'];
            $this->data['last_ai_error'] = '';

            Notification::make()
                ->title('Phân tích AI hoàn tất')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::warning('ai.analyze_failed', [
                'title' => $this->data['title'] ?? null,
                'message' => $e->getMessage(),
            ]);
            $this->data['last_ai_error'] = $e->getMessage();
            Notification::make()
                ->title('Lỗi AI')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function savePost(): void
    {
        $this->form->validate([
            'data.title' => ['required', 'string', 'min:1', 'max:500'],
            'data.original_content' => ['required', 'string', 'min:1'],
            'data.status' => ['required', 'in:'.implode(',', [Post::STATUS_DRAFT, Post::STATUS_READY, Post::STATUS_PUBLISHED])],
        ]);

        $manual = trim((string) ($this->data['manual_url'] ?? ''));
        $picked = trim((string) ($this->data['selected_url'] ?? ''));
        $sourceUrl = $manual !== '' ? $manual : $picked;

        if ($sourceUrl === '') {
            Notification::make()
                ->title('Thiếu URL gốc')
                ->body('Hãy thu thập trang trước hoặc điền URL thủ công.')
                ->warning()
                ->send();

            return;
        }

        try {
            CrawlUrlValidator::assertHttpUrlAllowed($sourceUrl);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('URL không hợp lệ')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        if (Post::query()->where('source_url', $sourceUrl)->exists()) {
            Notification::make()
                ->title('URL đã tồn tại')
                ->body('Bài với `source_url` này đã có trong hệ thống. Hãy mở mục Bài viết để chỉnh sửa.')
                ->warning()
                ->send();

            return;
        }

        $dailyLimit = config('crawl.daily_post_import_limit');
        if ($dailyLimit !== null && $dailyLimit > 0) {
            $todayCount = Post::query()->whereDate('created_at', now()->toDateString())->count();
            if ($todayCount >= $dailyLimit) {
                Notification::make()
                    ->title('Đạt giới hạn import trong ngày')
                    ->body("Hôm nay đã tạo {$todayCount}/{$dailyLimit} bài. Tăng DAILY_POST_IMPORT_LIMIT trong .env nếu cần.")
                    ->warning()
                    ->send();

                return;
            }
        }

        try {
            $thumb = trim((string) ($this->data['thumbnail'] ?? ''));

            Post::query()->create([
                'title' => (string) $this->data['title'],
                'source_url' => $sourceUrl,
                'original_content' => (string) $this->data['original_content'],
                'category' => ($c = trim((string) ($this->data['category'] ?? ''))) !== '' ? $c : null,
                'ai_tone' => ($t = trim((string) ($this->data['ai_tone'] ?? ''))) !== '' ? $t : null,
                'ai_summary' => $this->data['ai_summary'] ?: null,
                'ai_tags' => ($tg = trim((string) ($this->data['ai_tags'] ?? ''))) !== '' ? $tg : null,
                'ai_rewritten_content' => $this->data['ai_rewritten_content'] ?: null,
                'thumbnail' => $thumb !== '' ? $thumb : null,
                'metadata' => is_array($this->data['metadata'] ?? null) ? $this->data['metadata'] : [],
                'status' => (string) $this->data['status'],
                'last_ai_error' => $this->data['last_ai_error'] ?: null,
            ]);

            Notification::make()
                ->title('Đã lưu bài viết')
                ->success()
                ->send();

            $this->mount();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                Notification::make()
                    ->title('Trùng URL nguồn')
                    ->body('Bài với `source_url` này đã tồn tại.')
                    ->warning()
                    ->send();

                return;
            }

            throw $e;
        }
    }
}
