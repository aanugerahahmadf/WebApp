<?php

namespace App\Models\DeveloperStudio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;

class DeveloperStudioEndpoint extends Model
{
    protected $table = 'developer_studio_endpoints';

    protected $fillable = [
        'type',
        'method',
        'uri',
        'name',
        'action',
        'controller_class',
        'middleware',
        'domain',
        'file_path',
        'description',
        'status',
    ];

    protected $casts = [
        'middleware' => 'array',
    ];

    /**
     * Sinkronkan semua data: Routes, Controllers, GraphQL, gRPC, Capacitor, Flutter.
     */
    public static function syncAll(): int
    {
        $count = 0;

        // 1. Sync Laravel Routes (API & Web)
        $routes = Route::getRoutes()->getRoutes();
        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = array_filter($route->methods(), fn ($m) => $m !== 'HEAD');
            $methodStr = implode('|', $methods);
            $action = $route->getActionName();
            $name = $route->getName();
            $middleware = $route->middleware();
            $domain = $route->domain();

            $controllerClass = null;
            $filePath = null;

            if (is_string($action) && str_contains($action, '@')) {
                [$controllerClass] = explode('@', $action);
                if (class_exists($controllerClass)) {
                    try {
                        $reflector = new ReflectionClass($controllerClass);
                        $filePath = $reflector->getFileName();
                    } catch (\Throwable) {
                        // ignore reflection error
                    }
                }
            }

            $type = 'web';
            if (str_starts_with($uri, 'api')) {
                $type = 'api';
            } elseif (str_starts_with($uri, 'graphql')) {
                $type = 'graphql';
            } elseif (str_starts_with($uri, 'admin')) {
                $type = 'admin';
            }

            self::updateOrCreate(
                [
                    'uri' => $uri,
                    'method' => $methodStr,
                ],
                [
                    'type' => $type,
                    'name' => $name,
                    'action' => is_string($action) ? $action : 'Closure',
                    'controller_class' => $controllerClass,
                    'middleware' => $middleware,
                    'domain' => $domain,
                    'file_path' => $filePath,
                    'status' => 'active',
                ]
            );
            $count++;
        }

        // 2. Sync Controllers
        $controllersPath = app_path('Http/Controllers');
        if (File::isDirectory($controllersPath)) {
            $files = File::allFiles($controllersPath);
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = File::get($file->getRealPath());
                if (! preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) ||
                    ! preg_match('/(?:class|trait|interface)\s+([A-Za-z0-9_]+)/', $content, $classMatch)) {
                    continue;
                }

                $className = trim($nsMatch[1]) . '\\' . trim($classMatch[1]);

                if (class_exists($className)) {
                    try {
                        $reflector = new ReflectionClass($className);
                        if (! $reflector->isAbstract()) {
                            $publicMethods = array_map(
                                fn (ReflectionMethod $m) => $m->getName(),
                                $reflector->getMethods(ReflectionMethod::IS_PUBLIC)
                            );
                            $publicMethods = array_filter(
                                $publicMethods,
                                function ($name) use ($reflector, $className) {
                                    if (str_starts_with($name, '__')) {
                                        return false;
                                    }
                                    try {
                                        $method = $reflector->getMethod($name);
                                        return $method->getDeclaringClass()->getName() === $className;
                                    } catch (\Throwable) {
                                        return false;
                                    }
                                }
                            );

                            self::updateOrCreate(
                                [
                                    'uri' => 'controller:' . $reflector->getShortName(),
                                    'method' => 'CONTROLLER',
                                ],
                                [
                                    'type' => 'controller',
                                    'name' => $reflector->getShortName(),
                                    'action' => implode(', ', $publicMethods),
                                    'controller_class' => $className,
                                    'file_path' => $file->getRealPath(),
                                    'description' => 'Controller dengan ' . count($publicMethods) . ' method',
                                    'status' => 'active',
                                ]
                            );
                            $count++;
                        }
                    } catch (\Throwable) {
                        // ignore reflection errors
                    }
                }
            }
        }

        // 3. Sync GraphQL Schema & Resolvers
        $graphqlPath = base_path('graphql');
        if (File::isDirectory($graphqlPath)) {
            $gqlFiles = File::allFiles($graphqlPath);
            foreach ($gqlFiles as $file) {
                $name = $file->getFilename();
                $dir = basename($file->getPath());
                self::updateOrCreate(
                    [
                        'uri' => 'graphql:' . $dir . '/' . $name,
                        'method' => strtoupper($dir),
                    ],
                    [
                        'type' => 'graphql',
                        'name' => $name,
                        'action' => 'GraphQL Schema: ' . $dir,
                        'file_path' => $file->getRealPath(),
                        'description' => 'Definisi GraphQL ' . $dir,
                        'status' => 'active',
                    ]
                );
                $count++;
            }
        }

        // 4. Sync gRPC Proto & Services
        $protoPath = base_path('proto');
        if (File::isDirectory($protoPath)) {
            $protoFiles = File::allFiles($protoPath);
            foreach ($protoFiles as $file) {
                if ($file->getExtension() === 'proto') {
                    self::updateOrCreate(
                        [
                            'uri' => 'grpc:' . $file->getFilename(),
                            'method' => 'RPC',
                        ],
                        [
                            'type' => 'grpc',
                            'name' => $file->getFilename(),
                            'action' => 'Protobuf Service Definition',
                            'file_path' => $file->getRealPath(),
                            'description' => 'Definisi gRPC Protocol Buffers',
                            'status' => 'active',
                        ]
                    );
                    $count++;
                }
            }
        }

        // 5. Sync Capacitor
        $capacitorAppPath = app_path('Capacitor');
        if (File::isDirectory($capacitorAppPath)) {
            $capApps = ['AdminApp', 'UserApp'];
            foreach ($capApps as $capApp) {
                $conf = app_path("Capacitor/{$capApp}/capacitor.config.json");
                if (File::exists($conf)) {
                    $json = json_decode(File::get($conf), true) ?: [];
                    self::updateOrCreate(
                        [
                            'uri' => 'capacitor:' . strtolower($capApp),
                            'method' => 'CAPACITOR',
                        ],
                        [
                            'type' => 'capacitor',
                            'name' => $capApp,
                            'action' => $json['appId'] ?? 'com.wedding.' . strtolower($capApp),
                            'file_path' => $conf,
                            'description' => 'Capacitor Hybrid Mobile Bridge (' . ($json['appName'] ?? $capApp) . ')',
                            'status' => 'active',
                        ]
                    );
                    $count++;
                }
            }
        }

        // 6. Sync Flutter
        $flutterPath = base_path('../mobile_app');
        if (File::isDirectory($flutterPath)) {
            $pubspec = $flutterPath . '/pubspec.yaml';
            self::updateOrCreate(
                [
                    'uri' => 'flutter:mobile_app',
                    'method' => 'FLUTTER',
                ],
                [
                    'type' => 'flutter',
                    'name' => 'Wedding Flowers Mobile App',
                    'action' => 'Flutter Multiplatform (Android & iOS)',
                    'file_path' => File::exists($pubspec) ? $pubspec : $flutterPath,
                    'description' => 'Aplikasi Mobile Flutter terintegrasi dengan REST API, gRPC, dan WebSocket',
                    'status' => 'active',
                ]
            );
            $count++;
        }

        // 7. Sync Python / AI Core
        $aiCorePath = base_path('../ai_core');
        if (File::isDirectory($aiCorePath)) {
            $appPy = $aiCorePath . '/app.py';
            if (File::exists($appPy)) {
                $appContent = File::get($appPy);
                // Regex match Flask route definitions: @app.route('/path', methods=['GET', 'POST'])
                if (preg_match_all("/@app\.route\(\s*['\"]([^'\"]+)['\"](?:.*?methods\s*=\s*\[([^\]]+)\])?/s", $appContent, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $routeUri = $match[1];
                        $rawMethods = isset($match[2]) ? str_replace(["'", '"', ' '], '', $match[2]) : 'GET';
                        $routeMethod = str_replace(',', '|', strtoupper($rawMethods));

                        self::updateOrCreate(
                            [
                                'uri' => 'ai_core:' . ltrim($routeUri, '/'),
                                'method' => $routeMethod ?: 'GET',
                            ],
                            [
                                'type' => 'python',
                                'name' => 'AI Core: ' . $routeUri,
                                'action' => 'Flask CBIR & AI Microservice (Port 5000)',
                                'file_path' => $appPy,
                                'description' => 'Endpoint Microservice Python Flask AI Core untuk pencarian gambar visual (CBIR), ekstraksi fitur, dan verifikasi wajah/dokumen.',
                                'status' => 'active',
                            ]
                        );
                        $count++;
                    }
                }
            }

            // Also check ai_sync.py and grpc/server.py
            $syncPy = $aiCorePath . '/ai_sync.py';
            if (File::exists($syncPy)) {
                self::updateOrCreate(
                    [
                        'uri' => 'ai_core:ai_sync.py',
                        'method' => 'PYTHON_SCRIPT',
                    ],
                    [
                        'type' => 'python',
                        'name' => 'AI Feature Extractor & CSV Sync',
                        'action' => 'Python Worker (ai_sync.py)',
                        'file_path' => $syncPy,
                        'description' => 'Skrip sinkronisasi ekstraksi fitur gambar ke dataset.csv untuk model CBIR.',
                        'status' => 'active',
                    ]
                );
                $count++;
            }

            $grpcPy = $aiCorePath . '/grpc/server.py';
            if (File::exists($grpcPy)) {
                self::updateOrCreate(
                    [
                        'uri' => 'ai_core:grpc/server.py',
                        'method' => 'PYTHON_GRPC',
                    ],
                    [
                        'type' => 'python',
                        'name' => 'AI Core gRPC Python Server',
                        'action' => 'Python gRPC Server (server.py)',
                        'file_path' => $grpcPy,
                        'description' => 'Server gRPC internal Python untuk inferensi AI berkecepatan tinggi.',
                        'status' => 'active',
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    public function getPythonSnippet(): string
    {
        $cleanUri = ltrim(str_replace('ai_core:', '', $this->uri), '/');

        return <<<PYTHON
import requests

AI_CORE_URL = "http://127.0.0.1:5000/{$cleanUri}"

def call_ai_core():
    try:
        response = requests.get(AI_CORE_URL, timeout=5)
        if response.status_code == 200:
            print("Response:", response.json())
            return response.json()
        else:
            print("Error:", response.status_code, response.text)
    except Exception as e:
        print("Connection failed:", e)

if __name__ == "__main__":
    call_ai_core()
PYTHON;
    }

    public function getFileContent(): string
    {
        if ($this->file_path && File::exists($this->file_path)) {
            return File::get($this->file_path);
        }

        return __('Berkas fisik tidak ditemukan di server.');
    }

    public function getFileSize(): string
    {
        if (! $this->file_path || ! File::exists($this->file_path)) {
            return '-';
        }

        $bytes = File::size($this->file_path);
        return number_format($bytes / 1024, 2) . ' KB';
    }

    public function getCurlCommand(): string
    {
        /*
         * JANGAN pakai config('app.url') di sini: nilainya bisa menunjuk
         * domain tunnel yang sudah mati (mis. *.trycloudflare.com), sehingga
         * contoh cURL di halaman detail menunjuk host yang tidak melayani
         * apa pun. `url('/')` memakai origin request yang sedang berjalan
         * (127.0.0.1 saat dibuka lokal, domain tunnel saat diakses dari luar).
         */
        $appUrl = rtrim(url('/'), '/');
        $methods = explode('|', $this->method ?? 'GET');
        $httpMethod = in_array('GET', $methods) ? 'GET' : ($methods[0] ?? 'GET');
        $cleanUri = ltrim($this->uri, '/');

        if (str_starts_with($cleanUri, 'http')) {
            $url = $cleanUri;
        } else {
            $url = "{$appUrl}/{$cleanUri}";
        }

        $cmd = "curl -X {$httpMethod} \"{$url}\" \\\n  -H \"Accept: application/json\" \\\n  -H \"Content-Type: application/json\"";

        if ($this->middleware && in_array('auth:sanctum', (array) $this->middleware)) {
            $cmd .= " \\\n  -H \"Authorization: Bearer YOUR_API_TOKEN\"";
        }

        if (in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            $cmd .= " \\\n  -d '{\"example\": \"value\"}'";
        }

        return $cmd;
    }

    public function getFlutterSnippet(): string
    {
        $cleanUri = ltrim($this->uri, '/');
        $methods = explode('|', $this->method ?? 'GET');
        $httpMethod = in_array('GET', $methods) ? 'get' : strtolower($methods[0] ?? 'get');

        return <<<DART
import 'package:http/http.dart' as http;
import 'dart:convert';

Future<dynamic> callEndpoint() async {
  final url = Uri.parse('\$baseUrl/{$cleanUri}');
  final response = await http.{$httpMethod}(
    url,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer \$authToken',
    },
  );

  if (response.statusCode == 200) {
    return jsonDecode(response.body);
  } else {
    throw Exception('Failed request: \${response.statusCode}');
  }
}
DART;
    }

    public function getCapacitorSnippet(): string
    {
        $cleanUri = ltrim($this->uri, '/');
        $methods = explode('|', $this->method ?? 'GET');
        $httpMethod = in_array('GET', $methods) ? 'GET' : ($methods[0] ?? 'GET');

        $capCall = $httpMethod === 'GET' ? 'get' : 'post';

        return <<<TYPESCRIPT
import { CapacitorHttp, HttpResponse } from '@capacitor/core';

export async function requestEndpoint(): Promise<any> {
  const options = {
    url: `\${baseUrl}/{$cleanUri}`,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': `Bearer \${authToken}`,
    },
  };

  const response: HttpResponse = await CapacitorHttp.{$capCall}(options);
  return response.data;
}
TYPESCRIPT;
    }
}
