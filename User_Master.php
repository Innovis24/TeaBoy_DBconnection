<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Database connection
$host = 'localhost';
$username = 'root'; // Default username
$password = ''; // Default password
$database = 'friendscafe';
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

// Determine the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : 'default';
        if ($action === 'getbranchName') {
            handleGetBranch($conn); // Call getUsers function for 'getUsers' action
        } elseif ($action === 'getuserlist') {
            handleGet($conn); // Call getReports function for 'getReports' action
        }
      
        break;
    default:
        echo json_encode(['error' => 'Invalid request method']);
}

$conn->close();

// Function to handle GET requests
function handleGet($conn) {
    $sql = 'SELECT * FROM user_list';
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode($users);

}

// Function to handle GET requests
function handleGetBranch($conn) {
    $sql = 'SELECT * FROM branch_name';
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode($users);

}



?>