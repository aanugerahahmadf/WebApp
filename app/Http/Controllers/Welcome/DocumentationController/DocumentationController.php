<?php

namespace App\Http\Controllers\Welcome\DocumentationController;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentationController extends Controller
{
    /**
     * Render the Markdown documentation as a small, public documentation site.
     */
    public function index(Request $request): \Illuminate\Contracts\View\View
    {
        $documents = $this->documents();
        $selected = $request->query('doc', 'project-readme');

        if (! isset($documents[$selected])) {
            $selected = 'project-readme';
        }

        return $this->render($selected, $documents);
    }

    public function show(string $path): \Illuminate\Contracts\View\View
    {
        $documents = $this->documents();
        $key = trim($path, '/');
        $key = preg_replace('/\.md$/i', '', $key);
        $key = Str::startsWith($key, 'docs/')
            ? Str::after($key, 'docs/')
            : $key;

        if (! isset($documents[$key])) {
            throw new NotFoundHttpException;
        }

        return $this->render($key, $documents);
    }

    public function code(string $path): \Illuminate\Contracts\View\View
    {
        $relative = ltrim(rawurldecode($path), '/');
        $file = realpath(base_path($relative));
        $base = realpath(base_path());

        if ($file === false || $base === false || ! Str::startsWith(str_replace('\\', '/', $file), str_replace('\\', '/', $base).'/')) {
            throw new NotFoundHttpException;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($extension, ['php', 'js', 'ts', 'tsx', 'jsx', 'css', 'html', 'json', 'md'], true)) {
            throw new NotFoundHttpException;
        }

        return view('Welcome.pages.documentation.code', [
            'title' => basename($file),
            'path' => str_replace('\\', '/', $relative),
            'code' => file_get_contents($file) ?: '',
        ]);
    }

    public function snippet(int $index, string $path): \Illuminate\Contracts\View\View
    {
        $key = trim(preg_replace('/\.md$/i', '', rawurldecode($path)), '/');
        $documents = $this->documents();

        if (! isset($documents[$key])) {
            throw new NotFoundHttpException;
        }

        $markdown = file_get_contents(base_path($documents[$key]['file'])) ?: '';
        preg_match_all('/```([^\r\n]*)\r?\n(.*?)```/s', $markdown, $matches, PREG_SET_ORDER);

        if (! isset($matches[$index])) {
            throw new NotFoundHttpException;
        }

        $info = trim($matches[$index][1]);
        $language = preg_replace('/\s+title=(?:"[^"]+"|\'[^\']+\')/i', '', $info) ?: 'text';
        $title = preg_match('/\btitle=(?:"([^"]+)"|\'([^\']+)\')/i', $info, $titleMatch)
            ? ($titleMatch[1] ?? $titleMatch[2])
            : strtoupper($language).' code example';

        return view('Welcome.pages.documentation.code', [
            'title' => $title,
            'path' => 'Dokumentasi: '.$documents[$key]['title'],
            'code' => rtrim($matches[$index][2], "\r\n"),
        ]);
    }

    /**
     * Build the shared page payload: selected document plus a sidebar grouped
     * by category. Grouping happens server-side so the Blade template only
     * needs a nested loop (and does not depend on loop internals).
     *
     * @param  array<string, array{file: string, title: string, category: string}>  $documents
     * @return array<string, array{documents: array<string, array{file: string, title: string, category: string}>, groups: array<int, array{label: string, items: array<int, array{key: string, title: string}>}>, selected: string, document: string}>
     */
    private function render(string $selected, array $documents): \Illuminate\Contracts\View\View
    {
        $groups = [];

        foreach ($documents as $key => $item) {
            $groups[$item['category']][] = [
                'key' => $key,
                'title' => $item['title'],
            ];
        }

        return view('Welcome.pages.documentation.documentation', [
            'documents' => $documents,
            'groups' => collect($groups)
                ->map(fn (array $items, string $label): array => ['label' => $label, 'items' => $items])
                ->values()
                ->all(),
            'selected' => $selected,
            'document' => $this->renderDocument($selected, $documents),
        ]);
    }

    /**
     * @return array<string, array{file: string, title: string, category: string}>
     */
    private function documents(): array
    {
        $root = base_path('docs');
        $files = collect(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            ),
        )
            ->filter(fn (\SplFileInfo $file): bool => $file->isFile() && strtolower($file->getExtension()) === 'md')
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->sort()
            ->values();

        $documents = [];

        // The repository README is the landing document for the public portal.
        // Keep it outside docs/ on disk, but expose it beside the documentation
        // index so the complete project documentation is available from /docs.
        $readme = base_path('README.md');
        if (is_file($readme)) {
            $contents = file_get_contents($readme) ?: '';
            $documents['project-readme'] = [
                'file' => 'README.md',
                'title' => preg_match('/^#\s+(.+)$/m', $contents, $match)
                    ? trim($match[1])
                    : 'README',
                'category' => 'Mulai di sini',
            ];
        }

        $license = base_path('LICENSE');
        if (is_file($license)) {
            $documents['project-license'] = [
                'file' => 'LICENSE',
                'title' => 'Lisensi MIT',
                'category' => 'Dokumen proyek',
            ];
        }

        // Include other project-level Markdown guides, while keeping the
        // internal docs/ tree handled below. Agent/tooling instructions are
        // intentionally not public documentation.
        foreach (glob(base_path('*.md')) ?: [] as $file) {
            if (basename($file) === 'README.md') {
                continue;
            }

            $contents = file_get_contents($file) ?: '';
            $slug = Str::slug(pathinfo($file, PATHINFO_FILENAME));
            $documents['project-'.$slug] = [
                'file' => basename($file),
                'title' => preg_match('/^#\s+(.+)$/m', $contents, $match)
                    ? trim($match[1])
                    : Str::headline($slug),
                'category' => 'Dokumen proyek',
            ];
        }

        foreach ($files as $file) {
            $relative = ltrim(str_replace('\\', '/', str_replace($root, '', $file)), '/');
            $key = preg_replace('/\.md$/i', '', $relative);
            $contents = file_get_contents($file) ?: '';
            $title = preg_match('/^#\s+(.+)$/m', $contents, $match)
                ? trim($match[1])
                : str($key)->headline()->toString();

            $documents[$key] = [
                'file' => 'docs/'.$relative,
                'title' => $title,
                'category' => str_contains($key, '/')
                    ? Str::headline(Str::before($key, '/'))
                    : 'Mulai di sini',
            ];
        }

        // The mobile app and AI Core live beside this backend repository.
        // Include their Markdown guides in the same public documentation
        // portal, without copying or moving the source files.
        foreach ([
            'mobile-app' => base_path('../mobile_app'),
            'ai-core' => base_path('../ai_core'),
        ] as $category => $externalRoot) {
            if (! is_dir($externalRoot)) {
                continue;
            }

            $externalDirectory = new \RecursiveDirectoryIterator($externalRoot, \FilesystemIterator::SKIP_DOTS);
            $externalFilter = new \RecursiveCallbackFilterIterator(
                $externalDirectory,
                static function (\SplFileInfo $current): bool {
                    if (! $current->isDir()) {
                        return true;
                    }

                    return ! in_array($current->getFilename(), [
                        '.agents', '.claude', '.dart_tool', '.git', '.idea',
                        'build', 'node_modules', 'venv', 'dataconnect_generated',
                    ], true);
                },
            );
            $externalFiles = new \RecursiveIteratorIterator($externalFilter);

            foreach ($externalFiles as $file) {
                if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                    continue;
                }

                $relative = ltrim(str_replace('\\', '/', str_replace($externalRoot, '', $file->getPathname())), '/');
                $key = $category.'/'.preg_replace('/\.md$/i', '', $relative);
                $contents = file_get_contents($file->getPathname()) ?: '';
                $documents[$key] = [
                    'file' => '../'.basename($externalRoot).'/'.$relative,
                    'title' => preg_match('/^#\s+(.+)$/m', $contents, $match)
                        ? trim($match[1])
                        : Str::headline(pathinfo($relative, PATHINFO_FILENAME)),
                    'category' => Str::headline($category),
                ];
            }
        }

        return $documents;
    }

    private function renderDocument(string $key, array $documents): string
    {
        $file = base_path($documents[$key]['file']);
        $markdown = file_get_contents($file) ?: '';

        // Optional code-card metadata:
        // ```php title="app/Example.php"
        // The marker is removed by the documentation page JavaScript.
        $markdown = preg_replace_callback(
            '/^(```+)([^\r\n]*)$/m',
            static function (array $match): string {
                if (! preg_match('/\btitle=(?:"([^"]+)"|\'([^\']+)\')/i', $match[2], $title)) {
                    return $match[0];
                }

                return $match[1].preg_replace('/\s+title=(?:"[^"]+"|\'[^\']+\')/i', '', $match[2])
                    ."\n__DOC_CODE_TITLE__:".($title[1] ?? $title[2]);
            },
            $markdown,
        );

        // Keep internal Markdown links inside the documentation web.
        $markdown = preg_replace_callback(
            '/\]\((?!https?:\/\/|mailto:|#)([^)#]+\.md)(#[^)]*)?\)/i',
            function (array $match) use ($key, $documents): string {
                $target = trim($match[1], "./");
                // README.md lives at the repository root and links to files
                // using their on-disk `docs/...` paths. Portal keys already
                // start inside docs/, so remove that prefix before resolving.
                $target = Str::startsWith($target, 'docs/')
                    ? Str::after($target, 'docs/')
                    : $target;
                $candidate = str_contains($target, '/')
                    ? preg_replace('/\.md$/i', '', $target)
                    : preg_replace('/\.md$/i', '', str($key)->beforeLast('/')->append('/'.$target)->toString());

                if (! isset($documents[$candidate])) {
                    return $match[0];
                }

                return ']('.route('docs.show', ['path' => $candidate]).($match[2] ?? '').')';
            },
            $markdown,
        );

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = $converter->convert($markdown)->getContent();
        $usedHeadingIds = [];

        // CommonMark renders heading text but does not add anchor IDs by
        // default. Add stable slugs so README tables of contents such as
        // `#fitur` and `#konfigurasi` work inside the public portal.
        return preg_replace_callback(
            '/<h([1-6])([^>]*)>(.*?)<\/h\1>/is',
            static function (array $match) use (&$usedHeadingIds): string {
                if (preg_match('/\sid=["\']/', $match[2])) {
                    return $match[0];
                }

                $text = html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $id = Str::slug($text) ?: 'section';
                $base = $id;
                $suffix = 2;

                while (isset($usedHeadingIds[$id])) {
                    $id = $base.'-'.$suffix++;
                }

                $usedHeadingIds[$id] = true;

                return '<h'.$match[1].$match[2].' id="'.$id.'">'.$match[3].'</h'.$match[1].'>';
            },
            $html,
        ) ?? $html;
    }
}
