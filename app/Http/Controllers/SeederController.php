<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
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
        ]);

        $count = (int) $data['count'];
        $fresh = (bool) ($data['fresh'] ?? false);

        $chunk = 500;

        $args = ['php', 'artisan', 'benchmark:seed', (string) $count, "--chunk={$chunk}"];
        if ($fresh) {
            $args[] = '--fresh';
        }

        $this->startBackgroundProcess($args);

        return response()->json([
            'started' => true,
            'count' => $count,
            'fresh' => $fresh,
        ]);
    }

    public function status()
    {
        $status = Cache::store('file')->get('benchmark:seed:status');

        return response()->json([
            'status' => $status,
            'counts' => $this->counts(),
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
            $escaped = array_map(static fn (string $a) => str_contains($a, ' ') ? "\"{$a}\"" : $a, $args);
            $cmd = 'cmd /c start /B '.implode(' ', $escaped);
            Process::fromShellCommandline($cmd, base_path())->start();
            return;
        }

        $process = new Process($args, base_path());
        $process->disableOutput();
        $process->start();
    }
}
