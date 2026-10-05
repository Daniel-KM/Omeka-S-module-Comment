<?php declare(strict_types=1);

namespace Comment\Spam;

/**
 * Abstraction for the spam check applied to a submitted comment.
 *
 * The only implementation delegates to the SpamGuard module when it is active,
 * and a null checker is used otherwise.
 */
interface SpamCheckerInterface
{
    /**
     * Return the list of matched spam reasons for the given submission context.
     *
     * The context contains the request snapshot: ip, userAgent, email, subject,
     * body, userId.
     *
     * @return string[] Reason keys, e.g. ['dnsbl', 'bannedIp']. Empty when the
     *   submission is not spam.
     */
    public function check(array $context): array;
}
