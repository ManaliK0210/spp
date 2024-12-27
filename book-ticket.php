<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

ini_set('display_errors', 1);
error_reporting(E_ALL);  // Enable full error reporting

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['customer_id']) && isset($data['event_id']) && isset($data['ticket_count'])) {
    $customerId = intval($data['customer_id']);
    $eventId = intval($data['event_id']);
    $ticketCount = intval($data['ticket_count']);

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "spp";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die(json_encode(["message" => "Connection failed: " . $conn->connect_error]));
    }

    // Start a transaction
    $conn->begin_transaction();

    try {
        // Check event availability with prepared statement
        $stmt = $conn->prepare("SELECT available_tickets FROM events WHERE id = ?");
        if (!$stmt) {
            die(json_encode(["message" => "Error preparing SELECT statement: " . $conn->error]));
        }
        $stmt->bind_param("i", $eventId);
        
        // Log the query
        echo "Executing query: SELECT available_tickets FROM Events WHERE id = $eventId\n";

        if (!$stmt->execute()) {
            die(json_encode(["message" => "Error executing SELECT query: " . $stmt->error]));
        }
        
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if ($event && $event['available_tickets'] >= $ticketCount) {
            // Log the INSERT query
            echo "Executing query: INSERT INTO tickets (customer_id, event_id, ticket_count) VALUES ($customerId, $eventId, $ticketCount)\n";

            // Prepare statement to insert the ticket booking
            $stmt = $conn->prepare("INSERT INTO Tickets (customer_id, event_id, ticket_count) VALUES (?, ?, ?)");
            if (!$stmt) {
                die(json_encode(["message" => "Error preparing INSERT statement: " . $conn->error]));
            }
            $stmt->bind_param("iii", $customerId, $eventId, $ticketCount);
            if (!$stmt->execute()) {
                die(json_encode(["message" => "Error executing INSERT query: " . $stmt->error]));
            }

            // Log the UPDATE query
            echo "Executing query: UPDATE Events SET available_tickets = available_tickets - $ticketCount WHERE id = $eventId\n";

            // Update event availability
            $stmt = $conn->prepare("UPDATE Events SET available_tickets = available_tickets - ? WHERE id = ?");
            if (!$stmt) {
                die(json_encode(["message" => "Error preparing UPDATE statement: " . $conn->error]));
            }
            $stmt->bind_param("ii", $ticketCount, $eventId);
            if (!$stmt->execute()) {
                die(json_encode(["message" => "Error executing UPDATE query: " . $stmt->error]));
            }

            // Commit transaction
            $conn->commit();
            echo json_encode(["message" => "Ticket booked successfully."]);
        } else {
            echo json_encode(["message" => "Not enough tickets available."]);
        }
    } catch (Exception $e) {
        // Rollback transaction on any exception
        $conn->rollback();
        echo json_encode(["message" => "Error: " . $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(["message" => "Invalid input."]);
}
?>


