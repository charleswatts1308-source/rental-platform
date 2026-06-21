<?php

namespace App\Services;

/**
 * D16 / A2 — template-save-time placeholder validation.
 *
 * Mechanical only (21 Jun ruling): rejects unknown/misspelled tokens and
 * malformed braces against the renderer's live whitelist. It does NOT
 * detect "dropped" required tokens — a genuinely dropped token is absent
 * text, which A3's mandatory preview surfaces plainly, and a required-set
 * check would false-positive on legitimate rewording.
 *
 * The whitelist is read LIVE from LetterTemplateRenderer::WHITELIST — never
 * copied — so a placeholder added to the renderer is honoured here without
 * a second edit.
 */
class PlaceholderValidator
{
    /** The token grammar the renderer substitutes against. */
    private const TOKEN_RE = '/\{\{\s*([a-z_][a-z0-9_]*)\s*\}\}/i';

    /**
     * Human-readable problems with placeholder usage in $text.
     * Empty array == clean.
     *
     * @return list<string>
     */
    public function problems(string $text): array
    {
        $problems = [];

        // 1. Well-formed tokens whose name is not on the renderer whitelist
        //    (catches misspellings like {{issue_desciption}}).
        preg_match_all(self::TOKEN_RE, $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (! in_array($match[1], LetterTemplateRenderer::WHITELIST, true)) {
                $problems[] = "Unknown placeholder \"{$match[0]}\" — not a recognised field.";
            }
        }

        // 2. Malformed braces: strip every well-formed token (whitelisted or
        //    not — those are handled above), then any remaining brace cluster
        //    is a broken placeholder attempt, e.g. "{ {notice_number}}",
        //    "{{notice_number}", "{{name} }".
        $stripped = preg_replace(self::TOKEN_RE, '', $text);
        if (preg_match('/\{\s*\{|\}\s*\}|\{\{|\}\}/', $stripped) === 1) {
            $problems[] = 'Malformed placeholder braces detected — check every {{...}} is correctly paired.';
        }

        return $problems;
    }

    public function isValid(string $text): bool
    {
        return $this->problems($text) === [];
    }
}
