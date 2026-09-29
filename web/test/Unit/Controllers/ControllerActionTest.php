<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Controllers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Controllers\ControllerAction;
use Vwork\Web\Controllers\ControllerError;
use Vwork\Web\Test\Stubs\ControllerStub;

final class ControllerActionTest extends TestCase
{
    #[Test]
    public function verify_accepts_a_marked_action_with_the_right_shape(): void
    {
        ControllerAction::verify(new ControllerStub(), 'index');

        $this->addToAssertionCount(1); // no exception
    }

    #[Test]
    #[TestWith(['missing'])]
    #[TestWith(['notMarked'])]
    #[TestWith(['notPublic'])]
    #[TestWith(['wrongParams'])]
    #[TestWith(['wrongReturn'])]
    public function verify_rejects_anything_else(string $method): void
    {
        $this->expectException(ControllerError::class);
        ControllerAction::verify(new ControllerStub(), $method);
    }
}
