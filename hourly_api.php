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
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    default:
        echo json_encode(['error' => 'Invalid request method']);
}

$conn->close();

// Function to handle GET requests
function handleGet($conn) {
    $sql = 'SELECT * FROM hourly_report ORDER BY BranchName ASC, ID ASC';
    $result = $conn->query($sql);

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode($users);
}
// Function to handle POST requests (Create)
function handlePost($conn) {
 
    $inputData = file_get_contents("php://input");
    $data = json_decode($inputData, true);
    if ($data && isset($data) && is_array($data)) {
    foreach ($data as $record) {

        $report_id =  $conn->real_escape_string($record['ID']);
        $Date = $conn->real_escape_string($record['Date']);
        $start_ID = $conn->real_escape_string($record['endtime']);
        $petty_cash = $conn->real_escape_string($record['pettycash']);
        $amount_enter = $conn->real_escape_string($record['amounttaken']);
        $sales = $conn->real_escape_string($record['Sales']);
        $total_amount = $conn->real_escape_string($record['totalamount']);
        $cashin_hand = $conn->real_escape_string($record['cashinhand']);
        $branchname = $conn->real_escape_string($record['BranchName']);
        $managername =  $conn->real_escape_string($record['ManagerName']);

    $checkRecord = "SELECT count(*) as count FROM hourly_report WHERE ID = $report_id AND BranchName='$branchname'";
    $result = $conn->query($checkRecord);
            
    if ($result) {
        $row = $result->fetch_assoc();
        $count = $row['count'];
    
        if ($count > 0) {

            $update_sql = "UPDATE hourly_report SET totalamount = '$total_amount' ,cashinhand = '$cashin_hand' , Sales='$sales'
            WHERE ID = '$report_id' AND BranchName='$branchname'";
            if ($conn->query($update_sql) === TRUE) {
                echo "Record updated successfully.";
            } else {
                echo "Error updating record: " . $conn->error;
            }
        } else {
            // Insert new record
            $insert_sql = "INSERT INTO hourly_report (ID,Date,BranchName,ManagerName,endtime,pettycash,amounttaken,totalamount,cashinhand,Sales) 
        VALUES ('$report_id','$Date','$branchname','$managername','$start_ID', '$petty_cash', '$amount_enter', '$total_amount', '$cashin_hand','$sales')";
        
            if ($conn->query($insert_sql) === TRUE) {
                echo "Record inserted successfully.";
            } else {
                echo "Error inserting record: " . $conn->error;
            }
        }
    } else {
        echo "Error fetching record count: " . $conn->error;
    }
}
    }
}
// Function to handle DELETE requests
function handleDelete($conn) {
    // Get the data from the request
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['ID']) ? $conn->real_escape_string($data['ID']) : null;
    $Date = isset($data['Date']) ? $conn->real_escape_string($data['Date']) : null;
    $branchname =  isset($data['BranchName']) ? $conn->real_escape_string($data['BranchName']) : null;

    // Ensure the ID is provided
    if (!$id) {
        echo json_encode(['error' => 'ID is required for deletion']);
        return;
    }

    // Step 1: Delete the specified record from the database
    $deleteQuery = "DELETE FROM hourly_report WHERE ID = $id AND BranchName='$branchname'";
    if ($conn->query($deleteQuery)) {
        echo json_encode(['message' => "Record with ID $id deleted successfully"]);
    } else {
        echo json_encode(['error' => "Error deleting record with ID $id: " . $conn->error]);
        return;
    }

    // Step 2: Retrieve all records for the given date
    $fetchQuery = "SELECT * FROM hourly_report WHERE DATE = '$Date' ORDER BY ID ASC";
    $result = $conn->query($fetchQuery);

    if ($result) {
        $remainingRecords = [];
        while ($row = $result->fetch_assoc()) {
            $remainingRecords[] = $row;
        }

        // Step 3: Recalculate cashinhand, sales, and totalamount
        $cumulativeAmount = 0;
        $previousRecord = null;  // To hold the previous record for calculations

        foreach ($remainingRecords as &$record) {
            // Get the current values
            $currentPettyCash = (int)$record['pettycash'];
            $currentAmountTaken = (int)$record['amounttaken'];
            $previousPettyCash = $previousRecord ? (int)$previousRecord['pettycash'] : 0;
            $previousAmountTaken = $previousRecord ? (int)$previousRecord['amounttaken'] : 0;

            // Calculate the new values
            $totalAmount = $previousAmountTaken + $currentPettyCash + $currentAmountTaken;
            $sales = abs($currentPettyCash + $currentAmountTaken - $previousPettyCash);
            $cumulativeAmount += $totalAmount;
            // $cashinhand += (int)$record['amounttaken'];

            if ($record["BranchName"] === $branchname) {
                $cashinhand += (int)$record["amounttaken"];
            }

            // Update the record with the new calculated values
            $record['cashinhand'] = $cashinhand;
            $record['sales'] = $sales;
            $record['totalamount'] = $totalAmount;

            // Step 4: Update the database with the recalculated values
            $updateQuery = "UPDATE hourly_report 
                            SET cashinhand = '{$record['cashinhand']}', 
                                sales = '{$record['sales']}', 
                                totalamount = '{$record['totalamount']}' 
                            WHERE ID = {$record['ID']} AND BranchName='$branchname'";
            if (!$conn->query($updateQuery)) {
                echo json_encode(['error' => "Error updating record with ID {$record['ID']}: " . $conn->error]);
                return;
            }

            // Set the current record as the previous record for the next iteration
            $previousRecord = $record;
        }

        // Final message
        echo json_encode(['message' => 'Records recalculated successfully']);
    } else {
        echo json_encode(['error' => 'Error fetching records after deletion']);
    }
}
// Function to handle Update 
function handlePut($conn) {
   
    $inputData = file_get_contents("php://input");
    $data = json_decode($inputData, true);
    foreach ($data as $index => $record) {
    $report_id = $conn->real_escape_string($record['ID']);
    $Date = $conn->real_escape_string($record['Date']);
    $start_ID = $conn->real_escape_string($record['endtime']);
    $petty_cash = $conn->real_escape_string($record['pettycash']);
    $amount_enter = $conn->real_escape_string($record['amounttaken']);
    $sales = $conn->real_escape_string($record['Sales']);
    $total_amount = $conn->real_escape_string($record['totalamount']);
    $cashin_hand = $conn->real_escape_string($record['cashinhand']);
    $branchname = $conn->real_escape_string($record['BranchName']);

    // First, fetch the previous record to calculate the new values
    $checkRecord = "SELECT * FROM hourly_report WHERE ID = $report_id AND BranchName='$branchname'";
    $result = $conn->query($checkRecord);

    if ($result) {
        $count = $result->num_rows;

        if ($count > 0) {
         
            $update_sql = "UPDATE hourly_report SET 
                            totalamount = '$total_amount', 
                            cashinhand = '$cashin_hand', 
                            Sales = '$sales' , amounttaken = '$amount_enter' ,
                            pettycash = '$petty_cash'
                            WHERE ID = $report_id AND BranchName='$branchname'";

            if ($conn->query($update_sql) === TRUE) {
                echo json_encode(['message' => 'Record updated successfully']);
            } else {
                echo json_encode(['error' => 'Error updating record: ' . $conn->error]);
            }
        } else {
            echo json_encode(['error' => 'Record not found']);
        }
    } else {
        echo json_encode(['error' => 'Error fetching record count: ' . $conn->error]);
    }
}

}



?>