<?php

namespace App\Domain\Rag\Services;

use App\Domain\Ai\Dto\ChatMessage;
use App\Models\Conversation;

class PromptBuilder
{
    public function systemPrompt(): string
    {
        return <<<'TXT'
You are InsightDeck, an assistant that answers questions strictly using the provided document excerpts.

Rules:
1. Only use information from the EXCERPTS section. If the answer is not contained there, say so plainly.
2. Cite excerpts inline as [n] where n is the excerpt number. Cite every factual claim that comes from the document.
3. Be concise. Prefer bullet points for lists. Use Markdown formatting.
4. Never fabricate page numbers, quotes, or sources.
5. If the user asks a follow-up that needs different evidence, ask for clarification rather than guess.
TXT;
    }

    /**
     * @param  array<int, array{id:int, document_id:int, page_number:?int, content:string, similarity:float}>  $chunks
     */
    public function buildContext(array $chunks): string
    {
        if (empty($chunks)) {
            return "EXCERPTS:\n(none — answer that you do not have enough context)";
        }
        $lines = ["EXCERPTS:"];
        foreach ($chunks as $i => $c) {
            $n = $i + 1;
            $page = $c['page_number'] !== null ? "p.{$c['page_number']}" : 'p.?';
            $lines[] = "[{$n}] (doc#{$c['document_id']} {$page}) {$c['content']}";
        }
        return implode("\n\n", $lines);
    }

    /**
     * @return ChatMessage[]
     */
    public function historyMessages(Conversation $conversation, int $maxTurns = 10): array
    {
        $messages = $conversation->messages()
            ->latest('created_at')
            ->limit($maxTurns * 2)
            ->get()
            ->reverse()
            ->values();

        $out = [];
        foreach ($messages as $m) {
            $out[] = new ChatMessage(role: (string) $m->role->value, content: (string) $m->content);
        }
        return $out;
    }
}
