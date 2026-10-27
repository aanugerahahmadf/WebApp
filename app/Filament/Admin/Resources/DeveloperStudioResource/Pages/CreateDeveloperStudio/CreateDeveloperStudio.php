<?php

namespace App\Filament\Admin\Resources\DeveloperStudioResource\Pages\CreateDeveloperStudio;

use App\Filament\Admin\Concerns\HandlesFileUpload\HandlesFileUpload;
use App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource;
use App\Models\DeveloperStudio\DeveloperStudioEndpoint;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Halaman "Buat Modul" Studio API.
 *
 * Modul yang bisa dibuat:
 *
 *   - `api_controller` -> controller API di `app/Http/Controllers/Api/{scope}/`
 *   - `controller`     -> controller web di `app/Http/Controllers/`
 *   - `route`          -> definisi route + baris di tabel endpoint
 *   - `graphql`        -> berkas schema `.graphql`
 *   - `grpc`           -> berkas `.proto`
 *   - `capacitor`      -> plugin TypeScript untuk aplikasi native
 *   - `flutter`        -> service Dart untuk aplikasi mobile
 *   - `python`         -> modul Python untuk AI Core
 *
 * Tiap modul bisa dibuat dari templat (`generate`) atau diunggah apa adanya
 * (`upload`).
 */
class CreateDeveloperStudio extends CreateRecord
{
    use HandlesFileUpload;

    protected static string $resource = DeveloperStudioResource::class;

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Select::make('module_type')
                ->searchable()
                ->label(__('Jenis Modul'))
                ->options([
                    'api_controller' => __('API Controller'),
                    'controller' => __('Web Controller'),
                    'route' => __('Route'),
                    'graphql' => __('GraphQL Schema'),
                    'grpc' => __('gRPC Proto'),
                    'capacitor' => __('Capacitor Plugin'),
                    'flutter' => __('Flutter Service'),
                    'python' => __('Python Module (AI Core)'),
                ])
                ->default('api_controller')
                ->required()
                ->live(),

            Select::make('mode')
                ->searchable()
                ->label(__('Cara'))
                ->options([
                    'generate' => __('Buat dari templat'),
                    'upload' => __('Unggah berkas'),
                ])
                ->default('generate')
                ->required()
                ->live(),

            // ------------------------------------------------ API CONTROLLER --
            TextInput::make('api_name')
                ->label(__('Nama Controller'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'api_controller'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'api_controller')),

            TextInput::make('api_scope')
                ->label(__('Scope'))
                ->default('User')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'api_controller'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'api_controller')),

            Select::make('api_methods')
                ->searchable()
                ->label(__('Metode'))
                ->options([
                    'all' => __('Semua (CRUD)'),
                    'index' => __('index'),
                    'show' => __('show'),
                    'store' => __('store'),
                    'update' => __('update'),
                    'destroy' => __('destroy'),
                ])
                ->default('all')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'api_controller'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'api_controller')),

            // ------------------------------------------------- WEB CONTROLLER --
            TextInput::make('web_controller_name')
                ->label(__('Nama Controller'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'controller'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'controller')),

            Select::make('controller_type')
                ->searchable()
                ->label(__('Tipe'))
                ->options([
                    'resource' => __('Resource'),
                    'controller' => __('Controller biasa'),
                    'invokable' => __('Invokable'),
                ])
                ->default('resource')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'controller'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'controller')),

            // ------------------------------------------------------------ ROUTE --
            Select::make('http_method')
                ->searchable()
                ->label(__('Metode HTTP'))
                ->options([
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'PATCH' => 'PATCH',
                    'DELETE' => 'DELETE',
                ])
                ->default('GET')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'route'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'route')),

            TextInput::make('route_uri')
                ->label(__('URI'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'route'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'route')),

            TextInput::make('target_route_file')
                ->label(__('Berkas Route'))
                ->default('routes/api.php')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'route'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'route')),

            TextInput::make('route_action')
                ->label(__('Action'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'route'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'route')),

            TextInput::make('route_name')
                ->label(__('Nama Route'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'route'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'route')),

            // --------------------------------------------------------- GRAPHQL --
            TextInput::make('graphql_name')
                ->label(__('Nama Schema'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'graphql'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'graphql')),

            Select::make('schema_type')
                ->searchable()
                ->label(__('Tipe'))
                ->options([
                    'type' => 'type',
                    'query' => 'query',
                    'mutation' => 'mutation',
                    'input' => 'input',
                    'enum' => 'enum',
                ])
                ->default('type')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'graphql'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'graphql')),

            Textarea::make('graphql_code')
                ->label(__('Kode'))
                ->rows(5)
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'graphql')),

            // ------------------------------------------------------------ GRPC --
            TextInput::make('grpc_proto_name')
                ->label(__('Nama Proto'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'grpc'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'grpc')),

            TextInput::make('package_name')
                ->label(__('Package'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'grpc'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'grpc')),

            Textarea::make('proto_code')
                ->label(__('Kode'))
                ->rows(5)
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'grpc')),

            // -------------------------------------------------------- CAPACITOR --
            TextInput::make('capacitor_plugin_name')
                ->label(__('Nama Plugin'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'capacitor'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'capacitor')),

            Select::make('target_app')
                ->searchable()
                ->label(__('Aplikasi'))
                ->options([
                    'UserApp' => 'UserApp',
                    'AdminApp' => 'AdminApp',
                ])
                ->default('UserApp')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'capacitor'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'capacitor')),

            Textarea::make('capacitor_code')
                ->label(__('Kode'))
                ->rows(5)
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'capacitor')),

            // ---------------------------------------------------------- FLUTTER --
            TextInput::make('flutter_filename')
                ->label(__('Nama Berkas'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'flutter'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'flutter')),

            Textarea::make('flutter_code')
                ->label(__('Kode'))
                ->rows(5)
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'flutter')),

            // ---------------------------------------------------------- PYTHON --
            TextInput::make('python_filename')
                ->label(__('Nama Berkas'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'python'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'python')),

            Textarea::make('python_code')
                ->label(__('Kode'))
                ->rows(5)
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'python')),

            // ---------------------------------------------------------- UPLOAD --
            TextInput::make('upload_target_dir')
                ->label(__('Folder Tujuan'))
                ->default('app/Http/Controllers')
                ->required(fn (Get $get): bool => $get('mode') === 'upload')
                ->visible(fn (Get $get): bool => $get('mode') === 'upload'),

            FileUpload::make('uploaded_file')
                ->label(__('Berkas'))
                ->disk('local')
                // Tanpa ini, nama berkasnya jadi hash Livewire dan nama
                // berkas yang ditulis ke disk tidak lagi sama dengan yang
                // diunggah.
                ->preserveFilenames()
                // Tanpa pembatasan `acceptedFileTypes`: mime type ditebak dari
                // isi berkas, jadi berkas `.php` bisa terdeteksi sebagai
                // `text/plain`. Membatasi jenis berkas akan menolak berkas
                // yang isinya benar.
                ->required(fn (Get $get): bool => $get('mode') === 'upload')
                ->visible(fn (Get $get): bool => $get('mode') === 'upload')
                ->maxSize(4096),
        ]);
    }

    /**
     * Buat modnya.
     *
     * Method ini menggantikan `CreateRecord::create()`: tidak semua modul
     * menghasilkan baris tabel (hanya `route` yang begitu), dan barisnya
     * tidak selalu fulfilling aturan model yang sama.
     */
    public function create(bool $another = false): void
    {
        // Code generation changes executable application files. It must never
        // be enabled on a production server by accident; use a reviewed CI/CD
        // deployment instead.
        if (app()->environment('production') && ! config('developer_studio.allow_code_generation', false)) {
            throw ValidationException::withMessages([
                'mode' => __('Pembuatan kode dinonaktifkan pada server produksi.'),
            ]);
        }

        $this->callHook('beforeValidate');

        $data = $this->form->getState();

        $this->callHook('beforeCreate');
        $this->callHook('afterValidate');

        $path = $this->writeModule($data);

        $this->callHook('afterCreate');

        Notification::make()
            ->success()
            ->title(__('Modul Berhasil Dibuat'))
            ->body(__('Berkas: :path', ['path' => $path]))
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function writeModule(array $data): string
    {
        $moduleType = (string) ($data['module_type'] ?? 'api_controller');
        $mode = (string) ($data['mode'] ?? 'generate');

        if ($mode === 'upload') {
            return $this->storeUploadedFile($data);
        }

        return match ($moduleType) {
            'controller' => $this->generateWebController($data),
            'route' => $this->generateRoute($data),
            'graphql' => $this->generateGraphql($data),
            'grpc' => $this->generateGrpc($data),
            'capacitor' => $this->generateCapacitor($data),
            'flutter' => $this->generateFlutter($data),
            'python' => $this->generatePython($data),
            default => $this->generateApiController($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateApiController(array $data): string
    {
        $name = $this->safeIdentifier((string) ($data['api_name'] ?? 'ApiController'), 'api_name');
        $scope = (string) ($data['api_scope'] ?? 'User');
        if (! in_array($scope, ['User', 'Admin'], true)) {
            throw ValidationException::withMessages(['api_scope' => __('Scope controller tidak valid.')]);
        }
        $methods = (string) ($data['api_methods'] ?? 'all');

        $directory = app_path("Http/Controllers/Api/{$scope}/{$name}");

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$name.'.php';

        File::put($path, $this->apiControllerTemplate($name, $scope, $methods));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateWebController(array $data): string
    {
        $name = $this->safeIdentifier((string) ($data['web_controller_name'] ?? 'Controller'), 'web_controller_name');
        $type = (string) ($data['controller_type'] ?? 'controller');

        $directory = app_path("Http/Controllers/{$name}");

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$name.'.php';

        File::put($path, $this->webControllerTemplate($name, $type));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateRoute(array $data): string
    {
        $method = strtoupper((string) ($data['http_method'] ?? 'GET'));
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw ValidationException::withMessages(['http_method' => __('Metode route tidak valid.')]);
        }
        $uri = (string) ($data['route_uri'] ?? 'api/sample');
        $targetFile = (string) ($data['target_route_file'] ?? 'routes/api.php');
        $action = (string) ($data['route_action'] ?? '');
        $name = (string) ($data['route_name'] ?? '');

        if (! preg_match('/\A[A-Za-z0-9_\/.{}?\-]+\z/', $uri)
            || ! preg_match('/\A[A-Za-z0-9_\\@:\-]+\z/', $action)
            || ($name !== '' && ! preg_match('/\A[A-Za-z0-9_.\-]+\z/', $name))) {
            throw ValidationException::withMessages(['route_uri' => __('Definisi route tidak valid.')]);
        }

        $targetFile = ltrim(str_replace('\\', '/', $targetFile), '/');
        if (str_contains($targetFile, '..') || ! preg_match('/\Aroutes\/[A-Za-z0-9_\/-]+\.php\z/', $targetFile)) {
            throw ValidationException::withMessages(['target_route_file' => __('Berkas route tidak diizinkan.')]);
        }

        $path = base_path($targetFile);

        File::ensureDirectoryExists(dirname($path));

        $line = "Route::{$method}('{$uri}', '{$action}')";

        if ($name !== '') {
            $line .= "->name('{$name}')";
        }

        $line .= ';';

        $existing = File::exists($path) ? (string) File::get($path) : '';

        if (! str_contains($existing, $uri)) {
            File::put($path, rtrim($existing, "\n")."\n".$line."\n");
        }

        DeveloperStudioEndpoint::updateOrCreate(
            ['uri' => $uri, 'method' => $method],
            [
                'type' => 'api',
                'name' => $name !== '' ? $name : $uri,
                'action' => $action,
                'status' => 'active',
            ]
        );

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateGraphql(array $data): string
    {
        $name = $this->safeIdentifier((string) ($data['graphql_name'] ?? 'schema'), 'graphql_name');
        $type = (string) ($data['schema_type'] ?? 'type');
        $code = (string) ($data['graphql_code'] ?? '');

        $directory = base_path("graphql/types/{$name}");

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$name.'.graphql';

        $contents = trim($code) !== ''
            ? $code
            : "{$type} {$name} {\n  id: ID!\n}\n";

        File::put($path, $contents);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateGrpc(array $data): string
    {
        $name = $this->safeFilename((string) ($data['grpc_proto_name'] ?? 'service.proto'), 'grpc_proto_name', ['proto']);
        $package = (string) ($data['package_name'] ?? 'wedding.app.v1');
        if (! preg_match('/\A[A-Za-z][A-Za-z0-9_.]*\z/', $package)) {
            throw ValidationException::withMessages(['package_name' => __('Nama package tidak valid.')]);
        }
        $code = (string) ($data['proto_code'] ?? '');

        $directory = base_path('proto');

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$name;

        $contents = trim($code) !== ''
            ? $code
            : "syntax = \"proto3\";\n\npackage {$package};\n\nservice {$name} {\n}\n";

        File::put($path, $contents);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateCapacitor(array $data): string
    {
        $name = $this->safeFilename((string) ($data['capacitor_plugin_name'] ?? 'Plugin.ts'), 'capacitor_plugin_name', ['ts']);
        $app = (string) ($data['target_app'] ?? 'UserApp');
        if (! in_array($app, ['UserApp', 'AdminApp'], true)) {
            throw ValidationException::withMessages(['target_app' => __('Target aplikasi tidak valid.')]);
        }
        $code = (string) ($data['capacitor_code'] ?? '');

        $directory = app_path("Capacitor/{$app}/src/js");

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$name;

        $contents = trim($code) !== ''
            ? $code
            : "export const {$name} = {\n  // TODO: isi plugin\n};\n";

        File::put($path, $contents);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateFlutter(array $data): string
    {
        $name = $this->safeFilename((string) ($data['flutter_filename'] ?? 'service.dart'), 'flutter_filename', ['dart']);
        $code = (string) ($data['flutter_code'] ?? '');

        $directory = base_path('../mobile_app/lib/services');

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$name;

        $contents = trim($code) !== ''
            ? $code
            : "// TODO: isi service\n";

        File::put($path, $contents);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generatePython(array $data): string
    {
        $name = $this->safeFilename((string) ($data['python_filename'] ?? 'module.py'), 'python_filename', ['py']);
        $code = (string) ($data['python_code'] ?? '');

        $directory = base_path('../ai_core/src/custom');

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$name;

        $contents = trim($code) !== ''
            ? $code
            : "\"\"\"TODO: isi modul ini.\"\"\"\n";

        File::put($path, $contents);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeUploadedFile(array $data): string
    {
        $uploaded = $this->resolveUploadedFile($data);

        $relativeDirectory = ltrim(str_replace('\\', '/', (string) ($data['upload_target_dir'] ?? '')), '/');
        $allowedRoots = [
            'app/Http/Controllers', 'app/Grpc', 'graphql', 'proto',
            'app/Capacitor', '../mobile_app/lib', '../ai_core/src',
        ];
        $allowed = false;
        foreach ($allowedRoots as $root) {
            if ($relativeDirectory === $root || str_starts_with($relativeDirectory, $root.'/')) {
                $allowed = true;
                break;
            }
        }
        $filename = basename($uploaded['name']);
        if (! $allowed || str_contains($relativeDirectory, '..')
            || $filename !== $uploaded['name']
            || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $filename)) {
            throw ValidationException::withMessages(['upload_target_dir' => __('Lokasi atau nama berkas tidak diizinkan.')]);
        }

        $directory = base_path($relativeDirectory);

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$uploaded['name'];

        File::put($path, $uploaded['contents']);

        return $path;
    }

    protected function isGenerating(Get $get, string $moduleType): bool
    {
        return $get('module_type') === $moduleType && $get('mode') === 'generate';
    }

    protected function safeIdentifier(string $value, string $field): string
    {
        if (! preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $value)) {
            throw ValidationException::withMessages([$field => __('Nama modul tidak valid.')]);
        }

        return $value;
    }

    /** @param list<string> $extensions */
    protected function safeFilename(string $value, string $field, array $extensions): string
    {
        if (basename($value) !== $value || ! preg_match('/\A[A-Za-z][A-Za-z0-9_-]*\.([A-Za-z0-9]+)\z/', $value)) {
            throw ValidationException::withMessages([$field => __('Nama berkas tidak valid.')]);
        }

        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        if (! in_array($extension, $extensions, true)) {
            throw ValidationException::withMessages([$field => __('Ekstensi berkas tidak diizinkan.')]);
        }

        return $value;
    }

    protected function apiControllerTemplate(string $name, string $scope, string $methods): string
    {
        $body = [];

        if ($methods === 'all' || $methods === 'index') {
            $body[] = <<<'PHP'
    public function index(Request $request)
    {
        return response()->json(['data' => []]);
    }
PHP;
        }

        if ($methods === 'all' || $methods === 'show') {
            $body[] = <<<'PHP'
    public function show(int|string $id)
    {
        return response()->json(['data' => ['id' => $id]]);
    }
PHP;
        }

        if ($methods === 'all' || $methods === 'store') {
            $body[] = <<<'PHP'
    public function store(Request $request)
    {
        return response()->json(['data' => $request->all()], 201);
    }
PHP;
        }

        if ($methods === 'all' || $methods === 'update') {
            $body[] = <<<'PHP'
    public function update(Request $request, int|string $id)
    {
        return response()->json(['data' => $request->all()]);
    }
PHP;
        }

        if ($methods === 'all' || $methods === 'destroy') {
            $body[] = <<<'PHP'
    public function destroy(int|string $id)
    {
        return response()->json(['deleted' => $id]);
    }
PHP;
        }

        return <<<PHP
        <?php

        namespace App\Http\Controllers\Api\\{$scope};

        use App\Http\Controllers\Controller;
        use Illuminate\Http\Request;

        class {$name} extends Controller
        {
        {$this->indent(implode("\n\n", $body))}
        }

        PHP;
    }

    protected function webControllerTemplate(string $name, string $type): string
    {
        return <<<PHP
        <?php

        namespace App\Http\Controllers;

        use Illuminate\Http\Request;

        class {$name} extends Controller
        {
            public function index(Request \$request)
            {
                return view('{$type}.index');
            }
        }

        PHP;
    }

    /**
     * Indentasi isi blok supaya rapi di dalam template.
     */
    protected function indent(string $body): string
    {
        $lines = explode("\n", $body);

        return implode("\n", array_map(
            fn (string $line): string => $line === '' ? '' : '    '.$line,
            $lines
        ));
    }

    /**
     * Judul halaman BUKAN "Create API & Developer Studio".
     *
     * `CreateRecord::getTitle()` membentuk "Create {plural model label}",
     * dan plural label resource ini "Studio API & Pengembang" -- hasilnya
     * kalimat yang tidak menjelaskan apa pun: halaman ini tidak membuat
     * "studio", ia membuat MODUL (controller, route, schema, plugin).
     */
    public function getTitle(): string
    {
        return __('Buat Modul');
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
