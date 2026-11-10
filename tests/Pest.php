<?php

/**
 * Pest configuration, for the Unit suite.
 *
 * The Integration suite is class-based PHPUnit 9.6 under tests/Integration, run
 * from its own Composer setup; Pest never loads it.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Add Mockery's expectation count to the running test's assertions.
 *
 * Pest binds the test to `$this`, which static analysis types as `mixed`, so
 * the test is narrowed here before it is called.
 *
 * @param  mixed  $test  The running test.
 */
function countMockeryExpectations(mixed $test): void
{
    if ($test instanceof TestCase) {
        $test->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }
}

/*
 * Brain Monkey around every test.
 *
 * setUp() defines WordPress' hook functions and arms Patchwork; tearDown()
 * verifies every expectation the test set (a `->once()` that never happened
 * fails here) and removes every mock, so nothing leaks into the next test.
 *
 * The assertion count: Brain Monkey's expectations are Mockery expectations,
 * which PHPUnit does not count. Without adding them, a test whose only check is
 * `Functions\expect(...)->once()` reports as risky, and failOnRisky fails it.
 */
uses()
    ->beforeEach(function (): void {
        setUp();
    })
    ->afterEach(function (): void {
        countMockeryExpectations($this);

        tearDown();
    })
    ->in('Unit');
