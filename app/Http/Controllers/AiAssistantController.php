<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiAssistantController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department' => ['required', 'in:marketing,operations'],
            'question' => ['required', 'string', 'max:600'],
            'context' => ['required', 'array'],
            'session_key' => ['nullable', 'string', 'max:80'],
        ]);

        $chatSession = $this->resolveChatSession($validated['department'], $validated['session_key'] ?? null, $validated['question']);
        $history = $this->historyForLlm((int) $chatSession->id);
        $llmAnswer = $this->answerWithConfiguredLlm($validated, $history);
        $answer = $llmAnswer ?? $this->answerLocally($validated);
        $answer = $this->formatAnswer($answer);

        $this->storeMessage((int) $chatSession->id, 'user', $validated['question'], $validated['context']);
        $this->storeMessage((int) $chatSession->id, 'assistant', $answer);

        DB::table('ai_chat_sessions')->where('id', $chatSession->id)->update([
            'department' => $validated['department'],
            'last_message_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'answer' => $answer,
            'session_key' => $chatSession->session_key,
            'messages' => $this->messagesForResponse((int) $chatSession->id),
            'mode' => $llmAnswer ? 'llm' : 'local_fallback',
        ]);
    }

    public function sessions(): JsonResponse
    {
        $sessions = DB::table('ai_chat_sessions')
            ->select(['session_key', 'title', 'department', 'last_message_at', 'created_at'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return response()->json(['data' => $sessions]);
    }

    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department' => ['nullable', 'in:marketing,operations'],
        ]);

        $session = $this->createChatSession($validated['department'] ?? 'marketing', 'New chat');

        return response()->json([
            'session_key' => $session->session_key,
            'messages' => [],
        ], 201);
    }

    public function messages(string $sessionKey): JsonResponse
    {
        $session = DB::table('ai_chat_sessions')->where('session_key', $sessionKey)->first();

        if (! $session) {
            return response()->json(['messages' => []]);
        }

        return response()->json([
            'session_key' => $session->session_key,
            'messages' => $this->messagesForResponse((int) $session->id),
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<int, array<string, string>> $history
     */
    private function answerWithConfiguredLlm(array $validated, array $history): ?string
    {
        $apiKey = config('services.openai.key');

        if (! $apiKey) {
            return null;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a concise D2C brand analytics assistant. Use the provided dashboard_context as the source of truth. If the context contains Marketing and Operations data, choose the relevant section from the user question. Never ask the user to provide metrics that are already present in context. Use readable formatting: short paragraphs, bullets when listing reasons/actions, and no markdown tables. Give direct answers with specific metric references and practical next steps.',
            ],
            ...$history,
            [
                'role' => 'user',
                'content' => json_encode([
                    'department' => $validated['department'],
                    'dashboard_context' => $validated['context'],
                    'question' => $validated['question'],
                ], JSON_PRETTY_PRINT),
            ],
        ];

        $baseUrl = rtrim((string) config('services.openai.base_url'), '/');
        $headers = [];

        if (Str::contains($baseUrl, 'openrouter.ai')) {
            $headers = [
                'HTTP-Referer' => (string) config('services.openai.app_url'),
                'X-Title' => (string) config('services.openai.app_name'),
            ];
        }

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders($headers)
                ->timeout(20)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => config('services.openai.model'),
                    'messages' => $messages,
                    'max_tokens' => 600,
                    'temperature' => 0.3,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();

        return trim((string) data_get($body, 'choices.0.message.content')) ?: null;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function answerLocally(array $validated): string
    {
        $context = $validated['context'];
        $question = Str::lower($validated['question']);

        if ($validated['department'] === 'marketing') {
            $context = $context['marketing'] ?? $context;
            $overview = $context['overview']['data'] ?? [];
            $campaigns = collect($context['campaigns']['data'] ?? []);
            $platforms = collect($context['platforms'] ?? [])->pluck('data')->filter();
            $weakCampaigns = $campaigns->sortBy('roas')->take(2)->map(fn ($row) => "{$row['name']} ({$row['roas']}x ROAS)")->implode(', ');

            if (Str::contains($question, ['platform', 'profitable', 'meta', 'google'])) {
                $bestPlatform = $platforms->sortByDesc(fn ($row) => (float) ($row['roas'] ?? 0))->first();
                $platformSummary = $platforms
                    ->map(fn ($row) => "{$row['platform_name']}: {$row['roas']}x ROAS, revenue INR " . number_format((float) ($row['revenue'] ?? 0)) . ', CAC INR ' . number_format((float) ($row['cac'] ?? 0)))
                    ->implode("\n- ");

                return "The more profitable platform is {$bestPlatform['platform_name']} based on ROAS.\n\nPlatform comparison:\n- {$platformSummary}\n\nWhy: {$bestPlatform['platform_name']} has the strongest revenue return for every rupee spent in the selected date range.";
            }

            if (Str::contains($question, ['roas', 'low', 'why'])) {
                return "Blended ROAS is {$overview['blended_roas']}x on spend of INR " . number_format((float) ($overview['total_spend'] ?? 0)) . ".\n\nFirst campaigns to inspect:\n- {$weakCampaigns}\n\nRecommended checks:\n- Creative fatigue\n- Audience overlap\n- Landing-page conversion before scaling spend";
            }

            if (Str::contains($question, ['cac', 'cost'])) {
                return "Blended CAC is INR " . number_format((float) ($overview['blended_cac'] ?? 0)) . ".\n\nActions:\n- Keep budget on campaigns above 2.5x ROAS\n- Reduce spend on the lowest ROAS campaigns\n- Watch whether CAC moves back under the target";
            }

            return "Marketing is currently at {$overview['blended_roas']}x ROAS with INR " . number_format((float) ($overview['revenue'] ?? 0)) . " revenue and {$overview['conversions']} conversions.\n\nRecommended focus:\n- Prioritize the highest ROAS campaigns\n- Review {$weakCampaigns} for efficiency leaks";
        }

        $context = $context['operations'] ?? $context;
        $overview = $context['overview']['data'] ?? [];
        $couriers = collect($context['couriers']['data'] ?? []);
        $weakCourier = $couriers->sortBy('performance_score')->first();
        $rtoReasons = collect($context['rto']['data'] ?? [])->take(2)->map(fn ($row) => "{$row['reason']} ({$row['rto_count']})")->implode(', ');

        if (Str::contains($question, ['suspend', 'courier', 'worst'])) {
            return "Review {$weakCourier['name']} first.\n\nCurrent signals:\n- RTO: {$weakCourier['rto_percent']}%\n- OTD: {$weakCourier['otd_percent']}%\n- Lost cases: {$weakCourier['lost_count']}\n- Score: {$weakCourier['performance_score']}\n\nRecommendation: throttle allocation first, then suspend only if the next few days do not improve.";
        }

        if (Str::contains($question, ['rto', 'high', 'reason'])) {
            return "RTO is {$overview['rto_rate']}%.\n\nLeading reasons:\n- {$rtoReasons}\n\nStart with controllable fixes:\n- Address quality checks\n- COD confirmation\n- Customer availability messaging";
        }

        return "Operations shows {$overview['total_orders']} orders, {$overview['otd_percent']}% OTD, {$overview['rto_rate']}% RTO, and {$overview['lost_cases']} lost cases.\n\nMost important actions:\n- Monitor weak courier lanes\n- Reduce controllable RTO reasons\n- Keep lost-case recovery moving";
    }

    private function resolveChatSession(string $department, ?string $sessionKey, string $question): object
    {
        if ($sessionKey) {
            $session = DB::table('ai_chat_sessions')->where('session_key', $sessionKey)->first();

            if ($session) {
                return $session;
            }
        }

        return $this->createChatSession($department, Str::limit($question, 56, ''));
    }

    private function createChatSession(string $department, string $title): object
    {
        $sessionKey = (string) Str::uuid();
        $now = now();

        DB::table('ai_chat_sessions')->insert([
            'session_key' => $sessionKey,
            'title' => $title ?: 'New chat',
            'department' => $department,
            'last_message_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('ai_chat_sessions')->where('session_key', $sessionKey)->first();
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function historyForLlm(int $sessionId): array
    {
        return DB::table('ai_chat_messages')
            ->where('ai_chat_session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn (object $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed>|null $context
     */
    private function storeMessage(int $sessionId, string $role, string $content, ?array $context = null): void
    {
        DB::table('ai_chat_messages')->insert([
            'ai_chat_session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'context_snapshot' => $context ? json_encode($context) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function messagesForResponse(int $sessionId): array
    {
        return DB::table('ai_chat_messages')
            ->where('ai_chat_session_id', $sessionId)
            ->select(['role', 'content', 'created_at'])
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    private function formatAnswer(string $answer): string
    {
        $answer = preg_replace("/[ \t]+\n/", "\n", trim($answer)) ?? trim($answer);
        $answer = preg_replace("/\n{3,}/", "\n\n", $answer) ?? $answer;

        return $answer;
    }
}
