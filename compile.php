<?php

$target = 'build/compiled.php';
$source = 'main.php';
$matches = [];
$result = preg_match_all("/require_once \'(.*)\';/", file_get_contents($source), $matches);
if (empty($result)) {
    die('Cant find any files to combine!');
}

$files = array_merge([$source], $matches[1]);
$replaces = [
    "/<\?php/" => '',
    "/require_once \'.*\';/" => '',
    "/\\\$context\s=\sfopen.*/" => "\\\$context = STDIN;",
];

$content = '<?php' . PHP_EOL . '// Last compile time: ' . date(DATE_ATOM) . PHP_EOL;
foreach ($files as $file) {
    $fileContent = file_get_contents($file);

    foreach ($replaces as $pattern => $replace) {
        $fileContent = preg_replace($pattern, $replace, $fileContent, -1, $count);
    }

    $content .= $fileContent;
}

file_put_contents($target, $content);
echo 'Code compilation finished!' . PHP_EOL;
