<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Pages\ImportContent;
use App\Filament\Admin\Resources\PostResource\Pages;
use App\Models\Post;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Bài viết';

    protected static ?string $modelLabel = 'Bài viết';

    protected static ?string $pluralModelLabel = 'Bài viết';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'source_url', 'slug', 'category'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Tiêu đề')
                    ->required()
                    ->maxLength(500),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(255),
                TextInput::make('source_url')
                    ->label('URL nguồn')
                    ->url()
                    ->required()
                    ->maxLength(2048),
                Textarea::make('original_content')
                    ->label('Nội dung gốc')
                    ->required()
                    ->rows(12),
                TextInput::make('category')
                    ->label('Danh mục')
                    ->maxLength(500),
                Textarea::make('ai_tone')
                    ->label('Văn phong (AI)')
                    ->helperText('Dùng khi «Phân tích lại (AI)». Để trống sẽ lấy mặc định từ cấu hình.')
                    ->rows(3)
                    ->maxLength(2000),
                Textarea::make('ai_summary')
                    ->label('Tóm tắt (AI)')
                    ->rows(4),
                Textarea::make('ai_tags')
                    ->label('Thẻ (AI)')
                    ->helperText('Từ khóa, cách nhau bằng dấu phẩy.')
                    ->rows(2),
                Textarea::make('ai_rewritten_content')
                    ->label('Nội dung viết lại (AI)')
                    ->rows(12),
                TextInput::make('thumbnail')
                    ->label('Ảnh đại diện')
                    ->maxLength(2048)
                    ->live(onBlur: true),
                Placeholder::make('thumbnail_preview')
                    ->label('Xem trước ảnh')
                    ->visible(fn (Get $get): bool => self::thumbnailUrlLooksValid($get('thumbnail')))
                    ->content(fn (Get $get): HtmlString => self::buildThumbnailPreviewHtml((string) $get('thumbnail'))),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        Post::STATUS_DRAFT => 'Nháp',
                        Post::STATUS_READY => 'Sẵn sàng duyệt',
                        Post::STATUS_PUBLISHED => 'Đã xuất bản',
                    ])
                    ->required(),
                Textarea::make('last_ai_error')
                    ->label('Lỗi AI gần nhất')
                    ->rows(2),
            ]);
    }

    protected static function thumbnailUrlLooksValid(mixed $url): bool
    {
        $u = trim((string) $url);
        if ($u === '' || ! filter_var($u, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($u, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }

    protected static function buildThumbnailPreviewHtml(string $url): HtmlString
    {
        $u = trim($url);
        if (! self::thumbnailUrlLooksValid($u)) {
            return new HtmlString('');
        }
        $escaped = e($u);

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (Post $record): string => $record->title),
                TextColumn::make('source_url')
                    ->label('URL')
                    ->limit(35)
                    ->visibleFrom('lg')
                    ->tooltip(fn (Post $record): string => $record->source_url)
                    ->url(fn (Post $record): string => $record->source_url)
                    ->openUrlInNewTab(),
                TextColumn::make('category')
                    ->label('Danh mục')
                    ->searchable()
                    ->visibleFrom('lg')
                    ->placeholder('—')
                    ->limit(24)
                    ->tooltip(fn (Post $record): string => (string) ($record->category ?? '')),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->visibleFrom('lg')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Post::STATUS_DRAFT => 'Nháp',
                        Post::STATUS_READY => 'Sẵn sàng',
                        Post::STATUS_PUBLISHED => 'Xuất bản',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Post::STATUS_DRAFT => 'gray',
                        Post::STATUS_READY => 'warning',
                        Post::STATUS_PUBLISHED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->visibleFrom('lg')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('_compact_updated')
                    ->label('Cập nhật')
                    ->hiddenFrom('lg')
                    ->getStateUsing(fn (Post $record): mixed => $record->updated_at)
                    ->dateTime('d/m/Y H:i')
                    ->alignment(Alignment::End),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        Post::STATUS_DRAFT => 'Nháp',
                        Post::STATUS_READY => 'Sẵn sàng duyệt',
                        Post::STATUS_PUBLISHED => 'Đã xuất bản',
                    ]),
            ])
            ->actions([
                ViewAction::make()->label('Xem'),
                EditAction::make()->label('Sửa'),
            ])
            ->actionsAlignment(Alignment::End->value)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Xóa đã chọn'),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Chưa có bài viết')
            ->emptyStateDescription('Dùng mục Import nội dung để crawl trang web và lưu bài.')
            ->emptyStateActions([
                TableAction::make('import')
                    ->label('Đến trang Import')
                    ->url(ImportContent::getUrl())
                    ->icon('heroicon-o-arrow-down-tray'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Tổng quan')
                    ->schema([
                        TextEntry::make('title')->label('Tiêu đề'),
                        TextEntry::make('slug')->label('Slug'),
                        TextEntry::make('source_url')->label('URL nguồn')->url(fn (Post $record): string => $record->source_url)->openUrlInNewTab(),
                        TextEntry::make('category')->label('Danh mục')->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Trạng thái')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                Post::STATUS_DRAFT => 'Nháp',
                                Post::STATUS_READY => 'Sẵn sàng duyệt',
                                Post::STATUS_PUBLISHED => 'Đã xuất bản',
                                default => $state,
                            }),
                        TextEntry::make('thumbnail')
                            ->label('Ảnh')
                            ->url(fn (Post $record): ?string => $record->thumbnail)
                            ->openUrlInNewTab()
                            ->visible(fn (Post $record): bool => filled($record->thumbnail)),
                    ])
                    ->columns(2),
                InfolistSection::make('Nội dung')
                    ->schema([
                        TextEntry::make('original_content')
                            ->label('Bản gốc')
                            ->columnSpanFull(),
                        TextEntry::make('ai_summary')
                            ->label('Tóm tắt (AI)')
                            ->columnSpanFull(),
                        TextEntry::make('ai_tags')
                            ->label('Thẻ (AI)')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('ai_rewritten_content')
                            ->label('Viết lại (AI)')
                            ->columnSpanFull(),
                        TextEntry::make('last_ai_error')
                            ->label('Lỗi AI')
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'view' => Pages\ViewPost::route('/{record}'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
