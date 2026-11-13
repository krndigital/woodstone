<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\StrongPin;
use Tests\TestCase;

class StrongPinTest extends TestCase
{
    private function fails(string $pin): bool
    {
        $failed = false;
        (new StrongPin)->validate('pin', $pin, function () use (&$failed): void {
            $failed = true;
        });

        return $failed;
    }

    public function test_weak_pins_are_rejected(): void
    {
        foreach (['0000', '1111', '1234', '2345', '6789', '9876', '4321', '123', '12345', 'abcd'] as $pin) {
            $this->assertTrue($this->fails($pin), "Expected {$pin} to be rejected");
        }
    }

    public function test_reasonable_pins_are_accepted(): void
    {
        foreach (['2580', '3691', '1357', '0912'] as $pin) {
            $this->assertFalse($this->fails($pin), "Expected {$pin} to be accepted");
        }
    }
}
