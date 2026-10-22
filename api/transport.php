<?php
/**
 * REST API: Smart School Bus & Transport Tracker
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);

$routes = [
    [
        'route_id' => 'R-01',
        'route_name' => 'City Center & Station Road Express',
        'bus_no' => 'MH-39-AZ-8446',
        'driver_name' => 'Ramesh Patil',
        'driver_phone' => '+91 94222 18446',
        'attendant_name' => 'Sunita Shinde',
        'attendant_phone' => '+91 98810 54321',
        'current_status' => 'On Route to School',
        'speed' => '34 km/h',
        'next_stop' => 'Shroff High School Square',
        'eta' => '07:42 AM',
        'stops' => [
            ['name' => 'Nandurbar Railway Station', 'time' => '07:15 AM', 'status' => 'Completed'],
            ['name' => 'Subhash Chowk', 'time' => '07:25 AM', 'status' => 'Completed'],
            ['name' => 'Shroff High School Square', 'time' => '07:42 AM', 'status' => 'Approaching'],
            ['name' => 'Old Nalwa Road Junction', 'time' => '07:55 AM', 'status' => 'Pending'],
            ['name' => 'Bellwether International School Campus', 'time' => '08:05 AM', 'status' => 'Pending']
        ]
    ],
    [
        'route_id' => 'R-02',
        'route_name' => 'Karanjwan & Dondaicha Bypass Route',
        'bus_no' => 'MH-39-BY-1204',
        'driver_name' => 'Ganesh More',
        'driver_phone' => '+91 97654 88765',
        'attendant_name' => 'Kavita Jadhav',
        'attendant_phone' => '+91 91580 99887',
        'current_status' => 'Completed Morning Drop',
        'speed' => '0 km/h (Parked)',
        'next_stop' => 'Campus Bay 3',
        'eta' => 'Arrived',
        'stops' => [
            ['name' => 'Dondaicha Naka', 'time' => '07:10 AM', 'status' => 'Completed'],
            ['name' => 'Karanjwan Phata', 'time' => '07:30 AM', 'status' => 'Completed'],
            ['name' => 'Police Colony Gate', 'time' => '07:45 AM', 'status' => 'Completed'],
            ['name' => 'Bellwether International School Campus', 'time' => '08:00 AM', 'status' => 'Completed']
        ]
    ]
];

api_response([
    'school' => 'Bellwether International School Nandurbar',
    'total_routes' => count($routes),
    'emergency_transport_desk' => '+91 6262848446',
    'routes' => $routes
]);
