<?php

session_start(); // Start or resume the session
require_once 'internal/db_connection.php';

// Function to initialize the game
function initializeGame($player1Name, $player2Name) {
    try {
        // Create a 7x7 board (filled with underscores for unused blocks)
        $board = array_fill(0, 7, array_fill(0, 7, '_'));

        // Call the function to create the game and get the IDs
        $gameData = createGameWithPlayers($player1Name, $player2Name);

        if ($gameData) {
            // Store player names and board in session
            $_SESSION['player1_name'] = $player1Name;
            $_SESSION['player2_name'] = $player2Name;
            $_SESSION['board'] = $board;
            $_SESSION['current_turn'] = $gameData['player1_id']; // Player 1 starts
            $_SESSION['game_id'] = $gameData['game_id'];

            // Fetch the available pieces for the current player
            $availablePieces = getAvailablePieces($gameData['player1_id'], $gameData['game_id']);

            // Display information
            echo "Game created successfully!\n\n";
            echo "Initial Board:\n";
            printBoard($board);
            echo "\nIt's {$player1Name}'s turn. Available pieces:\n";
            printAvailablePieces($availablePieces);
        } else {
            throw new Exception("Failed to initialize the game.");
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Function to create the game and insert players into the database
function createGameWithPlayers($player1Name, $player2Name) {
    $conn = null;
    try {
        $conn = getDatabaseConnection();

        // Prepare the SQL statement to call the stored procedure
        $stmt = $conn->prepare("CALL CreateGameWithPlayers(?, ?)");
        $stmt->bind_param("ss", $player1Name, $player2Name);
        $stmt->execute();

        // Fetch the results returned by the procedure
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $gameId = $row['game_id'];
            $player1Id = $row['player1_id'];
            $player2Id = $row['player2_id'];

            // Store the values in the session
            $_SESSION['game_id'] = $gameId;
            $_SESSION['player1_id'] = $player1Id;
            $_SESSION['player2_id'] = $player2Id;
            $_SESSION['current_turn'] = $player1Id; // Player 1 turn

            $stmt->close();
            return [
                'game_id' => $gameId,
                'player1_id' => $player1Id,
                'player2_id' => $player2Id
            ];
        } else {
            throw new Exception("Failed to retrieve IDs from the stored procedure.");
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        return false;
    } finally {
        // Close the connection
        if ($conn) {
            $conn->close();
        }
    }
}

function printBoard($board) {
    echo "<h3>Initial Board</h3>";
    echo "<table style='border-collapse: collapse;'>";

    foreach ($board as $row) {
        echo "<tr>";
        foreach ($row as $cell) {
            // Use underscores (_) for empty cells
            echo "<td style='width: 20px; height: 20px; text-align: center;'>{$cell}</td>";
        }
        echo "</tr>";
    }

    echo "</table>";
}

// Show the available peices
function printAvailablePieces($pieces) {
    echo "<h3>Available Pieces</h3>";
    echo "<div style='display: flex; flex-wrap: wrap; gap: 20px;'>";

    foreach ($pieces as $piece) {
        echo "<div style='margin: 10px;'>";
        echo "<p>Piece ID: {$piece['ID']} (Size: {$piece['sizeX']}x{$piece['sizeY']})</p>";

        // Convert text-based shape into an HTML table
        $rows = explode("\n", $piece['shape']);
        echo "<table style='border-collapse: collapse;'>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach (str_split($row) as $cell) {
                $color = ($cell === 'X') ? 'black' : 'white';
                echo "<td style='width: 20px; height: 20px; background: {$color};'></td>";
            }
            echo "</tr>";
        }
        echo "</table>";

        echo "</div>";
    }

    echo "</div>";
}

// Print the available pieces
function getAvailablePieces($playerId, $gameId) {
    try {
        $conn = getDatabaseConnection();

        $stmt = $conn->prepare("
            SELECT p.ID, p.sizeX, p.sizeY, p.shape 
            FROM PlayerPieces pp
            JOIN Pieces p ON pp.piece_id = p.ID
            WHERE pp.player_id = ? AND pp.game_id = ? AND pp.used = FALSE
        ");
        $stmt->bind_param("ii", $playerId, $gameId);
        $stmt->execute();
        $result = $stmt->get_result();

        $pieces = [];
        while ($row = $result->fetch_assoc()) {
            $pieces[] = $row;
        }

        $stmt->close();
        $conn->close();
        return $pieces;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        return [];
    }
}

?>
