<?php

namespace App\Controllers;

use App\Services\JsonTemplateStorage;

class PackController
{
    private \PDO $db;
    private string $packsDir;
    private JsonTemplateStorage $storage;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->packsDir = __DIR__ . '/../../templates/packs';
        $this->storage = new JsonTemplateStorage();
    }

    /**
     * GET /api/packs
     * List available packs (from filesystem).
     */
    public function index(): void
    {
        $packs = [];

        if (!is_dir($this->packsDir)) {
            json_response(['data' => $packs]);
            return;
        }

        $files = glob($this->packsDir . '/*.json') ?: [];

        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if (!$content) {
                continue;
            }

            $packs[] = [
                'filename'              => basename($file),
                'name'                  => $content['name'] ?? basename($file, '.json'),
                'version'               => $content['version'] ?? '1.0',
                'author'                => $content['author'] ?? 'Unknown',
                'description'           => $content['description'] ?? '',
                'landing_pages_count'   => count($content['landing_pages'] ?? []),
                'email_templates_count' => count($content['email_templates'] ?? []),
            ];
        }

        json_response(['data' => $packs]);
    }

    /**
     * GET /api/packs/{filename}
     * Get full details of a specific pack.
     */
    public function show(string $filename): void
    {
        $filename = basename($filename);
        $path = $this->packsDir . '/' . $filename;

        if (!file_exists($path)) {
            json_response(['error' => 'Pack not found'], 404);
        }

        $pack = json_decode(file_get_contents($path), true);
        if (!$pack) {
            json_response(['error' => 'Invalid pack file'], 422);
        }

        $pack['filename'] = $filename;

        json_response($pack);
    }

    /**
     * POST /api/packs/import
     * Import a pack: copy the JSON file into the packs directory and sync to DB.
     */
    public function import(): void
    {
        $input = get_json_input();

        $pack = null;
        $targetFilename = null;

        // Option 1: import from an existing pack file (already on disk)
        if (!empty($input['filename'])) {
            $filename = basename($input['filename']);
            $path = $this->packsDir . '/' . $filename;
            if (!file_exists($path)) {
                json_response(['error' => 'Pack file not found'], 404);
            }
            $pack = json_decode(file_get_contents($path), true);
            $targetFilename = $filename;
        }
        // Option 2: inline pack data (uploaded by the user)
        elseif (!empty($input['pack'])) {
            $pack = $input['pack'];
            $name = $pack['name'] ?? 'imported-pack';
            $targetFilename = $this->slugify($name) . '.json';
        }

        if (!$pack || !is_array($pack)) {
            json_response(['error' => 'No valid pack data provided'], 422);
        }

        // Write the pack file to disk (creates or overwrites)
        $targetPath = $this->packsDir . '/' . $targetFilename;
        $json = json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($targetPath, $json, LOCK_EX);

        // Sync all JSON data to DB for campaign/tracking compatibility
        $this->storage->syncToDb($this->db);

        $landingPagesCount = count($pack['landing_pages'] ?? []);
        $templatesCount = count($pack['email_templates'] ?? []);

        audit_log('import_pack', 'pack', 0, [
            'pack_name'    => $pack['name'] ?? 'unknown',
            'filename'     => $targetFilename,
            'landing_pages' => $landingPagesCount,
            'templates'    => $templatesCount,
        ]);

        json_response([
            'message'                => 'Pack imported successfully',
            'imported_landing_pages' => $landingPagesCount,
            'imported_templates'     => $templatesCount,
        ], 201);
    }

    /**
     * POST /api/packs/export
     * Export selected templates and landing pages as a pack JSON.
     */
    public function export(): void
    {
        $input = get_json_input();

        $packName = $input['name'] ?? 'Exported Pack';
        $packDescription = $input['description'] ?? '';
        $templateIds = $input['template_ids'] ?? [];
        $landingPageIds = $input['landing_page_ids'] ?? [];

        $landingPages = [];
        $lpRefMap = [];

        // Collect landing pages
        foreach ($landingPageIds as $lpId) {
            $lp = $this->storage->findLandingPageById((int) $lpId);
            if ($lp) {
                $ref = $lp['ref'] ?? 'lp-' . $this->slugify($lp['name']);
                $lpRefMap[$lp['id']] = $ref;
                $landingPages[] = [
                    'ref'            => $ref,
                    'name'           => $lp['name'],
                    'description'    => $lp['description'],
                    'html_content'   => $lp['html_content'],
                    'capture_fields' => $lp['capture_fields'],
                    'redirect_url'   => $lp['redirect_url'],
                    'awareness_html' => $lp['awareness_html'],
                ];
            }
        }

        // Collect email templates
        $emailTemplates = [];
        foreach ($templateIds as $tplId) {
            $tpl = $this->storage->findTemplateById((int) $tplId);
            if (!$tpl) {
                continue;
            }

            // Auto-include linked landing page
            $lpRef = $tpl['landing_page_ref'] ?? null;
            if ($tpl['landing_page_id'] && !isset($lpRefMap[$tpl['landing_page_id']])) {
                $lp = $this->storage->findLandingPageById($tpl['landing_page_id']);
                if ($lp) {
                    $ref = $lp['ref'] ?? 'lp-' . $this->slugify($lp['name']);
                    $lpRefMap[$lp['id']] = $ref;
                    $lpRef = $ref;
                    $landingPages[] = [
                        'ref'            => $ref,
                        'name'           => $lp['name'],
                        'description'    => $lp['description'],
                        'html_content'   => $lp['html_content'],
                        'capture_fields' => $lp['capture_fields'],
                        'redirect_url'   => $lp['redirect_url'],
                        'awareness_html' => $lp['awareness_html'],
                    ];
                }
            } elseif ($tpl['landing_page_id'] && isset($lpRefMap[$tpl['landing_page_id']])) {
                $lpRef = $lpRefMap[$tpl['landing_page_id']];
            }

            $emailTemplates[] = [
                'name'             => $tpl['name'],
                'description'      => $tpl['description'],
                'subject'          => $tpl['subject'],
                'sender_name'      => $tpl['sender_name'],
                'sender_email'     => $tpl['sender_email'],
                'html_body'        => $tpl['html_body'],
                'difficulty_level' => (int) $tpl['difficulty_level'],
                'tags'             => $tpl['tags'] ?: [],
                'landing_page_ref' => $lpRef,
            ];
        }

        $pack = [
            'name'            => $packName,
            'version'         => '1.0',
            'author'          => current_user()['username'] ?? 'unknown',
            'description'     => $packDescription,
            'landing_pages'   => $landingPages,
            'email_templates' => $emailTemplates,
        ];

        if (!empty($input['save_to_file'])) {
            $filename = $this->slugify($packName) . '.json';
            $filepath = $this->packsDir . '/' . $filename;
            file_put_contents($filepath, json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            $pack['filename'] = $filename;
        }

        json_response($pack);
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
