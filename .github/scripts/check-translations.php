<?php

/**
 * Checks that translation files are in sync with each other and with the code.
 *
 * Usage: php .github/scripts/check-translations.php [--base=origin/develop]
 *
 * Errors (exit 1):   string used in code but missing from en.json; key in another language but not in en.json.
 * Warnings (exit 0): key in en.json missing from another language; key in en.json not used in code.
 * With --base, only keys touched by `git diff base...HEAD` are reported; without it, everything is.
 * Dynamic keys (e.g. __($var)) cannot be detected statically and are ignored.
 */
$root = dirname(__DIR__, 2);
$base = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = substr($arg, 7);
    }
}

// ponytail: only single/double-quoted literals; no concatenation or template strings.
const CALL_PATTERN = '/(?:__|trans|trans_choice|@lang|\$t|\$tc)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s';

$unescape = fn (string $s, string $quote): string => str_replace(['\\'.$quote, '\\\\'], [$quote, '\\'], $s);

// --- Language files
$jsonKeys = [];
foreach (glob("$root/lang/*.json") as $file) {
    $jsonKeys[basename($file, '.json')] = array_keys(json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR));
}
$languages = array_keys($jsonKeys);
$phpKeys = []; // lang => 'file.dot.path' => true
foreach ($languages as $lang) {
    foreach (glob("$root/lang/$lang/*.php") ?: [] as $file) {
        $flat = flatten(include $file);
        foreach (array_keys($flat) as $key) {
            $phpKeys[$lang][basename($file, '.php').'.'.$key] = true;
        }
    }
}
$phpFiles = array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys($phpKeys['en'] ?? [])));

function phpLabel(string $key): string
{
    [$file, $rest] = explode('.', $key, 2);

    return "$file.php: $rest";
}

function flatten(array $a, string $prefix = ''): array
{
    $out = [];
    foreach ($a as $k => $v) {
        is_array($v) ? $out += flatten($v, "$prefix$k.") : $out["$prefix$k"] = true;
    }

    return $out;
}

// --- Keys used in code
$used = [];
foreach (['app', 'resources', 'routes'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (! preg_match('/\.(php|vue|js)$/', $file->getFilename())) {
            continue;
        }
        if (preg_match_all(CALL_PATTERN, file_get_contents($file->getPathname()), $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $used[$unescape($hit[2], $hit[1])] = true;
            }
        }
    }
}

// --- Classify used keys: JSON string, PHP array key, or missing
$enJson = array_flip($jsonKeys['en']);
$usedJson = $usedPhp = $missing = [];
foreach (array_keys($used) as $key) {
    if (isset($enJson[$key])) {
        $usedJson[$key] = true;
    } elseif (in_array(explode('.', $key)[0], $phpFiles, true) && isset($phpKeys['en'][$key])) {
        $usedPhp[$key] = true;
    } else {
        $missing[] = $key;
    }
}

// --- Keys touched by the PR (null = no limit)
$touched = null;
if ($base) {
    $diff = shell_exec('git -C '.escapeshellarg($root).' diff -U0 '.escapeshellarg("$base...HEAD").' -- app resources routes lang 2>&1') ?? '';
    $touched = [];
    foreach (preg_split('/\R/', $diff) as $line) {
        if (! preg_match('/^[+-](?![+-])/', $line)) {
            continue;
        }
        $line = substr($line, 1);
        if (preg_match('/^\s*"((?:[^"\\\\]|\\\\.)*)"\s*:/', $line, $m)) { // JSON key
            $touched[json_decode('"'.$m[1].'"')] = true;
        }
        if (preg_match('/^\s*[\'"]([^\'"]+)[\'"]\s*=>/', $line, $m)) { // PHP array key (leaf only)
            $touched['leaf:'.$m[1]] = true;
        }
        if (preg_match_all(CALL_PATTERN, $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $touched[$unescape($hit[2], $hit[1])] = true;
            }
        }
    }
}
$isTouched = function (string $key) use ($touched): bool {
    if ($touched === null || isset($touched[$key])) {
        return true;
    }

    return str_contains($key, '.') && isset($touched['leaf:'.substr($key, (int) strrpos($key, '.') + 1)]);
};

// --- Checks
$errors = $warnings = [];
foreach ($missing as $key) {
    $isTouched($key) && $errors[] = "Used in code but missing from lang/en.json: $key";
}
foreach ($languages as $lang) {
    if ($lang === 'en') {
        continue;
    }
    $have = array_flip($jsonKeys[$lang]);
    foreach (array_diff($jsonKeys['en'], $jsonKeys[$lang]) as $key) {
        $isTouched($key) && $warnings[] = "Missing from lang/$lang.json: $key";
    }
    foreach (array_diff($jsonKeys[$lang], $jsonKeys['en']) as $key) {
        $isTouched($key) && $errors[] = "In lang/$lang.json but not in lang/en.json: $key";
    }
    foreach (array_diff_key($phpKeys['en'] ?? [], $phpKeys[$lang] ?? []) as $key => $_) {
        $isTouched($key) && $warnings[] = "Missing from lang/$lang/".phpLabel($key);
    }
    foreach (array_diff_key($phpKeys[$lang] ?? [], $phpKeys['en'] ?? []) as $key => $_) {
        in_array(explode('.', $key)[0], $phpFiles, true) && $isTouched($key)
            && $errors[] = "In lang/$lang/".phpLabel($key).' but not in lang/en';
    }
}
foreach (array_diff($jsonKeys['en'], array_keys($used)) as $key) {
    $isTouched($key) && $warnings[] = "Unused in code (may be dynamic): $key";
}

foreach ($warnings as $w) {
    echo "::warning::$w\n";
}
foreach ($errors as $e) {
    echo "::error::$e\n";
}
printf("%d error(s), %d warning(s)%s\n", count($errors), count($warnings), $base ? " (limited to keys touched since $base)" : '');
exit($errors ? 1 : 0);
