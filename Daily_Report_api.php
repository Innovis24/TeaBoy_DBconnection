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
        if ($action === 'getUsers') {
            getUsers($conn); // Call getUsers function for 'getUsers' action
        } elseif ($action === 'getReports') {
            handleGet($conn); // Call getReports function for 'getReports' action
        }
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
    $sql = 'SELECT * FROM daily_report ORDER BY Date ASC';
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
}
function getUsers($conn) {
    $sql = 'SELECT * FROM owner_name';
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
}

function handlePost($conn) {
    $inputData = file_get_contents("php://input");
    $data = json_decode($inputData, true);
    if ($data && isset($data) && is_array($data)) {
    foreach ($data as $record) {

        $Date = $conn->real_escape_string($record['Date']);
        $Day = $conn->real_escape_string($record['Day']);
        $cashAmount = $conn->real_escape_string($record['cashAmount']);
        $reportedTo = $conn->real_escape_string($record['reportedTo']);
        $cashHolder = $conn->real_escape_string($record['cashHolder']);
        $gPay = $conn->real_escape_string($record['gPay']);
        $gPayHolder = $conn->real_escape_string($record['gPayHolder']);
        $PettyCash = $conn->real_escape_string($record['PettyCash']);
        $Total = $conn->real_escape_string($record['Total']);
        $ActualCollection = $conn->real_escape_string($record['ActualCollection']);
        $CashTaken = $conn->real_escape_string($record['CashTaken']);
        $TotalCashinTaken = $conn->real_escape_string($record['TotalCashinTaken']);


        $checkRecord = "SELECT count(*) as count FROM daily_report WHERE Date = '$Date'";
        $result = $conn->query($checkRecord);
        
        if ($result) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            if ($count > 0) {
                $update_sql = "UPDATE daily_report SET ActualCollection = '$ActualCollection' ,TotalCashinTaken = '$TotalCashinTaken' 
                WHERE Date = '$Date'";
                if ($conn->query($update_sql) === TRUE) {
                    echo "Record updated successfully.";

                } else {
                    echo "Error updating record: " . $conn->error;
                }
            } else {
                // Insert new record
                $insert_sql = "INSERT INTO daily_report (Date, Day,cashAmount, reportedTo, cashHolder, gPay, gPayHolder, PettyCash,Total,ActualCollection,CashTaken,TotalCashinTaken)  VALUES ('$Date','$Day', '$cashAmount', '$reportedTo', '$cashHolder', '$gPay', '$gPayHolder', '$PettyCash','$Total','$ActualCollection','$CashTaken',' $TotalCashinTaken')";
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
    // Function to handle DELETE requests

}
}
// Function to handle DELETE requests
function handleDelete($conn) {
        $data = json_decode(file_get_contents('php://input'), true);
        $Date = isset($data['Date']) ? $conn->real_escape_string($data['Date']) : null; // Expect date in 'YYYY-MM-DD' format
    
        if (!$Date) {
            echo json_encode(['error' => 'Date is required for deletion and recalculation']);
            return;
        }
    
        // Step 1: Delete the specified record for the given date
        $deleteQuery = "DELETE FROM daily_report WHERE Date = '$Date'";
        if ($conn->query($deleteQuery) === TRUE) {
            echo json_encode(['message' => "Record with Date $Date deleted successfully"]);
        } else {
            echo json_encode(['error' => "Error deleting record with Date $date: " . $conn->error]);
            return;
        }
    
        // Step 2: Retrieve and recalculate remaining records for the given date
        $fetchQuery = "SELECT * FROM daily_report ORDER BY Date ASC";
        $result = $conn->query($fetchQuery);
    
        if ($result) {
            $remainingRecords = [];
            while ($row = $result->fetch_assoc()) {
                $remainingRecords[] = $row;
            }
    
            // Initialize variables for recalculation
            $cumulativeCashTaken = 0;
            $previousPettyCash = 0;
    
            foreach ($remainingRecords as &$record) {
                $cashAmount = (int)$record['cashAmount'];
                $gPay = (int)$record['gPay'];
                $pettyCash = (int)$record['PettyCash'];
                $cashTaken = (int)$record['CashTaken'];
    
                $record['ActualCollection'] = ($cashAmount + $gPay + $pettyCash) - $previousPettyCash;
                $cumulativeCashTaken += $record['CashTaken'];
                $record['TotalCashinTaken'] = $cumulativeCashTaken;
    
                $previousPettyCash = $pettyCash;
    
                // Update recalculated values back into the database
                $updateQuery = "UPDATE daily_report SET 
                                ActualCollection = '{$record['ActualCollection']}', 
                                TotalCashinTaken = '{$record['TotalCashinTaken']}' 
                                WHERE Date = '{$record['Date']}'";
    
                if (!$conn->query($updateQuery)) {
                    echo json_encode(['error' => "Error updating record for Date {$record['Date']}: " . $conn->error]);
                    return;
                }
            }
    
            echo json_encode(['message' => 'Records recalculated successfully']);
        } else {
            echo json_encode(['error' => "Error fetching records for recalculation: " . $conn->error]);
        }
    }

    function handlePut($conn) {
   
        $inputData = file_get_contents("php://input");
        $data = json_decode($inputData, true);
        foreach ($data as $index => $record) {
            $Date = $conn->real_escape_string($record['Date']);
            $Day = $conn->real_escape_string($record['Day']);
            $cashAmount = $conn->real_escape_string($record['cashAmount']);
            $reportedTo = $conn->real_escape_string($record['reportedTo']);
            $cashHolder = $conn->real_escape_string($record['cashHolder']);
            $gPay = $conn->real_escape_string($record['gPay']);
            $gPayHolder = $conn->real_escape_string($record['gPayHolder']);
            $PettyCash = $conn->real_escape_string($record['PettyCash']);
            $Total = $conn->real_escape_string($record['Total']);
            $ActualCollection = $conn->real_escape_string($record['ActualCollection']);
            $CashTaken = $conn->real_escape_string($record['CashTaken']);
            $TotalCashinTaken = $conn->real_escape_string($record['TotalCashinTaken']);
    
        // First, fetch the previous record to calculate the new values
        $checkRecord = "SELECT * FROM daily_report WHERE  Date = '$Date'";
        $result = $conn->query($checkRecord);
    
        if ($result) {
            $count = $result->num_rows;
    
            if ($count > 0) {
                $update_sql = "UPDATE daily_report SET 
                Date = '$Date', 
                Day = '$Day', 
                cashAmount = '$cashAmount' , reportedTo = '$reportedTo' ,
                cashHolder = '$cashHolder' , gPay = '$gPay' ,
                gPayHolder = '$gPayHolder' , reportedTo = '$reportedTo' ,
                Total = '$Total' , PettyCash = '$PettyCash' ,
                CashTaken = '$CashTaken' , TotalCashinTaken = '$TotalCashinTaken' ,
                ActualCollection = '$ActualCollection'
                WHERE  Date = '$Date' ";


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