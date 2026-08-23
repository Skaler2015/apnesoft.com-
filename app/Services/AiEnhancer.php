<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Settings;
use App\Support\Http;

/**
 * AI-assisted content enrichment for software pages.
 *
 * Calls the Anthropic Messages API (raw HTTP through the shared cURL helper —
 * the project ships no Composer dependencies) to turn a software record's REAL
 * existing metadata into a richer, SEO-friendly long description plus feature /
 * pros / cons lists.
 *
 * Hard guardrails (enforced in the system prompt): the model may only rephrase,
 * organise and expand on the facts it is given. It must NEVER invent a version,
 * developer, release date, price, award, security claim or capability the data
 * doesn't support. Missing information stays missing. This keeps the catalogue
 * factual and avoids the SEO / trust penalties that fabricated content brings.
 */
final class AiEnhancer
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    public const DEFAULT_MODEL = 'claude-haiku-4-5';

    /** Models offered in the admin UI (id => label). Cheapest first. */
    public const MODELS = [
        'claude-haiku-4-5' => 'Haiku 4.5 — fastest & cheapest (good for bulk)',
        'claude-sonnet-5'  => 'Sonnet 5 — balanced quality',
        'claude-opus-4-8'  => 'Opus 4.8 — highest quality (most expensive)',
    ];

    public static function isConfigured(): bool
    {
        return self::apiKey() !== null;
    }

    public static function isEnabled(): bool
    {
        return self::isConfigured() && (string) Settings::get('ai_enabled', '0') === '1';
    }

    /** Decrypt and return the stored Anthropic API key, or null if unset. */
    public static function apiKey(): ?string
    {
        $enc = (string) Settings::get('ai_api_key_enc', '');
        if ($enc === '') {
            // Allow an env fallback so keys can be kept out of the DB entirely.
            $env = getenv('ANTHROPIC_API_KEY');
            return $env !== false && $env !== '' ? $env : null;
        }
        $key = Crypto::decrypt($enc);
        return ($key !== null && $key !== '') ? $key : null;
    }

    public static function model(): string
    {
        $m = (string) Settings::get('ai_model', self::DEFAULT_MODEL);
        return isset(self::MODELS[$m]) ? $m : self::DEFAULT_MODEL;
    }

    /**
     * Enhance a single published/draft software row.
     *
     * @return array{ok:bool, message:string}
     */
    public static function enhance(int $softwareId): array
    {
        $key = self::apiKey();
        if ($key === null) {
            return ['ok' => false, 'message' => 'No Anthropic API key configured.'];
        }

        $s = Database::first('SELECT * FROM software WHERE id = :id', ['id' => $softwareId]);
        if (!$s) {
            return ['ok' => false, 'message' => 'Software not found.'];
        }

        self::ensureSchema();

        $gen = self::generate(self::factSheet($s));
        if (!$gen['ok']) {
            return ['ok' => false, 'message' => $gen['message']];
        }

        self::apply($softwareId, $s, $gen['data']);

        return ['ok' => true, 'message' => 'Enhanced with ' . self::MODELS[self::model()] . '.'];
    }

    /**
     * Ask the model for a list of REAL, well-known software matching a request
     * (e.g. "50 popular PDF tools"). Returns plain names to feed the publisher,
     * which then resolves each to its official source. Never invents software.
     *
     * @return string[] names
     */
    public static function softwareList(string $request, int $count = 30): array
    {
        $key = self::apiKey();
        if ($key === null) {
            return [];
        }
        $count = max(5, min(100, $count));
        $system = "You are a software catalogue assistant. Given a request, return real, "
            . "well-known software that genuinely exists and has an official download page. "
            . "Do NOT invent names. Respond with ONLY a JSON object: {\"names\": [\"...\"]} — "
            . "up to $count entries, each the software's common product name only (no version, no description).";
        $user = "Request: " . $request . "\nReturn up to $count real software names as JSON.";

        $resp = Http::postJson(self::ENDPOINT, [
            'model'      => self::model(),
            'max_tokens' => 1500,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ], ['x-api-key: ' . $key, 'anthropic-version: ' . self::API_VERSION]);

        if ($resp['status'] !== 200) {
            return [];
        }
        $data = json_decode($resp['body'], true);
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $parsed = self::parseJson($text);
        $names = is_array($parsed['names'] ?? null) ? $parsed['names'] : [];
        $out = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if ($n !== '') {
                $out[] = mb_substr($n, 0, 120);
            }
        }
        return array_slice(array_values(array_unique($out)), 0, $count);
    }

    /**
     * Call the model with a fact-sheet and return the parsed content (without
     * saving) — used by the admin "AI fill" so the form can be pre-populated.
     *
     * @return array{ok:bool, data:?array, message:string}
     */
    public static function generate(array $facts): array
    {
        $key = self::apiKey();
        if ($key === null) {
            return ['ok' => false, 'data' => null, 'message' => 'No Anthropic API key configured.'];
        }
        [$system, $user] = self::buildPrompt($facts);

        $resp = Http::postJson(self::ENDPOINT, [
            'model'      => self::model(),
            'max_tokens' => 2600,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ], [
            'x-api-key: ' . $key,
            'anthropic-version: ' . self::API_VERSION,
        ]);

        if ($resp['status'] !== 200) {
            return ['ok' => false, 'data' => null, 'message' => self::apiError($resp)];
        }
        $data = json_decode($resp['body'], true);
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $parsed = self::parseJson($text);
        if ($parsed === null) {
            return ['ok' => false, 'data' => null, 'message' => 'Could not parse the AI response.'];
        }
        return ['ok' => true, 'data' => $parsed, 'message' => 'ok'];
    }

    /**
     * Run a single instruction over a piece of text (grammar fix, translate,
     * rewrite) and return the transformed plain text. Used by the Add form's
     * inline AI helpers.
     *
     * @return array{ok:bool, text?:string, message?:string}
     */
    public static function transform(string $instruction, string $text): array
    {
        $key = self::apiKey();
        if ($key === null) {
            return ['ok' => false, 'message' => 'No Anthropic API key configured.'];
        }
        $resp = Http::postJson(self::ENDPOINT, [
            'model'      => self::model(),
            'max_tokens' => 1500,
            'system'     => 'You are a careful text editor. ' . $instruction
                . ' Do not add commentary, quotes or explanations — output only the resulting text.',
            'messages'   => [['role' => 'user', 'content' => $text]],
        ], [
            'x-api-key: ' . $key,
            'anthropic-version: ' . self::API_VERSION,
        ]);
        if ($resp['status'] !== 200) {
            return ['ok' => false, 'message' => self::apiError($resp)];
        }
        $data = json_decode($resp['body'], true);
        $out = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $out .= $block['text'];
            }
        }
        $out = trim($out);
        return $out !== '' ? ['ok' => true, 'text' => $out] : ['ok' => false, 'message' => 'Empty AI response.'];
    }

    /**
     * Enhance a batch of not-yet-enhanced published software.
     *
     * @return array{processed:int, enhanced:int, failed:int, message:string}
     */
    public static function enhanceBatch(int $limit = 10): array
    {
        if (!self::isConfigured()) {
            return ['processed' => 0, 'enhanced' => 0, 'failed' => 0, 'message' => 'No API key configured.'];
        }
        self::ensureSchema();

        $rows = Database::all(
            "SELECT id FROM software
             WHERE status = 'published' AND ai_enhanced_at IS NULL
             ORDER BY updated_at DESC LIMIT " . max(1, min(50, $limit))
        );

        $enhanced = 0;
        $failed = 0;
        $lastErr = '';
        foreach ($rows as $r) {
            $res = self::enhance((int) $r['id']);
            if ($res['ok']) {
                $enhanced++;
            } else {
                $failed++;
                $lastErr = $res['message'];
                // Stop early on auth / credit errors — retrying won't help.
                if (str_contains($lastErr, 'API key') || str_contains($lastErr, 'credit')
                    || str_contains($lastErr, '401') || str_contains($lastErr, '403')) {
                    break;
                }
            }
        }

        return [
            'processed' => count($rows),
            'enhanced'  => $enhanced,
            'failed'    => $failed,
            'message'   => $failed && $lastErr ? "Last error: $lastErr" : '',
        ];
    }

    /** How many published items still await enhancement. */
    public static function pendingCount(): int
    {
        try {
            self::ensureSchema();
            return (int) Database::scalar(
                "SELECT COUNT(*) FROM software WHERE status = 'published' AND ai_enhanced_at IS NULL"
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function enhancedCount(): int
    {
        try {
            self::ensureSchema();
            return (int) Database::scalar('SELECT COUNT(*) FROM software WHERE ai_enhanced_at IS NOT NULL');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // --- internals -----------------------------------------------------------

    /** Only pass the model facts we actually hold — never placeholders. */
    private static function factSheet(array $s): array
    {
        $catName = null;
        if (!empty($s['category_id'])) {
            $catName = Database::scalar('SELECT name FROM categories WHERE id = :id', ['id' => $s['category_id']]);
        }
        return array_filter([
            'name'             => $s['name'] ?? null,
            'developer'        => $s['developer_name'] ?? null,
            'category'         => $catName,
            'operating_system' => $s['operating_system'] ?? null,
            'license'          => $s['license_type'] ?? null,
            'price_type'       => $s['price_type'] ?? null,
            'open_source'      => !empty($s['is_open_source']) ? 'yes' : null,
            'official_website' => $s['official_website'] ?? null,
            'existing_short'   => $s['short_description'] ?? null,
            'existing_long'    => $s['long_description'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');
    }

    /** @return array{0:string,1:string} [system, user] */
    private static function buildPrompt(array $facts): array
    {
        $system = <<<SYS
        You are a factual technical writer for a software download catalogue. You turn
        a set of KNOWN FACTS about a piece of software into clean, useful, SEO-friendly
        catalogue copy.

        ABSOLUTE RULES — breaking any of these is a failure:
        1. Use ONLY the facts provided. Do NOT invent or guess a version number,
           developer, release date, price, file size, download count, award, rating,
           or any capability that the facts do not support.
        2. Never make absolute security or safety claims ("100% safe", "virus-free",
           "guaranteed"). Describe, don't promise.
        3. If you are not confident a feature is real, leave it out. Fewer, accurate
           items are better than more, invented ones. It is fine to return an empty
           list for features, pros, or cons.
        4. Neutral, informative tone. No marketing hype, no fabricated superlatives.
        5. Do not mention that you are an AI or that the text was generated.

        Respond with ONLY a JSON object (no markdown fences, no commentary) of the form:
        {
          "short_description": "one factual sentence, max 155 chars",
          "long_description": "a thorough, well-structured description of AT LEAST 400 words in 4-6 plain-text paragraphs (separate paragraphs with a blank line): what the software is, who makes it, what it's used for, its main capabilities, who it's for, how it's licensed. Rich and readable — but strictly grounded in the facts and general, widely-known information about this software. Do NOT invent version numbers, dates, prices or specific statistics.",
          "features": ["short factual capability", "..."],
          "pros": ["grounded advantage", "..."],
          "cons": ["grounded limitation or consideration", "..."],
          "tags": ["lowercase keyword", "..."],
          "minimum_requirements": "a few lines of GENERAL, commonly-known system requirements (e.g. supported OS versions). Keep it conservative — do NOT invent exact RAM/CPU/disk numbers you are unsure of. Empty string if unknown."
        }
        Give 4-6 features, 3-5 pros, 1-3 cons, and 4-8 tags. Keep list items under 120 characters.
        SYS;

        $user = "KNOWN FACTS (JSON):\n" . json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nWrite the catalogue copy for this software. Base it on these facts plus what is widely and reliably known about this specific software; never fabricate versions, dates, prices or precise specs.";

        return [$system, $user];
    }

    /** Extract the first JSON object from the model's text output. */
    private static function parseJson(string $text): ?array
    {
        $text = trim($text);
        // Strip ```json fences if the model added them despite instructions.
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $json = substr($text, $start, $end - $start + 1);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /** Persist the enhanced content, then regenerate SEO. */
    private static function apply(int $softwareId, array $s, array $parsed): void
    {
        $update = [];

        $short = trim((string) ($parsed['short_description'] ?? ''));
        if ($short !== '') {
            $update['short_description'] = mb_substr($short, 0, 320);
        }
        $long = trim((string) ($parsed['long_description'] ?? ''));
        if ($long !== '') {
            $update['long_description'] = $long;
        }
        $req = trim((string) ($parsed['minimum_requirements'] ?? ''));
        if ($req !== '' && empty($s['minimum_requirements'])) {
            $update['minimum_requirements'] = mb_substr($req, 0, 2000);
        }
        $update['ai_enhanced_at'] = gmdate('Y-m-d H:i:s');

        Database::update('software', $update, ['id' => $softwareId]);

        // Tags (create + link).
        if (is_array($parsed['tags'] ?? null)) {
            foreach (array_slice($parsed['tags'], 0, 10) as $tag) {
                $tag = trim((string) $tag);
                $slug = slugify($tag);
                if ($slug === '') {
                    continue;
                }
                $tagId = (int) Database::scalar('SELECT id FROM tags WHERE slug = :s', ['s' => $slug]);
                if (!$tagId) {
                    Database::run('INSERT IGNORE INTO tags (name, slug) VALUES (:n, :s)', ['n' => mb_substr($tag, 0, 80), 's' => $slug]);
                    $tagId = (int) Database::scalar('SELECT id FROM tags WHERE slug = :s', ['s' => $slug]);
                }
                if ($tagId) {
                    Database::run('INSERT IGNORE INTO software_tags (software_id, tag_id) VALUES (:s, :t)', ['s' => $softwareId, 't' => $tagId]);
                }
            }
        }

        // Replace only the AI-managed feature/pro/con rows (leave any others intact
        // by clearing per-type before reinserting).
        foreach (['feature' => 'features', 'pro' => 'pros', 'con' => 'cons'] as $type => $key) {
            $items = $parsed[$key] ?? [];
            if (!is_array($items) || $items === []) {
                continue;
            }
            Database::run('DELETE FROM software_features WHERE software_id = :id AND `type` = :t',
                ['id' => $softwareId, 't' => $type]);
            $order = 0;
            foreach ($items as $label) {
                $label = trim((string) $label);
                if ($label === '') {
                    continue;
                }
                Database::run(
                    'INSERT INTO software_features (software_id, `type`, label, sort_order) VALUES (:id, :t, :l, :o)',
                    ['id' => $softwareId, 't' => $type, 'l' => mb_substr($label, 0, 300), 'o' => $order++]
                );
            }
        }

        Seo::generateForSoftware($softwareId);
    }

    private static function apiError(array $resp): string
    {
        if (!empty($resp['error'])) {
            return 'Network error: ' . $resp['error'];
        }
        $data = json_decode($resp['body'], true);
        $msg = $data['error']['message'] ?? ('HTTP ' . $resp['status']);
        return 'API error (' . $resp['status'] . '): ' . $msg;
    }

    /** Add the ai_enhanced_at tracking column once, if it isn't present yet. */
    private static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        $exists = Database::scalar(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'software' AND COLUMN_NAME = 'ai_enhanced_at'"
        );
        if ((int) $exists === 0) {
            try {
                Database::run('ALTER TABLE software ADD COLUMN ai_enhanced_at DATETIME NULL DEFAULT NULL');
            } catch (\Throwable $e) {
                // Concurrent add or insufficient privilege — non-fatal.
            }
        }
    }
}
