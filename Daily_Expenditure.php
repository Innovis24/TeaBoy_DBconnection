<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
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
        if ($action === 'getitemlist') {
            handleGetItemList($conn);
        } elseif ($action === 'getReports') {
            handleGetReports($conn);
        } else {
            echo json_encode(['error' => 'Invalid GET action']);
        }
        break;

    case 'POST':
        handlePost($conn);
        break;
    case 'DELETE':
            handleDelete($conn);
            break;
    default:
        echo json_encode(['error' => 'Invalid request method']);
}

$conn->close();

// Function to handle GET requests for daily expenditure
function handleGetReports($conn) {
    // Check if a filterDate is provided
    $filterDate = isset($_GET['filterDate']) ? $conn->real_escape_string($_GET['filterDate']) : null;

    // Base SQL query
    $sql = "SELECT * FROM Daily_Expenditure";

    // Add a WHERE clause if a filterDate is provided
    if ($filterDate) {
        $sql .= " WHERE Date = '$filterDate'";
    }

    $result = $conn->query($sql);

    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    echo json_encode($data);
}


// Function to handle GET requests for item list
function handleGetItemList($conn) {
    $sql = 'SELECT * FROM item_list';
    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    // Log data for debugging
    file_put_contents('log.txt', json_encode($data));
    echo json_encode($data);
}


// Function to handle POST requests
function handlePost($conn) {
    $inputData = file_get_contents("php://input");
    $data = json_decode($inputData, true);

    if ($data && isset($data['Date'], $data['Itemname'], $data['Amount'])) {
        $Date = $conn->real_escape_string($data['Date']);
        $Itemname = $conn->real_escape_string($data['Itemname']);
        $Amount = $conn->real_escape_string($data['Amount']);
        $branchname = $conn->real_escape_string($data['BranchName']);
        $managername =  $conn->real_escape_string($data['ManagerName']);

        $sql = "INSERT INTO daily_expenditure (Date, BranchName,ManagerName,Itemname, Amount) VALUES ('$Date', '$branchname','$managername','$Itemname', '$Amount')";

        if ($conn->query($sql) === TRUE) {
            echo json_encode(['message' => 'Record inserted successfully']);
        } else {
            echo json_encode(['error' => $conn->error]);
        }
    } else {
        echo json_encode(['error' => 'Invalid input data']);
    }
}


// Function to handle DELETE requests
function handleDelete($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $Sno = isset($data['Sno']) ? $conn->real_escape_string($data['Sno']) : null;

    if (!$Sno) {
        echo json_encode(['error' => 'Sno is required for deletion']);
        return;
    }

    $deleteQuery = "DELETE FROM daily_expenditure WHERE Sno = '$Sno'";
    if ($conn->query($deleteQuery) === TRUE) {
        echo json_encode(['message' => "Record with Sno $Sno deleted successfully"]);
    } else {
        echo json_encode(['error' => "Error deleting record with Sno $Sno: " . $conn->error]);
    }
}


?>

