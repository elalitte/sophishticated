<?php

namespace App\Services;

class JsonTemplateStorage
{
    private string $packsDir;
    private ?array $data = null;

    public function __construct()
    {
        $this->packsDir = __DIR__ . '/../../templates/packs';
    }

    // ── Loading ───────────────────────────────────────────────────────

    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $templates = [];
        $landingPages = [];
        $packs = [];
        $seenLpRefs = [];
        $seenTplKeys = [];

        $files = glob($this->packsDir . '/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            $content = json_decode(file_get_contents($file), true);
            if (!$content) {
                continue;
            }

            $packs[$filename] = $content;

            foreach ($content['landing_pages'] ?? [] as $idx => $lp) {
                $ref = $lp['ref'] ?? null;
                if ($ref && isset($seenLpRefs[$ref])) {
                    continue; // skip duplicate
                }
                if ($ref) {
                    $seenLpRefs[$ref] = true;
                }
                $lp['_pack'] = $filename;
                $lp['_index'] = $idx;
                $landingPages[] = $lp;
            }

            foreach ($content['email_templates'] ?? [] as $idx => $tpl) {
                $key = ($tpl['name'] ?? '') . '||' . ($tpl['subject'] ?? '');
                if (isset($seenTplKeys[$key])) {
                    continue; // skip duplicate
                }
                $seenTplKeys[$key] = true;
                $tpl['_pack'] = $filename;
                $tpl['_index'] = $idx;
                $templates[] = $tpl;
            }
        }

        // Auto-assign IDs if missing
        $this->assignIds($landingPages, 'landing_page');
        $this->assignIds($templates, 'email_template');

        $this->data = [
            'templates' => $templates,
            'landing_pages' => $landingPages,
            'packs' => $packs,
        ];

        return $this->data;
    }

    private function assignIds(array &$items, string $type): void
    {
        $maxId = 0;
        foreach ($items as $item) {
            if (isset($item['id']) && is_int($item['id'])) {
                $maxId = max($maxId, $item['id']);
            }
        }

        $changed = false;
        foreach ($items as &$item) {
            if (!isset($item['id']) || !is_int($item['id'])) {
                $item['id'] = ++$maxId;
                $changed = true;
            }
        }
        unset($item);

        if ($changed) {
            $this->persistItems($items, $type);
        }
    }

    private function invalidateCache(): void
    {
        $this->data = null;
    }

    // ── Persistence ───────────────────────────────────────────────────

    private function persistItems(array $items, string $type): void
    {
        $key = $type === 'email_template' ? 'email_templates' : 'landing_pages';

        $byPack = [];
        foreach ($items as $item) {
            $pack = $item['_pack'];
            $byPack[$pack][] = $item;
        }

        foreach ($byPack as $filename => $packItems) {
            $path = $this->packsDir . '/' . $filename;
            $content = json_decode(file_get_contents($path), true);
            if (!$content) {
                continue;
            }

            $content[$key] = array_map(function ($item) {
                return $this->stripMeta($item);
            }, $packItems);

            $this->writePack($path, $content);
        }
    }

    private function persistSingleItem(array $item, string $type): void
    {
        $key = $type === 'email_template' ? 'email_templates' : 'landing_pages';
        $filename = $item['_pack'];
        $path = $this->packsDir . '/' . $filename;

        $content = json_decode(file_get_contents($path), true);
        if (!$content) {
            return;
        }

        if (isset($item['_index'])) {
            $content[$key][$item['_index']] = $this->stripMeta($item);
        } else {
            $content[$key][] = $this->stripMeta($item);
        }

        $this->writePack($path, $content);
    }

    private function removeFromPack(string $filename, int $index, string $type): void
    {
        $key = $type === 'email_template' ? 'email_templates' : 'landing_pages';
        $path = $this->packsDir . '/' . $filename;

        $content = json_decode(file_get_contents($path), true);
        if (!$content) {
            return;
        }

        array_splice($content[$key], $index, 1);
        $this->writePack($path, $content);
    }

    private function writePack(string $path, array $content): void
    {
        $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json, LOCK_EX);
    }

    private function stripMeta(array $item): array
    {
        unset($item['_pack'], $item['_index']);
        return $item;
    }

    private function getCustomPackPath(): string
    {
        $path = $this->packsDir . '/custom.json';
        if (!file_exists($path)) {
            $this->writePack($path, [
                'name' => 'Custom',
                'version' => '1.0',
                'author' => 'Utilisateur',
                'description' => 'Templates personnalisés créés depuis l\'interface.',
                'landing_pages' => [],
                'email_templates' => [],
            ]);
        }
        return $path;
    }

    // ── Email Templates ───────────────────────────────────────────────

    public function findAllTemplates(array $filters = []): array
    {
        $data = $this->load();
        $templates = $data['templates'];

        if (!empty($filters['difficulty'])) {
            $templates = array_filter($templates, fn($t) => ($t['difficulty_level'] ?? 0) == $filters['difficulty']);
        }

        if (isset($filters['is_active'])) {
            $templates = array_filter($templates, fn($t) => ($t['is_active'] ?? 1) == $filters['is_active']);
        }

        if (!empty($filters['tags'])) {
            $tag = $filters['tags'];
            $templates = array_filter($templates, function ($t) use ($tag) {
                $tags = $t['tags'] ?? [];
                if (is_string($tags)) {
                    return str_contains($tags, $tag);
                }
                return in_array($tag, $tags);
            });
        }

        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $templates = array_filter($templates, fn($t) =>
                str_contains(mb_strtolower($t['name'] ?? ''), $search) ||
                str_contains(mb_strtolower($t['subject'] ?? ''), $search)
            );
        }

        return array_values(array_map([$this, 'normalizeTemplate'], $templates));
    }

    public function findTemplateById(int $id): ?array
    {
        $data = $this->load();
        foreach ($data['templates'] as $tpl) {
            if (($tpl['id'] ?? 0) === $id) {
                return $this->normalizeTemplate($tpl);
            }
        }
        return null;
    }

    public function findTemplateRaw(int $id): ?array
    {
        $data = $this->load();
        foreach ($data['templates'] as $tpl) {
            if (($tpl['id'] ?? 0) === $id) {
                return $tpl;
            }
        }
        return null;
    }

    public function createTemplate(array $input): array
    {
        $data = $this->load();

        $maxId = 0;
        foreach ($data['templates'] as $t) {
            $maxId = max($maxId, $t['id'] ?? 0);
        }

        $landingPageRef = null;
        if (!empty($input['landing_page_id'])) {
            $lp = $this->findLandingPageById((int) $input['landing_page_id']);
            if ($lp) {
                $landingPageRef = $lp['ref'] ?? null;
            }
        }

        $template = [
            'id' => $maxId + 1,
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'subject' => $input['subject'] ?? '',
            'sender_name' => $input['sender_name'] ?? null,
            'sender_email' => $input['sender_email'] ?? null,
            'html_body' => $input['html_body'] ?? '',
            'difficulty_level' => (int) ($input['difficulty_level'] ?? 3),
            'tags' => $input['tags'] ?? [],
            'is_active' => (int) ($input['is_active'] ?? 1),
            'landing_page_ref' => $landingPageRef,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (is_string($template['tags'])) {
            $template['tags'] = json_decode($template['tags'], true) ?: [];
        }

        $this->getCustomPackPath();
        $template['_pack'] = 'custom.json';

        $path = $this->packsDir . '/custom.json';
        $content = json_decode(file_get_contents($path), true);
        $content['email_templates'][] = $this->stripMeta($template);
        $this->writePack($path, $content);

        $this->invalidateCache();
        return $this->normalizeTemplate($template);
    }

    public function updateTemplate(int $id, array $input): ?array
    {
        $raw = $this->findTemplateRaw($id);
        if (!$raw) {
            return null;
        }

        $allowedFields = [
            'name', 'description', 'subject', 'html_body',
            'sender_name', 'sender_email', 'difficulty_level', 'tags', 'is_active',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $raw[$field] = $input[$field];
            }
        }

        if (array_key_exists('landing_page_id', $input)) {
            if ($input['landing_page_id']) {
                $lp = $this->findLandingPageById((int) $input['landing_page_id']);
                $raw['landing_page_ref'] = $lp ? ($lp['ref'] ?? null) : null;
            } else {
                $raw['landing_page_ref'] = null;
            }
        }

        if (isset($raw['tags']) && is_string($raw['tags'])) {
            $raw['tags'] = json_decode($raw['tags'], true) ?: [];
        }

        $raw['updated_at'] = date('Y-m-d H:i:s');

        $this->persistSingleItem($raw, 'email_template');
        $this->invalidateCache();

        return $this->normalizeTemplate($raw);
    }

    public function deleteTemplate(int $id): bool
    {
        $raw = $this->findTemplateRaw($id);
        if (!$raw) {
            return false;
        }

        $this->removeFromPack($raw['_pack'], $raw['_index'], 'email_template');
        $this->invalidateCache();
        return true;
    }

    // ── Landing Pages ─────────────────────────────────────────────────

    public function findAllLandingPages(array $filters = []): array
    {
        $data = $this->load();
        $pages = $data['landing_pages'];

        if (isset($filters['is_active'])) {
            $pages = array_filter($pages, fn($p) => ($p['is_active'] ?? 1) == $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $pages = array_filter($pages, fn($p) =>
                str_contains(mb_strtolower($p['name'] ?? ''), $search)
            );
        }

        return array_values(array_map([$this, 'normalizeLandingPage'], $pages));
    }

    public function findLandingPageById(int $id): ?array
    {
        $data = $this->load();
        foreach ($data['landing_pages'] as $lp) {
            if (($lp['id'] ?? 0) === $id) {
                return $this->normalizeLandingPage($lp);
            }
        }
        return null;
    }

    public function findLandingPageByRef(string $ref): ?array
    {
        $data = $this->load();
        foreach ($data['landing_pages'] as $lp) {
            if (($lp['ref'] ?? '') === $ref) {
                return $this->normalizeLandingPage($lp);
            }
        }
        return null;
    }

    private function findLandingPageRaw(int $id): ?array
    {
        $data = $this->load();
        foreach ($data['landing_pages'] as $lp) {
            if (($lp['id'] ?? 0) === $id) {
                return $lp;
            }
        }
        return null;
    }

    public function createLandingPage(array $input): array
    {
        $data = $this->load();

        $maxId = 0;
        foreach ($data['landing_pages'] as $lp) {
            $maxId = max($maxId, $lp['id'] ?? 0);
        }

        $ref = 'lp-' . $this->slugify($input['name']);

        $page = [
            'id' => $maxId + 1,
            'ref' => $ref,
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'html_content' => $input['html_content'] ?? null,
            'capture_fields' => $input['capture_fields'] ?? [],
            'redirect_url' => $input['redirect_url'] ?? '/awareness',
            'awareness_html' => $input['awareness_html'] ?? null,
            'is_active' => (int) ($input['is_active'] ?? 1),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (is_string($page['capture_fields'])) {
            $page['capture_fields'] = json_decode($page['capture_fields'], true) ?: [];
        }

        $this->getCustomPackPath();
        $page['_pack'] = 'custom.json';

        $path = $this->packsDir . '/custom.json';
        $content = json_decode(file_get_contents($path), true);
        $content['landing_pages'][] = $this->stripMeta($page);
        $this->writePack($path, $content);

        $this->invalidateCache();
        return $this->normalizeLandingPage($page);
    }

    public function updateLandingPage(int $id, array $input): ?array
    {
        $raw = $this->findLandingPageRaw($id);
        if (!$raw) {
            return null;
        }

        $allowedFields = [
            'name', 'description', 'html_content', 'awareness_html',
            'capture_fields', 'redirect_url', 'is_active',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $raw[$field] = $input[$field];
            }
        }

        if (isset($raw['capture_fields']) && is_string($raw['capture_fields'])) {
            $raw['capture_fields'] = json_decode($raw['capture_fields'], true) ?: [];
        }

        $raw['updated_at'] = date('Y-m-d H:i:s');

        $this->persistSingleItem($raw, 'landing_page');
        $this->invalidateCache();

        return $this->normalizeLandingPage($raw);
    }

    public function deleteLandingPage(int $id): bool
    {
        $raw = $this->findLandingPageRaw($id);
        if (!$raw) {
            return false;
        }

        $this->removeFromPack($raw['_pack'], $raw['_index'], 'landing_page');
        $this->invalidateCache();
        return true;
    }

    // ── DB Sync ───────────────────────────────────────────────────────

    public function syncToDb(\PDO $db): void
    {
        $data = $this->load();

        // Collect JSON IDs
        $jsonLpIds = [];
        $jsonTplIds = [];

        // Sync landing pages via upsert
        foreach ($data['landing_pages'] as $lp) {
            $norm = $this->normalizeLandingPage($lp);
            $jsonLpIds[] = $norm['id'];
            $stmt = $db->prepare(
                "INSERT INTO landing_pages (id, name, description, html_content, awareness_html, capture_fields, redirect_url, is_active, created_at, updated_at)
                 VALUES (:id, :name, :description, :html_content, :awareness_html, :capture_fields, :redirect_url, :is_active, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name), description = VALUES(description), html_content = VALUES(html_content),
                    awareness_html = VALUES(awareness_html), capture_fields = VALUES(capture_fields),
                    redirect_url = VALUES(redirect_url), is_active = VALUES(is_active), updated_at = VALUES(updated_at)"
            );
            $stmt->execute([
                ':id' => $norm['id'],
                ':name' => $norm['name'],
                ':description' => $norm['description'] ?? null,
                ':html_content' => $norm['html_content'] ?? null,
                ':awareness_html' => $norm['awareness_html'] ?? null,
                ':capture_fields' => is_array($norm['capture_fields'] ?? null) ? json_encode($norm['capture_fields']) : ($norm['capture_fields'] ?? null),
                ':redirect_url' => $norm['redirect_url'] ?? null,
                ':is_active' => $norm['is_active'] ?? 1,
                ':created_at' => $norm['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $norm['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }

        // Sync email templates via upsert
        foreach ($data['templates'] as $tpl) {
            $norm = $this->normalizeTemplate($tpl);
            $jsonTplIds[] = $norm['id'];
            $stmt = $db->prepare(
                "INSERT INTO email_templates (id, name, description, subject, sender_name, sender_email, html_body, landing_page_id, difficulty_level, tags, is_active, created_at, updated_at)
                 VALUES (:id, :name, :description, :subject, :sender_name, :sender_email, :html_body, :landing_page_id, :difficulty_level, :tags, :is_active, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name), description = VALUES(description), subject = VALUES(subject),
                    sender_name = VALUES(sender_name), sender_email = VALUES(sender_email), html_body = VALUES(html_body),
                    landing_page_id = VALUES(landing_page_id), difficulty_level = VALUES(difficulty_level),
                    tags = VALUES(tags), is_active = VALUES(is_active), updated_at = VALUES(updated_at)"
            );
            $stmt->execute([
                ':id' => $norm['id'],
                ':name' => $norm['name'],
                ':description' => $norm['description'] ?? null,
                ':subject' => $norm['subject'] ?? '',
                ':sender_name' => $norm['sender_name'] ?? null,
                ':sender_email' => $norm['sender_email'] ?? null,
                ':html_body' => $norm['html_body'] ?? '',
                ':landing_page_id' => $norm['landing_page_id'] ?? null,
                ':difficulty_level' => $norm['difficulty_level'] ?? 3,
                ':tags' => is_array($norm['tags'] ?? null) ? json_encode($norm['tags']) : ($norm['tags'] ?? null),
                ':is_active' => $norm['is_active'] ?? 1,
                ':created_at' => $norm['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $norm['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }

        // Clean up DB rows that no longer exist in JSON (only if not referenced by campaigns)
        if ($jsonTplIds) {
            $placeholders = implode(',', array_fill(0, count($jsonTplIds), '?'));
            $db->prepare(
                "DELETE FROM email_templates WHERE id NOT IN ({$placeholders})
                 AND id NOT IN (SELECT email_template_id FROM campaigns WHERE email_template_id IS NOT NULL)"
            )->execute($jsonTplIds);
        }
        if ($jsonLpIds) {
            $placeholders = implode(',', array_fill(0, count($jsonLpIds), '?'));
            $db->prepare(
                "DELETE FROM landing_pages WHERE id NOT IN ({$placeholders})
                 AND id NOT IN (
                    SELECT et.landing_page_id FROM email_templates et
                    JOIN campaigns c ON c.email_template_id = et.id
                    WHERE et.landing_page_id IS NOT NULL
                 )"
            )->execute($jsonLpIds);
        }
    }

    // ── Normalization ─────────────────────────────────────────────────

    private function normalizeTemplate(array $tpl): array
    {
        $data = $this->load();

        // Resolve landing_page_ref to landing_page_id
        $landingPageId = null;
        if (!empty($tpl['landing_page_ref'])) {
            foreach ($data['landing_pages'] as $lp) {
                if (($lp['ref'] ?? '') === $tpl['landing_page_ref']) {
                    $landingPageId = $lp['id'] ?? null;
                    break;
                }
            }
        }

        $tags = $tpl['tags'] ?? [];
        if (is_string($tags)) {
            $tags = json_decode($tags, true) ?: [];
        }

        return [
            'id' => $tpl['id'] ?? 0,
            'name' => $tpl['name'] ?? '',
            'description' => $tpl['description'] ?? null,
            'subject' => $tpl['subject'] ?? '',
            'sender_name' => $tpl['sender_name'] ?? null,
            'sender_email' => $tpl['sender_email'] ?? null,
            'html_body' => $tpl['html_body'] ?? '',
            'landing_page_id' => $landingPageId,
            'landing_page_ref' => $tpl['landing_page_ref'] ?? null,
            'difficulty_level' => (int) ($tpl['difficulty_level'] ?? 3),
            'difficulty' => (int) ($tpl['difficulty_level'] ?? 3),
            'tags' => $tags,
            'is_active' => (int) ($tpl['is_active'] ?? 1),
            'created_at' => $tpl['created_at'] ?? null,
            'updated_at' => $tpl['updated_at'] ?? null,
        ];
    }

    private function normalizeLandingPage(array $lp): array
    {
        $captureFields = $lp['capture_fields'] ?? [];
        if (is_string($captureFields)) {
            $captureFields = json_decode($captureFields, true) ?: [];
        }

        return [
            'id' => $lp['id'] ?? 0,
            'ref' => $lp['ref'] ?? null,
            'name' => $lp['name'] ?? '',
            'description' => $lp['description'] ?? null,
            'html_content' => $lp['html_content'] ?? null,
            'capture_fields' => $captureFields,
            'capture_credentials' => !empty($captureFields) ? 1 : 0,
            'redirect_url' => $lp['redirect_url'] ?? null,
            'awareness_html' => $lp['awareness_html'] ?? null,
            'is_active' => (int) ($lp['is_active'] ?? 1),
            'created_at' => $lp['created_at'] ?? null,
            'updated_at' => $lp['updated_at'] ?? null,
        ];
    }

    private function slugify(string $text): string
    {
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        } else {
            $text = mb_strtolower($text);
        }
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
