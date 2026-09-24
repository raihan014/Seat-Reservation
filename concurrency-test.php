<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$baseUrl = 'http://127.0.0.1:8000';

echo "========================================\n";
echo "  Laravel Seat Reservation Stress Test\n";
echo "========================================\n\n";


// --------------------------------------------------
// 1. Create a fresh event with ONE seat
// --------------------------------------------------

$event = Event::create([
    'name' => '50 User Concurrency Test',
    'capacity' => 1,
    'reserved_count' => 0,
]);

echo "Event created:\n";
echo "ID: {$event->id}\n";
echo "Capacity: {$event->capacity}\n";
echo "Reserved: {$event->reserved_count}\n\n";


// --------------------------------------------------
// 2. Create 50 users and tokens
// --------------------------------------------------

$requests = [];

for ($i = 1; $i <= 50; $i++) {

    $user = User::create([
        'name' => "Concurrency User {$i}",
        'email' => "concurrency{$event->id}_{$i}@test.com",
        'password' => Hash::make('password'),
    ]);

    $token = $user->createToken("concurrency-test-{$event->id}")->plainTextToken;

    $requests[] = [
        'user_id' => $user->id,
        'token' => $token,
    ];
}

echo "Created 50 users and tokens.\n\n";


// --------------------------------------------------
// 3. Prepare 50 concurrent HTTP requests
// --------------------------------------------------

$multiHandle = curl_multi_init();

$handles = [];

foreach ($requests as $index => $request) {

    $url = "{$baseUrl}/api/events/{$event->id}/reserve";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $request['token'],
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    curl_multi_add_handle($multiHandle, $ch);

    $handles[$index] = $ch;
}

echo "Sending 50 reservation requests concurrently...\n";
echo "Please wait...\n\n";


// --------------------------------------------------
// 4. Execute requests concurrently
// --------------------------------------------------

$running = null;

do {
    curl_multi_exec($multiHandle, $running);

    if ($running) {
        curl_multi_select($multiHandle);
    }

} while ($running);


// --------------------------------------------------
// 5. Collect results
// --------------------------------------------------

$successCount = 0;
$conflictCount = 0;
$errorCount = 0;

echo "========================================\n";
echo "REQUEST RESULTS\n";
echo "========================================\n";

foreach ($handles as $index => $ch) {

    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 201) {
        $successCount++;
    } elseif ($httpCode === 409) {
        $conflictCount++;
    } else {
        $errorCount++;
    }

    echo sprintf(
        "User %02d -> HTTP %d -> %s\n",
        $index + 1,
        $httpCode,
        trim($response)
    );

    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}

curl_multi_close($multiHandle);


// --------------------------------------------------
// 6. Check database state
// --------------------------------------------------

$event->refresh();

$activeReservations = \App\Models\Reservation::where(
    'event_id',
    $event->id
)->where(
    'status',
    'reserved'
)->count();

echo "\n========================================\n";
echo "FINAL DATABASE STATE\n";
echo "========================================\n";

echo "Event ID: {$event->id}\n";
echo "Capacity: {$event->capacity}\n";
echo "Reserved count: {$event->reserved_count}\n";
echo "Active reservations: {$activeReservations}\n";

echo "\n========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n";

echo "Successful reservations: {$successCount}\n";
echo "Conflict responses (409): {$conflictCount}\n";
echo "Other errors: {$errorCount}\n";

echo "\n";

if (
    $successCount === 1 &&
    $event->reserved_count === 1 &&
    $activeReservations === 1
) {
    echo "PASS: Concurrency protection is working correctly.\n";
} else {
    echo "FAIL: Database state is not correct.\n";
}

echo "\n";