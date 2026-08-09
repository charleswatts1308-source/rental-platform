<?php

use App\Support\FileSize;

it('reads whole KB below 1MB', function () {
    expect(FileSize::human(424 * 1024))->toBe('424 KB');
    expect(FileSize::human(1023 * 1024))->toBe('1023 KB');
});

it('switches to MB at 1MB and keeps one decimal', function () {
    expect(FileSize::human(1048576))->toBe('1 MB');
    expect(FileSize::human(1439 * 1024))->toBe('1.4 MB');
    expect(FileSize::human(2935 * 1024))->toBe('2.9 MB');
});

it('never renders a real file as 0 KB', function () {
    // A 200-byte file rounds to 0 KB naively, which reads as "nothing is
    // attached" for something that IS attached.
    expect(FileSize::human(200))->toBe('1 KB');
});

it('treats a negative size as zero rather than erroring', function () {
    expect(FileSize::human(-5))->toBe('1 KB');
});

it('parses php.ini shorthand sizes', function () {
    expect(FileSize::fromIniShorthand('2M'))->toBe(2097152);
    expect(FileSize::fromIniShorthand('8M'))->toBe(8388608);
    expect(FileSize::fromIniShorthand('512K'))->toBe(524288);
    expect(FileSize::fromIniShorthand('1G'))->toBe(1073741824);
    // Case-insensitive, and a bare byte count is valid.
    expect(FileSize::fromIniShorthand('2m'))->toBe(2097152);
    expect(FileSize::fromIniShorthand('1048576'))->toBe(1048576);
});

it('returns 0 for a missing or unparseable ini value', function () {
    // 0 means "no usable limit reported", NOT "zero bytes allowed" — the
    // caller falls back to our own cap rather than refusing everything.
    expect(FileSize::fromIniShorthand(''))->toBe(0);
    expect(FileSize::fromIniShorthand(null))->toBe(0);
    expect(FileSize::fromIniShorthand('unlimited'))->toBe(0);
});
