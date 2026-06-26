<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $history = $this->historyForLlm($chatSession->id);
        $llmAnswer = $this->answerWithConfiguredLlm($validated, $history);
        $answer = $this->formatAnswer($llmAnswer ?? $this->answerLocally($validated));

        $this->storeMessage($chatSession, 'user', $validated['question'], $validated['context']);
        $this->storeMessage($chatSession, 'assistant', $answer);

        $chatSession->update([
            'department' => $validated['department'],
            'last_message_at' => now(),
        ]);

        return response()->json([
            'answer' => $answer,
            'session_key' => $chatSession->session_key,
            'messages' => $this->messagesForResponse($chatSession->id),
            'mode' => $llmAnswer ? 'llm' : 'local_fallback',
        ]);
    }

    public function sessions(): JsonResponse
    {
        $sessions = AiChatSession::query()
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
        $session = AiChatSession::query()->where('session_key', $sessionKey)->first();

        if (! $session) {
            return response()->json(['messages' => []]);
        }

        return response()->json([
            'session_key' => $session->session_key,
            'messages' => $this->messagesForResponse($session->id),
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
                'content' => 'You are a concise D2C brand analytics assistant. Answer only from the provided dashboard context. Use readable formatting: short paragraphs, bullets when listing reasons/actions, and no markdown tables. Give specific metric references and one or two practical recommendations.',
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

        return trim((string) data_get($response->json(), 'choices.0.message.content')) ?: null;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function answerLocally(array $validated): string
    {
        $context = $validated['context'];
        $question = Str::lower($validated['question']);

        if ($validated['department'] === 'marketing') {
            $marketingContext = $context['marketing'] ?? $context;
            $overview = $marketingContext['overview']['data'] ?? [];
            $campaigns = collect($marketingContext['campaigns']['data'] ?? []);
            $weakCampaigns = $campaigns->sortBy('roas')->take(2)->map(fn ($row) => "{$row['name']} ({$row['roas']}x ROAS)")->implode(', ');
            $blendedRoas = $overview['blended_roas'] ?? 0;
            $blendedCac = $overview['blended_cac'] ?? 0;
            $revenue = $overview['revenue'] ?? 0;
            $conversions = $overview['conversions'] ?? 0;
            $totalSpend = $overview['total_spend'] ?? 0;
            $platforms = collect($marketingContext['platforms'] ?? [])
                ->map(fn ($row) => $row['data'] ?? $row)
                ->sortByDesc('roas')
                ->values();
            $bestPlatform = $platforms->first();

            if (Str::contains($question, ['roas', 'low', 'why'])) {
                return "Blended ROAS is {$blendedRoas}x on spend of INR " . number_format((float) $totalSpend) . ".\n\nFirst campaigns to inspect:\n- {$weakCampaigns}\n\nRecommended checks:\n- Creative fatigue\n- Audience overlap\n- Landing-page conversion before scaling spend";
            }

            if (Str::contains($question, ['cac', 'cost'])) {
                return "Blended CAC is INR " . number_format((float) $blendedCac) . ".\n\nActions:\n- Keep budget on campaigns above 2.5x ROAS\n- Reduce spend on the lowest ROAS campaigns\n- Watch whether CAC moves back under the target";
            }

            if (Str::contains($question, ['profit', 'profitable', 'best platform', 'which platform'])) {
                $bestName = $bestPlatform['platform_name'] ?? 'the top platform';
                $bestRoas = $bestPlatform['roas'] ?? 0;

                return "{$bestName} looks the most profitable right now at {$bestRoas}x ROAS.\n\nSupport view:\n- Total revenue: INR " . number_format((float) $revenue) . "\n- Total spend: INR " . number_format((float) $totalSpend) . "\n- Conversions: {$conversions}\n\nA practical next step is to shift more budget toward the higher-ROAS platform and keep testing the weaker one.";
            }

            return "Marketing is currently at {$blendedRoas}x ROAS with INR " . number_format((float) $revenue) . " revenue and {$conversions} conversions.\n\nRecommended focus:\n- Prioritize the highest ROAS campaigns\n- Review {$weakCampaigns} for efficiency leaks";
        }

        $operationsContext = $context['operations'] ?? $context;
        $overview = $operationsContext['overview']['data'] ?? [];
        $couriers = collect($operationsContext['couriers']['data'] ?? []);
        $weakCourier = $couriers->sortBy('performance_score')->first();
        $rtoReasons = collect($operationsContext['rto']['data'] ?? [])->take(2)->map(fn ($row) => "{$row['reason']} ({$row['rto_count']})")->implode(', ');
        $totalOrders = $overview['total_orders'] ?? 0;
        $otdPercent = $overview['otd_percent'] ?? 0;
        $rtoRate = $overview['rto_rate'] ?? 0;
        $lostCases = $overview['lost_cases'] ?? 0;

        if (Str::contains($question, ['suspend', 'courier', 'worst'])) {
            $courierName = $weakCourier['name'] ?? 'the weakest courier';
            $rtoPercent = $weakCourier['rto_percent'] ?? 0;
            $weakOtd = $weakCourier['otd_percent'] ?? 0;
            $lostCount = $weakCourier['lost_count'] ?? 0;
            $score = $weakCourier['performance_score'] ?? 0;

            return "Review {$courierName} first.\n\nCurrent signals:\n- RTO: {$rtoPercent}%\n- OTD: {$weakOtd}%\n- Lost cases: {$lostCount}\n- Score: {$score}\n\nRecommendation: throttle allocation first, then suspend only if the next few days do not improve.";
        }

        if (Str::contains($question, ['rto', 'high', 'reason'])) {
            return "RTO is {$rtoRate}%.\n\nLeading reasons:\n- {$rtoReasons}\n\nStart with controllable fixes:\n- Address quality checks\n- COD confirmation\n- Customer availability messaging";
        }

        return "Operations shows {$totalOrders} orders, {$otdPercent}% OTD, {$rtoRate}% RTO, and {$lostCases} lost cases.\n\nMost important actions:\n- Monitor weak courier lanes\n- Reduce controllable RTO reasons\n- Keep lost-case recovery moving";
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function historyForLlm(int $sessionId): array
    {
        return AiChatMessage::query()
            ->where('ai_chat_session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (AiChatMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }

    private function createChatSession(string $department, string $title): AiChatSession
    {
        return AiChatSession::query()->create([
            'session_key' => (string) Str::uuid(),
            'title' => $title ?: 'New chat',
            'department' => $department,
            'last_message_at' => now(),
        ]);
    }

    private function resolveChatSession(string $department, ?string $sessionKey, string $question): AiChatSession
    {
        if ($sessionKey) {
            $session = AiChatSession::query()->where('session_key', $sessionKey)->first();

            if ($session) {
                return $session;
            }
        }

        return $this->createChatSession($department, Str::limit($question, 56, ''));
    }

    /**
     * @param array<string, mixed>|null $context
     */
    private function storeMessage(AiChatSession $session, string $role, string $content, ?array $context = null): void
    {
        AiChatMessage::query()->create([
            'ai_chat_session_id' => $session->id,
            'role' => $role,
            'content' => $content,
            'context_snapshot' => $context,
        ]);
    }

    private function messagesForResponse(int $sessionId): array
    {
        return AiChatMessage::query()
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
