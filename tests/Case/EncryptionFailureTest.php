<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Tests\TestCase;

/**
 * Prove rare platform failures fail closed without an exception chain carrying native call arguments.
 * @since 0.1.0
 */
final class EncryptionFailureTest extends TestCase
{
    /**
     * The nonce and sealing failures expose only a stable typed, secret-free refusal.
     * @return void
     * @since 0.1.0
     */
    public function testPlatformFailuresAreTypedUnchainedAndRedacted(): void
    {
        foreach (
            [
            'pass-through' => 'Native pass-through preserved.',
            'nonce' => 'The secret envelope nonce could not be generated.',
            'seal' => 'The secret envelope could not be sealed.',
            ] as $mode => $expected
        ) {
            $lines = [];
            $status = 0;
            $command = 'KUMWE_TEST_CIPHER_FAILURE=' . escapeshellarg($mode) . ' ' . escapeshellarg(PHP_BINARY)
                . ' -d zend.exception_ignore_args=0 '
                . escapeshellarg(dirname(__DIR__, 2) . '/tools/test-encryption-failure.php');
            exec($command, $lines, $status);
            $this->assertSame(0, $status, 'Isolated platform failure must be safely mapped.');
            $this->assertSame([$expected], $lines, 'The refusal must reveal no platform detail.');
        }
    }
}
