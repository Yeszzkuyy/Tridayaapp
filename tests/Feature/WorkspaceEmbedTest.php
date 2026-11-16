<?php

namespace Tests\Feature;

use App\Http\Middleware\PropagateWorkspaceEmbed;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Penjaga agar redirect di dalam tab workspace tetap memakai layout embed.
 * Tanpa ini, halaman setelah simpan/proses bisa merender layout penuh di
 * dalam iframe dan memicu iframe bersarang.
 */
class WorkspaceEmbedTest extends TestCase
{
    private function handle(Request $request, string $location)
    {
        $middleware = new PropagateWorkspaceEmbed();

        return $middleware->handle($request, fn () => redirect($location));
    }

    public function test_embed_redirect_gets_embed_parameter(): void
    {
        $request = Request::create('/estimator/requests/5/answer', 'POST', ['embed' => '1']);

        $response = $this->handle($request, '/estimator/requests/5');

        $this->assertStringContainsString('embed=1', (string) $response->headers->get('Location'));
    }

    public function test_embed_redirect_preserves_existing_query(): void
    {
        $request = Request::create('/estimator/requests/5/answer?embed=1', 'POST');

        $response = $this->handle($request, '/estimator/requests/5?tab=items');

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('tab=items', $location);
        $this->assertStringContainsString('embed=1', $location);
    }

    public function test_non_embed_redirect_is_not_modified(): void
    {
        $request = Request::create('/estimator/requests/5/answer', 'POST');

        $response = $this->handle($request, '/estimator/requests/5');

        $this->assertStringNotContainsString('embed=1', (string) $response->headers->get('Location'));
    }

    public function test_external_redirect_is_not_modified(): void
    {
        $request = Request::create('/estimator/requests/5/answer', 'POST', ['embed' => '1']);

        $response = $this->handle($request, 'https://accounts.google.com/o/oauth2/auth');

        $this->assertSame(
            'https://accounts.google.com/o/oauth2/auth',
            $response->headers->get('Location'),
        );
    }

    public function test_absolute_same_host_redirect_gets_embed_parameter(): void
    {
        $request = Request::create('/estimator/requests/5/answer', 'POST', ['embed' => '1']);

        $response = $this->handle($request, 'http://localhost/estimator/requests/5');

        $this->assertStringContainsString('embed=1', (string) $response->headers->get('Location'));
    }
}
