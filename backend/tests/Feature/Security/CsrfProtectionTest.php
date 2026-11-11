<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    private function csrfMiddleware(): VerifyCsrfToken
    {
        return new class(
            $this->app,
            $this->app['encrypter']
        ) extends VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    }

    private function createPostRequest(): Request
    {
        $session = $this->app['session.store'];
        $session->start();

        $request = Request::create(
            '/web/auth/login',
            'POST'
        );

        $request->setLaravelSession($session);

        return $request;
    }

    public function test_post_request_without_csrf_token_is_rejected(): void
    {
        $request = $this->createPostRequest();

        $this->expectException(
            TokenMismatchException::class
        );

        $this->csrfMiddleware()->handle(
            $request,
            fn (Request $request): Response => new Response('OK')
        );
    }

    public function test_post_request_with_invalid_csrf_token_is_rejected(): void
    {
        $request = $this->createPostRequest();

        $request->headers->set(
            'X-CSRF-TOKEN',
            'invalid-csrf-token'
        );

        $this->expectException(
            TokenMismatchException::class
        );

        $this->csrfMiddleware()->handle(
            $request,
            fn (Request $request): Response => new Response('OK')
        );
    }

    public function test_post_request_with_valid_csrf_token_is_allowed(): void
    {
        $request = $this->createPostRequest();

        $request->headers->set(
            'X-CSRF-TOKEN',
            $request->session()->token()
        );

        $response = $this->csrfMiddleware()->handle(
            $request,
            fn (Request $request): Response => new Response('OK')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }
}
