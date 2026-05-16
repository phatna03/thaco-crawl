<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    /**
     * @return array{summary: string, tags: string, rewritten: string}
     *         tags = một chuỗi "từ khóa 1, từ khóa 2, ..."
     */
    public function analyzeForCms(string $title, string $originalContent, ?string $writingStyle = null): array
    {
        $tone = $this->resolveWritingStyle($writingStyle);
        $excerpt = $this->truncateForPrompt($originalContent);
        $summaryMax = max(20, (int) config('services.ai.summary_max_words', 100));
        $rewriteMin = max(50, (int) config('services.ai.rewritten_min_words', 250));
        $tagMax = max(3, min(8, (int) config('services.ai.max_tag_count', 5)));

        $system = <<<SYS
Bạn là biên tập viên CMS. Trả về DUY NHẤT một JSON (không markdown).

Bắt buộc đúng 3 khóa, mọi giá trị là CHUỖI (string):
- "summary" — tóm tắt tiếng Việt. Tối đa {$summaryMax} từ (đếm bằng cách tách khoảng trắng giữa các cụm).
- "tags" — MỘT chuỗi duy nhất: từ 3 đến {$tagMax} từ khóa, chỉ phân tách bằng dấu phẩy (ví dụ: "Metro số 2, THACO, TP.HCM"). Từ khóa suy từ tiêu đề + nội dung gửi kèm, không cần HTML. Không dùng: bài viết, tin tức, tổng hợp.
- "rewritten" — bài viết lại toàn bộ ý chính, tiếng Việt, theo văn phong user. Tối thiểu {$rewriteMin} từ (đếm bằng khoảng trắng). Không câu mở đầu xã giao; có thể nhiều đoạn.

Ví dụ format: {"summary":"...","tags":"A, B, C","rewritten":"..."}
SYS;

        $user = "Văn phong khi viết lại:\n{$tone}\n\nTiêu đề: {$title}\n\nNội dung gốc:\n{$excerpt}";

        $decoded = $this->completeJsonWithRetry($system, $user);
        $summary = $this->stringField($decoded, 'summary');
        $rewritten = $this->stringField($decoded, 'rewritten');
        $tags = $this->tagsToCommaString($decoded, $title, $excerpt);

        $summary = $this->limitWordCount($summary, $summaryMax);
        $rewritten = $this->expandRewrittenIfShort($rewritten, $rewriteMin, $tone, $title, $excerpt);

        return [
            'summary' => $summary,
            'tags' => $tags,
            'rewritten' => $rewritten,
        ];
    }

    public function generateSummary(string $title, string $originalContent): string
    {
        return $this->analyzeForCms($title, $originalContent, null)['summary'];
    }

    /**
     * @return list<string>
     */
    public function generateTags(string $title, string $originalContent): array
    {
        return $this->commaStringToTagList($this->analyzeForCms($title, $originalContent, null)['tags']);
    }

    public function rewriteContent(string $title, string $originalContent): string
    {
        return $this->analyzeForCms($title, $originalContent, null)['rewritten'];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function stringField(array $decoded, string $key): string
    {
        $v = $decoded[$key] ?? null;

        return is_string($v) ? trim($v) : '';
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function tagsToCommaString(array $decoded, string $title, string $excerpt): string
    {
        $raw = $decoded['tags'] ?? $decoded['keywords'] ?? $decoded['labels'] ?? null;
        $s = $this->normalizeTagsString($raw);
        if ($s !== '' && $this->commaStringToTagList($s) !== []) {
            return $s;
        }

        return $this->fetchTagsCommaOnly($title, $excerpt);
    }

    private function normalizeTagsString(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }
        if (is_string($raw)) {
            return $this->sanitizeCommaTagsString($raw);
        }
        if (is_array($raw)) {
            $parts = [];
            foreach ($raw as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $parts[] = trim($item);
                } elseif (is_scalar($item) && ! is_bool($item)) {
                    $t = trim((string) $item);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                }
            }

            return $this->sanitizeCommaTagsString(implode(', ', $parts));
        }

        return '';
    }

    private function sanitizeCommaTagsString(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $parts = preg_split('/[,;|，、\n]+/u', $s) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $t = trim((string) $p);
            if ($t !== '') {
                $out[] = $t;
            }
        }
        $out = array_values(array_unique($out));
        $max = max(3, min(8, (int) config('services.ai.max_tag_count', 5)));
        $out = array_slice($out, 0, $max);

        return implode(', ', $out);
    }

    /**
     * @return list<string>
     */
    private function commaStringToTagList(string $comma): array
    {
        if (trim($comma) === '') {
            return [];
        }
        $parts = preg_split('/[,;|，、\n]+/u', $comma) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $t = trim((string) $p);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }

    private function fetchTagsCommaOnly(string $title, string $excerpt): string
    {
        $tagMax = max(3, min(8, (int) config('services.ai.max_tag_count', 5)));
        $system = <<<SYS
Trả về DUY NHẤT JSON một khóa: {"tags":"..."}.
Giá trị tags là MỘT chuỗi gồm 3 đến {$tagMax} từ khóa tiếng Việt, cách nhau bằng dấu phẩy. Suy ra từ nội dung. Không dùng mảng JSON. Không "tin tức","bài viết".
SYS;
        $user = "Tiêu đề: {$title}\n\nNội dung:\n".mb_substr($excerpt, 0, 6000);

        try {
            $raw = trim($this->completeJsonRaw($system, $user));
            $decoded = json_decode($this->stripJsonFences($raw), true);
            if (! is_array($decoded)) {
                return '';
            }

            return $this->normalizeTagsString($decoded['tags'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('ai.tags_comma_only_failed', ['message' => $e->getMessage()]);

            return '';
        }
    }

    private function countWords(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? count($parts) : 0;
    }

    private function limitWordCount(string $text, int $maxWords): string
    {
        $text = trim($text);
        if ($text === '' || $maxWords < 1) {
            return $text;
        }
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) <= $maxWords) {
            return $text;
        }

        return implode(' ', array_slice($parts, 0, $maxWords));
    }

    private function expandRewrittenIfShort(string $rewritten, int $minWords, string $tone, string $title, string $excerpt): string
    {
        if ($this->countWords($rewritten) >= $minWords) {
            return $rewritten;
        }

        $system = <<<SYS
Trả về DUY NHẤT JSON: {"rewritten":"..."} — một chuỗi duy nhất.
Bài viết lại tiếng Việt, tối thiểu {$minWords} từ (đếm bằng khoảng trắng), đủ ý, mạch lạc, theo văn phong user. Không xã giao.
SYS;

        $user = "Văn phong:\n{$tone}\n\nTiêu đề: {$title}\n\nBản hiện có (cần mở rộng, giữ ý chính):\n{$rewritten}\n\n---\nTham chiếu nguồn:\n".mb_substr($excerpt, 0, 12000);

        try {
            $raw = trim($this->completeJsonRaw($system, $user));
            $decoded = json_decode($this->stripJsonFences($raw), true);
            $r = is_array($decoded) && isset($decoded['rewritten']) && is_string($decoded['rewritten'])
                ? trim($decoded['rewritten'])
                : '';
            if ($r !== '' && $this->countWords($r) >= $minWords) {
                return $r;
            }
            if ($r !== '' && $this->countWords($r) > $this->countWords($rewritten)) {
                return $r;
            }
        } catch (\Throwable $e) {
            Log::warning('ai.rewritten_expand_failed', ['message' => $e->getMessage()]);
        }

        return $rewritten;
    }

    private function resolveWritingStyle(?string $writingStyle): string
    {
        $t = $writingStyle !== null ? trim($writingStyle) : '';
        if ($t === '') {
            $t = (string) config('services.ai.default_writing_style', '');
        }

        return $t !== '' ? $t : 'Chuyên nghiệp, súc tích, dễ đọc, tiếng Việt tự nhiên.';
    }

    private function truncateForPrompt(string $text): string
    {
        $max = max(2000, (int) config('services.ai.max_input_chars', 12000));
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max)."\n\n[… nội dung đã được cắt bớt theo AI_MAX_INPUT_CHARS …]";
    }

    /**
     * @return array<string, mixed>
     */
    private function completeJsonWithRetry(string $system, string $user): array
    {
        $attempts = max(1, (int) config('services.ai.retry_attempts', 3));
        $last = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $raw = trim($this->completeJsonRaw($system, $user));
                $decoded = json_decode($this->stripJsonFences($raw), true);
                if (! is_array($decoded)) {
                    throw new \RuntimeException('AI trả về JSON không parse được.');
                }

                return $decoded;
            } catch (\Throwable $e) {
                $last = $e;
                Log::warning('ai.analyze_attempt_failed', [
                    'attempt' => $i + 1,
                    'message' => $e->getMessage(),
                ]);
                if ($i < $attempts - 1 && $this->isRetriableFailure($e)) {
                    usleep(450_000 * ($i + 1));

                    continue;
                }

                throw $e;
            }
        }

        throw $last ?? new \RuntimeException('Phân tích AI thất bại.');
    }

    private function isRetriableFailure(\Throwable $e): bool
    {
        $m = strtolower($e->getMessage());

        return str_contains($m, '429')
            || str_contains($m, 'rate')
            || str_contains($m, '503')
            || str_contains($m, '502')
            || str_contains($m, '500')
            || str_contains($m, 'timeout')
            || str_contains($m, 'timed out')
            || str_contains($m, 'connection');
    }

    private function completeJsonRaw(string $system, string $user): string
    {
        $driver = strtolower((string) config('services.ai.driver', 'openai'));

        return match ($driver) {
            'openai' => $this->completeJsonOpenAi($system, $user),
            default => $this->completeJsonGemini($system, $user),
        };
    }

    private function completeJsonOpenAi(string $system, string $user): string
    {
        $key = config('services.openai.key');
        if (! filled($key)) {
            throw new \RuntimeException('Chưa cấu hình OPENAI_API_KEY (AI_DRIVER=openai).');
        }

        $timeout = (int) config('services.openai.timeout', config('services.ai.timeout', 120));
        $maxOut = max(512, (int) config('services.ai.max_output_tokens', 4096));

        $response = Http::withToken($key)
            ->timeout($timeout)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.35,
                'max_tokens' => $maxOut,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            $msg = $response->json('error.message') ?? $response->body();
            $msgStr = is_string($msg) ? $msg : 'OpenAI trả về lỗi.';
            Log::warning('ai.chat_failed', [
                'provider' => 'openai',
                'status' => $response->status(),
                'message' => $msgStr,
            ]);

            throw new \RuntimeException($msgStr);
        }

        $choice = $response->json('choices.0.message.content');

        if (! is_string($choice) || $choice === '') {
            throw new \RuntimeException('OpenAI không trả về nội dung hợp lệ.');
        }

        return $choice;
    }

    private function completeJsonGemini(string $system, string $user): string
    {
        $key = config('services.gemini.key');
        if (! filled($key)) {
            throw new \RuntimeException('Chưa cấu hình GEMINI_API_KEY (AI_DRIVER=gemini).');
        }

        $model = (string) config('services.gemini.model', 'gemini-2.0-flash');
        $timeout = (int) config('services.ai.timeout', 120);
        $maxOut = max(512, (int) config('services.ai.max_output_tokens', 4096));

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?%s',
            rawurlencode($model),
            http_build_query(['key' => $key])
        );

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $system]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $user]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.35,
                'maxOutputTokens' => $maxOut,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $msg = $response->json('error.message') ?? $response->body();
            $msgStr = is_string($msg) ? $msg : 'Gemini API trả về lỗi.';
            Log::warning('ai.chat_failed', [
                'provider' => 'gemini',
                'status' => $response->status(),
                'message' => $msgStr,
            ]);

            throw new \RuntimeException($msgStr);
        }

        $text = $this->extractGeminiText($response->json() ?? []);

        if ($text === '') {
            throw new \RuntimeException('Gemini không trả về nội dung (có thể bị chặn bởi chính sách an toàn).');
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractGeminiText(?array $json): string
    {
        if ($json === null) {
            return '';
        }

        $parts = data_get($json, 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return '';
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        return implode('', $chunks);
    }

    private function stripJsonFences(string $raw): string
    {
        $t = trim($raw);
        if (str_starts_with($t, '```')) {
            $t = preg_replace('/^```(?:json)?\s*/i', '', $t) ?? $t;
            $t = preg_replace('/\s*```$/', '', $t) ?? $t;
        }

        return $t;
    }
}
