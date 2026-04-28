<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Dto\ChatChunk;
use App\Domain\Ai\Dto\ChatRequest;
use App\Domain\Ai\Dto\EmbeddingResult;

/**
 * Deterministic offline provider for local development, the public demo, and CI.
 *
 * - Embeddings are derived from a hash of the input text, so identical inputs
 *   always produce the same vector and similarity search still picks the
 *   closest chunk to a question.
 * - Chat completion echoes the retrieved excerpts back as a synthesized answer
 *   that includes a few `[n]` citations so the citation UI can be exercised.
 */
class FakeAiProvider implements AiProvider
{
    public function __construct(protected string $providerName = 'openai') {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function usesByoKey(): bool
    {
        return false;
    }

    public function streamChat(ChatRequest $request): iterable
    {
        $text = $this->fakeAnswer($request);
        $tokens = max(1, (int) ceil(mb_strlen($text) / 4));

        // Stream one word at a time with a tiny delay so the UI shows the typewriter effect.
        $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($words as $w) {
            if ($w === '') continue;
            usleep(15_000);
            yield new ChatChunk($w, false);
        }

        $promptTokens = max(1, (int) ceil(mb_strlen($this->systemAndMessages($request)) / 4));
        yield new ChatChunk('', true, $promptTokens, $tokens);
    }

    public function chat(ChatRequest $request): ChatChunk
    {
        $text = $this->fakeAnswer($request);
        return new ChatChunk(
            delta: $text,
            done: true,
            promptTokens: max(1, (int) ceil(mb_strlen($this->systemAndMessages($request)) / 4)),
            completionTokens: max(1, (int) ceil(mb_strlen($text) / 4)),
        );
    }

    public function embed(array $texts, ?string $model = null): EmbeddingResult
    {
        $embeddings = [];
        $totalTokens = 0;
        foreach ($texts as $text) {
            $embeddings[] = $this->deterministicVector((string) $text, 1536);
            $totalTokens += max(1, (int) ceil(mb_strlen((string) $text) / 4));
        }
        return new EmbeddingResult(
            embeddings: $embeddings,
            tokensUsed: $totalTokens,
            model: $model ?: 'fake-embedding',
        );
    }

    public function testConnection(): bool
    {
        return true;
    }

    /**
     * Build a deterministic 1536-dim unit vector from a SHA-256 expansion of the text.
     */
    protected function deterministicVector(string $text, int $dims): array
    {
        $bytes = '';
        $counter = 0;
        while (strlen($bytes) < $dims * 4) {
            $bytes .= hash('sha256', $text.'#'.$counter, true);
            $counter++;
        }
        $vec = [];
        for ($i = 0; $i < $dims; $i++) {
            // Map 4 bytes → uint32 → [-1,1)
            $u = unpack('N', substr($bytes, $i * 4, 4))[1];
            $vec[] = (($u / 2147483648) - 1.0);
        }
        // Normalize.
        $sum = 0.0;
        foreach ($vec as $v) $sum += $v * $v;
        $norm = sqrt($sum) ?: 1.0;
        foreach ($vec as $i => $v) $vec[$i] = $v / $norm;
        return $vec;
    }

    protected function systemAndMessages(ChatRequest $request): string
    {
        $s = $request->system ?? '';
        foreach ($request->messages as $m) {
            $s .= "\n".$m->role.': '.$m->content;
        }
        return $s;
    }

    protected function fakeAnswer(ChatRequest $request): string
    {
        $userMessage = '';
        foreach (array_reverse($request->messages) as $m) {
            if ($m->role === 'user') { $userMessage = $m->content; break; }
        }

        $system = $request->system ?? '';

        // 1) RAG chat: prompt contains numbered EXCERPTS — surface them with [n] citations.
        if (preg_match_all('/\[(\d+)\]\s*\(doc#\d+\s*p\.\d+\)\s*([^\n]{1,160})/', $system, $m)) {
            $cites = array_slice($m[1] ?? [], 0, 3);
            $snippets = array_slice($m[2] ?? [], 0, 3);
            $bullets = [];
            foreach ($snippets as $i => $snip) {
                $bullets[] = '- '.trim($snip).' ['.$cites[$i].']';
            }
            return "Based on the document excerpts, here's what I found:\n\n"
                .implode("\n", $bullets)
                ."\n\n_Offline mode — set `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` for real model responses._";
        }

        // 2) Title generation — short fixed-length output.
        if (str_contains($system, 'short, descriptive chat titles') || preg_match('/^Suggest a 3-6 word title/i', $userMessage)) {
            $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $userMessage);
            $words = preg_split('/\s+/u', $clean) ?: [];
            $picked = array_slice(array_filter($words, fn ($w) => mb_strlen($w) > 2), 0, 4);
            return implode(' ', $picked) ?: 'New conversation';
        }

        // 3) Email drafting — produce a believable email.
        if (str_contains($system, 'email drafter') || str_contains($userMessage, 'GOAL:')) {
            return $this->fakeEmail($userMessage);
        }

        // 4) Summarization — produce structured Markdown.
        if (str_contains($system, 'careful summarizer')) {
            return $this->fakeSummary($userMessage);
        }

        return "Offline response to: ".mb_substr($userMessage, 0, 240).
            "\n\n_Offline mode — set `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` for real model responses._";
    }

    protected function fakeEmail(string $userMessage): string
    {
        $goal = '';
        if (preg_match('/GOAL:\s*([^\n]+)/', $userMessage, $g)) $goal = trim($g[1]);
        $recipient = 'team';
        if (preg_match('/RECIPIENT:\s*([^\n]+)/', $userMessage, $r)) $recipient = trim($r[1]);
        $tone = 'friendly';
        if (preg_match('/TONE:\s*([^\n]+)/', $userMessage, $t)) $tone = trim($t[1]);

        $opener = match ($tone) {
            'formal' => "Dear $recipient,",
            'direct' => "$recipient,",
            'empathetic' => "Hi $recipient — thank you for understanding.",
            default => "Hi $recipient,",
        };
        $closer = match ($tone) {
            'formal' => "Kind regards,\n[Your name]",
            'direct' => "Thanks,\n[Your name]",
            'empathetic' => "With appreciation,\n[Your name]",
            default => "Thanks,\n[Your name]",
        };

        return $opener
            ."\n\nQuick note on your message — ".mb_substr($goal, 0, 120)."."
            ."\n\nI'd suggest we proceed by aligning on the key points first, then sharing a short async summary so everyone stays unblocked. Happy to adjust if a different cadence works better for you."
            ."\n\n$closer\n\n_(Offline draft — set `OPENAI_API_KEY` for real LLM-generated copy.)_";
    }

    protected function fakeSummary(string $userMessage): string
    {
        $title = 'this document';
        if (preg_match('/DOCUMENT TITLE:\s*([^\n]+)/', $userMessage, $m)) $title = trim($m[1]);

        // Extract a few sentence fragments from the body for verisimilitude.
        $body = '';
        if (preg_match('/CONTENT:\s*(.+)/s', $userMessage, $m)) $body = $m[1];
        $sentences = preg_split('/(?<=[.!?])\s+/u', preg_replace('/\s+/', ' ', $body)) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences), fn ($s) => mb_strlen($s) > 10));
        $picked = array_slice($sentences, 0, 4);

        return "## $title — summary\n\n"
            .(empty($picked)
                ? "*The document had no extractable text — make sure it isn't a scanned image PDF.*"
                : implode("\n", array_map(fn ($s) => "- ".mb_substr($s, 0, 220), $picked)))
            ."\n\n_Offline summary — set `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` for an LLM-generated summary._";
    }
}
