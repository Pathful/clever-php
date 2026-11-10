<?php

namespace Clever;

use PHPUnit\Framework\TestCase;

/**
 * Loads every model and both API classes with all errors reported, and fails on any
 * deprecation, notice or warning. Guards the PHP 8 fixes (#[\ReturnTypeWillChange],
 * explicit nullable types) against a regenerated SDK undoing them (POD-5962).
 * Run it on PHP 7.4 and on PHP 8.5.
 */
class Php8CompatTest extends TestCase
{
    /** @var string[] */
    private $problems = [];

    protected function setUp(): void
    {
        error_reporting(E_ALL);
        set_error_handler(function (int $level, string $message, string $file = '', int $line = 0): bool {
            $this->problems[] = basename($file) . ":{$line} {$message}";
            return true;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testEveryClassLoadsWithoutDeprecations(): void
    {
        $loaded = 0;
        foreach (glob(__DIR__ . '/../lib/Model/*.php') as $file) {
            $class = 'Clever\\Model\\' . basename($file, '.php');
            $reflection = new \ReflectionClass($class);
            if ($reflection->isInstantiable()) {
                new $class();
            }
            $loaded++;
        }
        foreach (['DataApi', 'EventsApi'] as $api) {
            $class = 'Clever\\Api\\' . $api;
            (new $class())->getConfig()->setAccessToken('test-token');
        }

        $this->assertGreaterThan(50, $loaded);
        $this->assertSame([], $this->problems);
    }

    public function testModelArrayAccess(): void
    {
        $school = new Model\School(['id' => 'school-1']);
        $response = new Model\SchoolResponse(['data' => $school]);

        $this->assertTrue(isset($response['data']));
        $this->assertSame('school-1', $response['data']->getId());

        $school['name'] = 'Example High';
        $this->assertSame('Example High', $school->getName());
        $this->assertIsString((string) $school);

        unset($school['name']);
        // Only isset after unset: the generated getters read the key directly and notice on 7.4 too.
        $this->assertFalse(isset($school['name']));
        $this->assertSame([], $this->problems);
    }
}
