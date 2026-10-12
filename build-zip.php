<?php
$zip = new ZipArchive();
$zipFile = __DIR__ . '/indahweb-ai-auto-article.zip';
if (file_exists($zipFile)) {
    unlink($zipFile);
}

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to open zip file\n");
}

$exclude = array(
    '.git',
    'indahweb-ai-auto-article.zip',
    '.gitattributes',
    'build-zip.php',
    'test.zip'
);

$baseDir = __DIR__;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $path = $item->getPathname();
    $rel = substr($path, strlen($baseDir) + 1);

    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    if (in_array($parts[0], $exclude)) {
        continue;
    }

    $posixRel = 'indahweb-ai-auto-article/' . str_replace('\\', '/', $rel);

    if ($item->isDir()) {
        $zip->addEmptyDir($posixRel);
    } else {
        $zip->addFile($path, $posixRel);
    }
}

$zip->close();
echo "SUCCESS: Created $zipFile with size " . filesize($zipFile) . " bytes\n";
