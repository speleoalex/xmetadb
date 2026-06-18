<?php
/**
 * Minimal, dependency-free test runner for xmetadb tests.
 * Run any test with: php tests/test_xxx.php
 */
class TestRunner
{
    private $passed  = 0;
    private $failed  = 0;
    private $errors  = [];
    private $section = '';

    function section($name)
    {
        $this->section = $name;
        echo "\n  [{$name}]\n";
    }

    function eq($expected, $actual, $msg)
    {
        if ($expected === $actual) {
            $this->passed++;
            echo "    PASS  {$msg}\n";
        } else {
            $this->failed++;
            $detail = 'expected=' . var_export($expected, true) . '  got=' . var_export($actual, true);
            $label  = $this->section ? "[{$this->section}] " : '';
            $this->errors[] = $label . $msg . ' | ' . $detail;
            echo "    FAIL  {$msg}\n          {$detail}\n";
        }
    }

    function ok($value, $msg)
    {
        $this->eq(true, (bool)$value, $msg);
    }

    function notOk($value, $msg)
    {
        $this->eq(false, (bool)$value, $msg);
    }

    function cnt($expected, $value, $msg)
    {
        $this->eq($expected, is_array($value) ? count($value) : null, $msg);
    }

    function isStr($value, $msg)
    {
        $this->ok(is_string($value), $msg . ' [is string]');
    }

    function summary($title)
    {
        $total = $this->passed + $this->failed;
        echo "\n=== {$title}: {$this->passed}/{$total} passed";
        if ($this->failed > 0) {
            echo ", {$this->failed} FAILED ===\nFailures:\n";
            foreach ($this->errors as $e) {
                echo "  • {$e}\n";
            }
        } else {
            echo " ===";
        }
        echo "\n";
        return $this->failed === 0;
    }

    function getFailCount() { return $this->failed; }
    function getPassCount() { return $this->passed; }
}
