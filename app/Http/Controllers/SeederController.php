<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SeederController extends Controller
{
    public function index()
    {
        return view('seeder.index', [
            'counts' => $this->counts(),
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:200000'],
            'fresh' => ['nullable', 'boolean'],
            'authors' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'categories' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'tags' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'comments_per_article' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'chunk' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $count = (int) $data['count'];
        $fresh = (bool) ($data['fresh'] ?? false);
        $chunk = (int) ($data['chunk'] ?? 500);

        $jobId = Str::uuid()->toString();
        $statusStore = Cache::store('file');
        $statusStore->put('benchmark:seed:last_job', $jobId, now()->addHours(6));
        $statusStore->put('benchmark:seed:status:'.$jobId, [
            'running' => true,
            'job' => $jobId,
            'started_at' => now()->toIso8601String(),
            'article_target' => $count,
            'article_created' => 0,
            'phase' => 'starting',
            'message' => 'Starting...',
        ], now()->addHours(6));

        $args = [
            PHP_BINARY,
            base_path('artisan'),
            'benchmark:seed',
            (string) $count,
            "--chunk={$chunk}",
            "--job={$jobId}",
        ];
        if ($fresh) {
            $args[] = '--fresh';
        }

        foreach (['authors' => 'authors', 'categories' => 'categories', 'tags' => 'tags'] as $key => $opt) {
            if (isset($data[$key])) {
                $args[] = "--{$opt}=".(int) $data[$key];
            }
        }

        if (isset($data['comments_per_article'])) {
            $args[] = '--comments-per-article='.(float) $data['comments_per_article'];
        }

        Log::info('Starting benchmark seeder', ['args' => $args, 'job' => $jobId]);

        $this->startBackgroundProcess($args);

        return response()->json([
            'started' => true,
            'job' => $jobId,
            'count' => $count,
            'fresh' => $fresh,
        ]);
    }

    public function status()
    {
        $job = request()->query('job') ?: Cache::store('file')->get('benchmark:seed:last_job');
        $status = $job ? Cache::store('file')->get('benchmark:seed:status:'.$job) : null;

        return response()->json([
            'job' => $job,
            'status' => $status,
            'counts' => $this->counts(),
        ]);
    }

    public function stream()
    {
        $job = request()->query('job') ?: Cache::store('file')->get('benchmark:seed:last_job');

        return response()->stream(function () use ($job) {
            if (! $job) {
                echo "event: server_error\n";
                echo 'data: '.json_encode(['message' => 'No seeding job found.'])."\n\n";
                @ob_flush();
                @flush();
                return;
            }

            while (true) {
                $status = Cache::store('file')->get('benchmark:seed:status:'.$job);
                $payload = [
                    'job' => $job,
                    'status' => $status,
                    'counts' => $this->counts(),
                ];

                echo "event: progress\n";
                echo 'data: '.json_encode($payload)."\n\n";
                @ob_flush();
                @flush();

                if (! is_array($status) || ($status['running'] ?? false) === false) {
                    break;
                }

                echo ": keepalive\n\n";
                @ob_flush();
                @flush();

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function counts(): array
    {
        if (! Schema::hasTable('articles')) {
            return [
                'articles' => 0,
                'authors' => 0,
                'categories' => 0,
                'tags' => 0,
                'comments' => 0,
            ];
        }

        return [
            'articles' => Article::count(),
            'authors' => Author::count(),
            'categories' => Category::count(),
            'tags' => Tag::count(),
            'comments' => \DB::table('comments')->count(),
        ];
    }

    /**
     * @param  list<string>  $args
     */
    private function startBackgroundProcess(array $args): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $escaped = array_map(static function (string $a) {
                $a = str_replace('"', '""', $a);
                return str_contains($a, ' ') ? "\"{$a}\"" : $a;
            }, $args);

            $cmd = 'cmd /c start "" /B '.implode(' ', $escaped);
            Log::info('Starting seeder (windows detached)', ['cmd' => $cmd]);
            Process::fromShellCommandline($cmd, base_path())->start();
            return;
        }

        $process = new Process($args, base_path());
        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();
    }
}
