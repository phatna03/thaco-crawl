<?php

namespace App\Filament\Admin\Resources\PostResource\Pages;

use App\Filament\Admin\Resources\PostResource;
use App\Services\AIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->label('Xem'),
            Actions\DeleteAction::make()->label('Xóa'),
            Actions\Action::make('reanalyze')
                ->label('Phân tích lại (AI)')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Chạy lại phân tích AI?')
                ->modalDescription('Kết quả AI hiện tại sẽ bị thay thế bằng bản mới.')
                ->action(function (): void {
                    /** @var AIService $ai */
                    $ai = app(AIService::class);
                    $record = $this->record;

                    try {
                        $tone = trim((string) ($record->ai_tone ?? ''));
                        $result = $ai->analyzeForCms(
                            (string) $record->title,
                            (string) $record->original_content,
                            $tone !== '' ? $tone : null
                        );
                        $record->ai_summary = $result['summary'];
                        $record->ai_tags = $result['tags'];
                        $record->ai_rewritten_content = $result['rewritten'];
                        $record->last_ai_error = null;
                        $record->save();

                        Notification::make()
                            ->title('Đã phân tích lại')
                            ->success()
                            ->send();

                        $this->refreshFormData(['ai_summary', 'ai_tags', 'ai_rewritten_content', 'last_ai_error']);
                    } catch (\Throwable $e) {
                        Log::warning('ai.reanalyze_failed', [
                            'post_id' => $record->getKey(),
                            'message' => $e->getMessage(),
                        ]);
                        $record->last_ai_error = $e->getMessage();
                        $record->save();

                        Notification::make()
                            ->title('Lỗi khi phân tích AI')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        $this->refreshFormData(['last_ai_error']);
                    }
                }),
        ];
    }
}
