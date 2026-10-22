<?php

$sampleRate = 44100;
$duration = 0.5;
$numSamples = (int)($sampleRate * $duration);
$data = '';

for ($i = 0; $i < $numSamples; $i++) {
    $t = $i / $sampleRate;
    // Bell chime: first note 659.25Hz (E5) transitions to 880Hz (A5)
    $env = exp(-7.0 * $t);
    $freq = $t < 0.12 ? 659.25 : 880.0;
    $harmonic = $freq * 2;
    
    $s1 = sin(2 * M_PI * $freq * $t);
    $s2 = sin(2 * M_PI * $harmonic * $t) * 0.35;
    
    $sample = ($s1 * 0.7 + $s2 * 0.3) * $env;
    $val = (int)(max(-1.0, min(1.0, $sample)) * 32767);
    $data .= pack('v', $val < 0 ? $val + 65536 : $val);
}

$dataLen = strlen($data);
$header = 'RIFF' 
    . pack('V', 36 + $dataLen) 
    . 'WAVEfmt ' 
    . pack('V', 16) 
    . pack('v', 1) 
    . pack('v', 1) 
    . pack('V', $sampleRate) 
    . pack('V', $sampleRate * 2) 
    . pack('v', 2) 
    . pack('v', 16) 
    . 'data' 
    . pack('V', $dataLen);

$dir = __DIR__ . '/../public/sounds';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

file_put_contents($dir . '/notification.wav', $header . $data);
file_put_contents($dir . '/notification.mp3', $header . $data); // Fallback copy

echo "Audio generated at {$dir}/notification.wav (bytes: " . strlen($header . $data) . ")\n";
