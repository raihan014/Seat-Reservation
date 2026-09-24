<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Models\User;

echo "========================================\n";
echo "SEAT RESERVATION PERFORMANCE BENCHMARK\n";
echo "========================================\n\n";

$baseUrl = 'http://127.0.0.1:8000';

// --------------------------------------------------
// Configuration
// --------------------------------------------------

$numberOfUsers = 50;
$eventCapacity = $numberOfUsers;

// --------------------------------------------------
// Create test event
// --------------------------------------------------

$event = Event::create([
    'name' => 'Performance Test Event ' . time(),
    'capacity' => $eventCapacity,
    'reserved_count' => 0,
]);

echo "Event created.\n";
echo "Event ID: {$event->id}\n";
echo "Capacity: {$event->capacity}\n";
echo "Requests: {$numberOfUsers}\n\n";

// --------------------------------------------------
// Create users and tokens
// --------------------------------------------------

$tokens = [];

for ($i = 1; $i <= $numberOfUsers; $i++) {

    $user = User::create([
        'name' => 'Benchmark User ' . $i . ' ' . time(),
        'email' => 'benchmark_' . time() . '_' . $i . '@example.com',
        'password' => bcrypt('password'),
    ]);

    $token = $user->createToken('benchmark-token')->plainTextToken;

    $tokens[] = $token;
}

echo "Created {$numberOfUsers} users and tokens.\n\n";

// --------------------------------------------------
// Prepare concurrent requests
// --------------------------------------------------

$multiHandle = curl_multi_init();

$handles = [];

$startTime = microtime(true);

foreach ($tokens as $index => $token) {

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/api/events/' . $event->id . '/reserve',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ]);

    curl_multi_add_handle($multiHandle, $ch);

    $handles[$index] = $ch;
}

// --------------------------------------------------
// Execute requests concurrently
// --------------------------------------------------

echo "Sending {$numberOfUsers} requests concurrently...\n";
echo "Please wait...\n\n";

$running = null;

do {
    curl_multi_exec($multiHandle, $running);

    if ($running) {
        curl_multi_select($multiHandle, 1.0);
    }

} while ($running);

$endTime = microtime(true);

// --------------------------------------------------
// Collect results
// --------------------------------------------------

$successCount = 0;
$conflictCount = 0;
$errorCount = 0;

$responseTimes = [];

foreach ($handles as $ch) {

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

    $responseTimes[] = $totalTime * 1000;

    if ($httpCode === 201) {
        $successCount++;
    } elseif ($httpCode === 409) {
        $conflictCount++;
    } else {
        $errorCount++;
    }

    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);

// --------------------------------------------------
// Calculate statistics
// --------------------------------------------------

$totalWallTime = ($endTime - $startTime) * 1000;

$averageTime = array_sum($responseTimes) / count($responseTimes);
$minimumTime = min($responseTimes);
$maximumTime = max($responseTimes);

// --------------------------------------------------
// Final database state
// --------------------------------------------------

$event->refresh();

$activeReservations = \App\Models\Reservation::where('event_id', $event->id)
    ->where('status', 'reserved')
    ->count();

// --------------------------------------------------
// Output
// --------------------------------------------------

echo "========================================\n";
echo "PERFORMANCE RESULTS\n";
echo "========================================\n";

echo "Requests:             {$numberOfUsers}\n";
echo "Successful (201):     {$successCount}\n";
echo "Conflicts (409):      {$conflictCount}\n";
echo "Other errors:         {$errorCount}\n\n";

echo "Average response:     " . number_format($averageTime, 2) . " ms\n";
echo "Fastest response:     " . number_format($minimumTime, 2) . " ms\n";
echo "Slowest response:     " . number_format($maximumTime, 2) . " ms\n";
echo "Total wall time:      " . number_format($totalWallTime, 2) . " ms\n\n";

echo "========================================\n";
echo "FINAL DATABASE STATE\n";
echo "========================================\n";

echo "Event ID:             {$event->id}\n";
echo "Capacity:             {$event->capacity}\n";
echo "Reserved count:       {$event->reserved_count}\n";
echo "Active reservations:  {$activeReservations}\n\n";

echo "========================================\n";
echo "CORRECTNESS CHECK\n";
echo "========================================\n";

if (
    $event->reserved_count <= $event->capacity &&
    $activeReservations === $event->reserved_count &&
    $errorCount === 0
) {
    echo "PASS: Database state is consistent.\n";
} else {
    echo "FAIL: Database state needs investigation.\n";
}

echo "\n========================================\n";
echo "BENCHMARK COMPLETE\n";
echo "========================================\n";