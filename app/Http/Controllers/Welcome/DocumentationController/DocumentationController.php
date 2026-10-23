<?php

namespace App\Http\Controllers\Welcome\DocumentationController;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        $selected = $request->query('doc', 'README');

        if (! isset($documents[$selected])) {
            $selected = 'README';
        }

        return view('Welcome.pages.documentation.documentation', [
            'documents' => $documents,
            'selected' => $selected,
            'document' => $this->renderDocument($selected, $documents),
        ]);
    }

    public function show(string $path): \Illuminate\Contracts\View\View
    {
        $documents = $this->documents();
        $key = trim($path, '/');

        if (! isset($documents[$key])) {
            throw new NotFoundHttpException;
        }

        return view('Welcome.pages.documentation.documentation', [
            'documents' => $documents,
            'selected' => $key,
            'document' => $this->renderDocument($key, $documents),
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

        foreach ($files as $file) {
            $relative = ltrim(str_replace('\\', '/', str_replace($root, '', $file)), '/');
            $key = preg_replace('/\.md$/i', '', $relative);
            $contents = file_get_contents($file) ?: '';
            $title = preg_match('/^#\s+(.+)$/m', $contents, $match)
                ? trim($match[1])
                : str($key)->headline()->toString();

            $documents[$key] = [
                'file' => $relative,
                'title' => $title,
                'category' => str_contains($key, '/')
                    ? str(str_before($key, '/'))->headline()->toString()
                    : 'Mulai di sini',
            ];
        }

        return $documents;
    }

    private function renderDocument(string $key, array $documents): string
    {
        $file = base_path('docs/'.$documents[$key]['file']);
        $markdown = file_get_contents($file) ?: '';

        // Keep internal Markdown links inside the documentation web.
        $markdown = preg_replace_callback(
            '/\]\((?!https?:\/\/|mailto:|#)([^)#]+\.md)(#[^)]*)?\)/i',
            function (array $match) use ($key, $documents): string {
                $target = trim($match[1], "./");
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

        return $converter->convert($markdown)->getContent();
    }
}
