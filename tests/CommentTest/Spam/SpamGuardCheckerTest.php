<?php declare(strict_types=1);

namespace CommentTest\Spam;

use Comment\Spam\SpamGuardChecker;
use PHPUnit\Framework\TestCase;

class SpamGuardCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\SpamGuard\SpamResult::class)) {
            $this->markTestSkipped('Requires SpamGuard module.');
        }
    }

    public function testDelegatesFullContextAndReturnsReasons(): void
    {
        $spamChecker = new class {
            public ?\SpamGuard\SpamContext $received = null;
            public function check(\SpamGuard\SpamContext $context): \SpamGuard\SpamResult
            {
                $this->received = $context;
                return new \SpamGuard\SpamResult(['honeypot', 'tooFast']);
            }
        };

        $reasons = (new SpamGuardChecker($spamChecker))->check([
            'ip' => '1.2.3.4',
            'userAgent' => 'Bot/1.0',
            'email' => 'bot@spam.example',
            'subject' => 'buy now',
            'body' => 'spam body',
            'formLoadedAt' => 1000,
            'honeypot' => 'filled',
            'powSalt' => 'abc',
            'powNonce' => '42',
            'prevSubmitAt' => 900,
            'prevSubmitIp' => '1.2.3.4',
        ]);

        $this->assertSame(['honeypot', 'tooFast'], $reasons);
        $ctx = $spamChecker->received;
        $this->assertSame('1.2.3.4', $ctx->ip);
        $this->assertSame('bot@spam.example', $ctx->email);
        $this->assertSame('spam body', $ctx->body);
        $this->assertSame(1000, $ctx->formLoadedAt);
        $this->assertSame('filled', $ctx->honeypotValue);
        $this->assertSame('abc', $ctx->extra['powSalt']);
        $this->assertSame('42', $ctx->extra['powNonce']);
        $this->assertSame(900, $ctx->extra['lastSubmitAt']);
    }

    public function testCleanSubmissionReturnsNoReason(): void
    {
        $spamChecker = new class {
            public function check(\SpamGuard\SpamContext $context): \SpamGuard\SpamResult
            {
                return new \SpamGuard\SpamResult([]);
            }
        };

        $this->assertSame([], (new SpamGuardChecker($spamChecker))->check([
            'ip' => '1.2.3.4',
            'body' => 'a legitimate comment',
        ]));
    }
}
