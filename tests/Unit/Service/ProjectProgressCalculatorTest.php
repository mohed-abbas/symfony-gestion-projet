<?php

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Service\ProjectProgressCalculator;
use PHPUnit\Framework\TestCase;

class ProjectProgressCalculatorTest extends TestCase
{
    private ProjectProgressCalculator $calculator;

    protected function setUp(): void
    {
        // taskCompletion/pointCompletion sont purs : le repo n'est jamais appelé ici (stub suffit).
        $this->calculator = new ProjectProgressCalculator($this->createStub(TaskRepository::class));
    }

    public function testTaskCompletionIsZeroWhenNoTasks(): void
    {
        $this->assertSame(0, $this->calculator->taskCompletion([]));
    }

    public function testTaskCompletionIsHundredWhenAllDone(): void
    {
        $this->assertSame(100, $this->calculator->taskCompletion([Task::STATUS_DONE => 4]));
    }

    public function testTaskCompletionRoundsPartialShare(): void
    {
        // 1 done sur 3 → 33.33 % arrondi à 33.
        $counts = [Task::STATUS_TODO => 2, Task::STATUS_DONE => 1];
        $this->assertSame(33, $this->calculator->taskCompletion($counts));
    }

    public function testPointCompletionGuardsAgainstZeroTotal(): void
    {
        $this->assertSame(0, $this->calculator->pointCompletion(0, 0));
    }

    public function testPointCompletionRoundsPartialShare(): void
    {
        // 5 points livrés sur 8 → 62.5 % arrondi à 63.
        $this->assertSame(63, $this->calculator->pointCompletion(5, 8));
    }
}
