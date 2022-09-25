<?php

if(!isset($argv[1])) {
    exit('Please provide an m3u8 file as the first argument');
}

$m3u8 = $argv[1];

$fileBaseUrl = substr($m3u8, 0, strrpos($m3u8, '/'));
$folderName = substr($fileBaseUrl, strrpos($fileBaseUrl, '/') + 1);

// prepare our destination
$destinationFolder = 'downloads';
if(!is_dir($destinationFolder)) {
    mkdir($destinationFolder);
}

// extract the ts files
$contents = file_get_contents($m3u8);
preg_match_all('/^(.*?\.ts)/m', $contents, $files);

// empty our destination & open a stream
$destination = $destinationFolder . '/' . $folderName . '.ts';
file_put_contents($destination, '');
$stream = fopen($destination, 'w+');

$progress = 0;
$chunkCount = 7;
print('Downloading ' . $destination . ' in ' . ceil(count($files[0]) / $chunkCount) . ' chunks' . "\n");

// loop through our files in chunks
foreach (array_chunk($files[0], $chunkCount) as $chunkKey => $chunk) {
    $curls = [];
    
    // initialise multi-init
    $mh = curl_multi_init();
    
    foreach ($chunk as $key => $file) {
        $progress++;
        
        $curls[$progress] = curl_init($fileBaseUrl . '/' . $file);
        curl_setopt($curls[$progress], CURLOPT_RETURNTRANSFER, true);
        
        // add to our multi handle
        curl_multi_add_handle($mh, $curls[$progress]);
    }
    
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh);
        }
    } while ($active && $status == CURLM_OK);
    
    // clean up and write our data.
    foreach($curls as $curl) {
        curl_multi_remove_handle($mh, $curl);
        fputs($stream, curl_multi_getcontent($curl));
    }
    
    print('Completed chunk ' . $chunkKey . "\n");
    curl_multi_close($mh);
}

fclose($stream);

$newDestination = str_replace('.ts', '.mp4', $destination);
print('Running conversion');
exec("ffmpeg -i '$destination' -acodec copy -vcodec copy -f mp4 '$newDestination'");
exit('Done!');