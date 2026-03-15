<?php

use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\Result;
use MarcoKoepfli\LaravelPatrol\ResultCollection;

it('starts empty', function () {
    $collection = new ResultCollection;
    expect($collection)->toHaveCount(0)
        ->and($collection->hasErrors())->toBeFalse()
        ->and($collection->hasWarnings())->toBeFalse();
});

it('adds and counts results', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'message 1'));
    $collection->add(new Result('test', 'message 2'));

    expect($collection)->toHaveCount(2);
});

it('detects errors', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'warning', severity: Severity::Warning));
    expect($collection->hasErrors())->toBeFalse();

    $collection->add(new Result('test', 'error', severity: Severity::Error));
    expect($collection->hasErrors())->toBeTrue();
});

it('detects warnings', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'info', severity: Severity::Info));
    expect($collection->hasWarnings())->toBeFalse();

    $collection->add(new Result('test', 'warning', severity: Severity::Warning));
    expect($collection->hasWarnings())->toBeTrue();
});

it('filters by severity', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'error', severity: Severity::Error));
    $collection->add(new Result('test', 'warning', severity: Severity::Warning));
    $collection->add(new Result('test', 'info', severity: Severity::Info));
    $collection->add(new Result('test', 'warning2', severity: Severity::Warning));

    expect($collection->bySeverity(Severity::Error))->toHaveCount(1)
        ->and($collection->bySeverity(Severity::Warning))->toHaveCount(2)
        ->and($collection->bySeverity(Severity::Info))->toHaveCount(1);
});

it('groups by file', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'msg1', file: 'app/Service.php'));
    $collection->add(new Result('test', 'msg2', file: 'app/Service.php'));
    $collection->add(new Result('test', 'msg3', file: 'app/Controller.php'));

    $grouped = $collection->groupByFile();
    expect($grouped)->toHaveCount(2)
        ->and($grouped['app/Service.php'])->toHaveCount(2)
        ->and($grouped['app/Controller.php'])->toHaveCount(1);
});

it('groups by rule', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('rule-a', 'msg1'));
    $collection->add(new Result('rule-a', 'msg2'));
    $collection->add(new Result('rule-b', 'msg3'));

    $grouped = $collection->groupByRule();
    expect($grouped)->toHaveCount(2)
        ->and($grouped['rule-a'])->toHaveCount(2)
        ->and($grouped['rule-b'])->toHaveCount(1);
});

it('merges results', function () {
    $collection = new ResultCollection;
    $collection->merge([
        new Result('test', 'msg1'),
        new Result('test', 'msg2'),
    ]);

    expect($collection)->toHaveCount(2);
});

it('is iterable', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'msg1'));
    $collection->add(new Result('test', 'msg2'));

    $count = 0;
    foreach ($collection as $result) {
        $count++;
        expect($result)->toBeInstanceOf(Result::class);
    }
    expect($count)->toBe(2);
});

it('converts to array', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'msg', file: 'test.php', line: 5, severity: Severity::Warning));

    $array = $collection->toArray();
    expect($array)->toHaveCount(1)
        ->and($array[0])->toHaveKeys(['rule', 'message', 'file', 'line', 'severity'])
        ->and($array[0]['rule'])->toBe('test')
        ->and($array[0]['severity'])->toBe('warning');
});

it('handles null file in groupByFile', function () {
    $collection = new ResultCollection;
    $collection->add(new Result('test', 'msg without file'));

    $grouped = $collection->groupByFile();
    expect($grouped)->toHaveKey('(no file)')
        ->and($grouped['(no file)'])->toHaveCount(1);
});
