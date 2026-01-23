<?php
/**
 * High Scores API for JunkDash game
 * Endpoints:
 *   GET  ?action=leaderboard - Get top scores
 *   POST action=submit       - Submit a new score
 *   POST action=setup        - Create database table (run once)
 */

require_once '../../php_includes/db_conx.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get action from GET params or JSON body
$action = $_GET['action'] ?? '';
$jsonInput = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    if (empty($action)) {
        $action = $jsonInput['action'] ?? '';
    }
}

switch ($action) {
    case 'setup':
        setupDatabase();
        break;
    case 'submit':
        submitScore($jsonInput);
        break;
    case 'leaderboard':
        getLeaderboard();
        break;
    case 'check_username':
        checkUsername();
        break;
    case 'check_player':
        checkPlayer();
        break;
    case 'update_username':
        updateUsername($jsonInput);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Create the high scores table
 */
function setupDatabase() {
    global $db_conx;
    
    $sql = "CREATE TABLE IF NOT EXISTS game_highscores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id VARCHAR(36) NOT NULL,
        username VARCHAR(50) NOT NULL,
        level INT NOT NULL DEFAULT 1,
        space_efficiency INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        INDEX idx_level (level DESC),
        INDEX idx_username (username),
        UNIQUE INDEX idx_player_id (player_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($db_conx, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Table created successfully']);
    } else {
        echo json_encode(['error' => 'Failed to create table: ' . mysqli_error($db_conx)]);
    }
    
    // Add player_id column to existing table if it doesn't exist
    $alterSql = "ALTER TABLE game_highscores ADD COLUMN player_id VARCHAR(36) DEFAULT NULL AFTER id";
    mysqli_query($db_conx, $alterSql); // Ignore error if column exists
    
    // Add unique index if not exists
    $indexSql = "ALTER TABLE game_highscores ADD UNIQUE INDEX idx_player_id (player_id)";
    mysqli_query($db_conx, $indexSql); // Ignore error if index exists
}

/**
 * Submit a new high score
 */
function submitScore($input) {
    global $db_conx;
    
    // Input is passed from the main handler
    
    $playerId = trim($input['player_id'] ?? '');
    $username = trim($input['username'] ?? '');
    $level = intval($input['level'] ?? 1);
    $spaceEfficiency = intval($input['spaceEfficiency'] ?? 0);
    
    // Validate player_id (UUID format)
    if (empty($playerId) || !preg_match('/^[a-f0-9-]{36}$/i', $playerId)) {
        echo json_encode(['error' => 'Invalid player ID']);
        return;
    }
    
    // Validate username
    if (empty($username)) {
        echo json_encode(['error' => 'Username is required']);
        return;
    }
    
    if (strlen($username) > 50) {
        $username = substr($username, 0, 50);
    }
    
    // Sanitize username - alphanumeric, spaces, underscores only
    $username = preg_replace('/[^a-zA-Z0-9 _-]/', '', $username);
    
    if (empty($username)) {
        echo json_encode(['error' => 'Invalid username']);
        return;
    }
    
    if ($level < 1) $level = 1;
    if ($level > 9999) $level = 9999;
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    
    // Check if this player already has a score (by player_id)
    $checkSql = "SELECT id, level FROM game_highscores WHERE player_id = ? LIMIT 1";
    $stmt = $db_conx->prepare($checkSql);
    $stmt->bind_param('s', $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing && $existing['level'] >= $level) {
        // Existing score is higher or equal - but still update username if changed
        $updateSql = "UPDATE game_highscores SET username = ? WHERE id = ?";
        $updateStmt = $db_conx->prepare($updateSql);
        $updateStmt->bind_param('si', $username, $existing['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Score recorded (personal best unchanged)',
            'personalBest' => $existing['level'],
            'isNewRecord' => false
        ]);
        return;
    }
    
    if ($existing) {
        // Update existing record (also update username in case it changed)
        $sql = "UPDATE game_highscores SET username = ?, level = ?, space_efficiency = ?, ip_address = ?, user_agent = ?, created_at = NOW() WHERE id = ?";
        $stmt = $db_conx->prepare($sql);
        $stmt->bind_param('siissi', $username, $level, $spaceEfficiency, $ipAddress, $userAgent, $existing['id']);
    } else {
        // Insert new record
        $sql = "INSERT INTO game_highscores (player_id, username, level, space_efficiency, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db_conx->prepare($sql);
        $stmt->bind_param('ssiiss', $playerId, $username, $level, $spaceEfficiency, $ipAddress, $userAgent);
    }
    
    if ($stmt->execute()) {
        // Get rank
        $rankSql = "SELECT COUNT(*) as rank FROM game_highscores WHERE level > ?";
        $rankStmt = $db_conx->prepare($rankSql);
        $rankStmt->bind_param('i', $level);
        $rankStmt->execute();
        $rankResult = $rankStmt->get_result();
        $rank = $rankResult->fetch_assoc()['rank'] + 1;
        $rankStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Score submitted successfully',
            'personalBest' => $level,
            'rank' => $rank,
            'isNewRecord' => true
        ]);
    } else {
        echo json_encode(['error' => 'Failed to submit score: ' . $stmt->error]);
    }
    
    $stmt->close();
}

/**
 * Get leaderboard
 */
function getLeaderboard() {
    global $db_conx;
    
    $limit = intval($_GET['limit'] ?? 10);
    if ($limit < 1) $limit = 10;
    if ($limit > 100) $limit = 100;
    
    $sql = "SELECT username, level, space_efficiency, created_at 
            FROM game_highscores 
            ORDER BY level DESC, created_at ASC 
            LIMIT ?";
    
    $stmt = $db_conx->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $scores = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $scores[] = [
            'rank' => $rank++,
            'username' => $row['username'],
            'level' => intval($row['level']),
            'spaceEfficiency' => intval($row['space_efficiency']),
            'date' => date('M j, Y', strtotime($row['created_at']))
        ];
    }
    
    $stmt->close();
    
    // Get total players
    $countResult = mysqli_query($db_conx, "SELECT COUNT(*) as total FROM game_highscores");
    $totalPlayers = mysqli_fetch_assoc($countResult)['total'];
    
    echo json_encode([
        'success' => true,
        'scores' => $scores,
        'totalPlayers' => intval($totalPlayers)
    ]);
}

/**
 * Check if username exists and get their best score
 */
function checkUsername() {
    global $db_conx;
    
    $username = trim($_GET['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['exists' => false]);
        return;
    }
    
    $sql = "SELECT level FROM game_highscores WHERE username = ? ORDER BY level DESC LIMIT 1";
    $stmt = $db_conx->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        echo json_encode([
            'exists' => true,
            'personalBest' => intval($row['level'])
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
}

/**
 * Check if player exists by player_id and get their info
 */
function checkPlayer() {
    global $db_conx;
    
    $playerId = trim($_GET['player_id'] ?? '');
    
    if (empty($playerId)) {
        echo json_encode(['exists' => false]);
        return;
    }
    
    $sql = "SELECT username, level FROM game_highscores WHERE player_id = ? LIMIT 1";
    $stmt = $db_conx->prepare($sql);
    $stmt->bind_param('s', $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        echo json_encode([
            'exists' => true,
            'username' => $row['username'],
            'personalBest' => intval($row['level'])
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
}

/**
 * Update username for a player (updates leaderboard entry)
 */
function updateUsername($input) {
    global $db_conx;
    
    $playerId = trim($input['player_id'] ?? '');
    $username = trim($input['username'] ?? '');
    
    // Validate player_id (UUID format)
    if (empty($playerId) || !preg_match('/^[a-f0-9-]{36}$/i', $playerId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid player ID']);
        return;
    }
    
    // Validate username
    if (empty($username)) {
        echo json_encode(['success' => false, 'error' => 'Username is required']);
        return;
    }
    
    if (strlen($username) > 50) {
        $username = substr($username, 0, 50);
    }
    
    // Sanitize username - alphanumeric, spaces, underscores only
    $username = preg_replace('/[^a-zA-Z0-9 _-]/', '', $username);
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'error' => 'Invalid username']);
        return;
    }
    
    // Update username if player exists
    $sql = "UPDATE game_highscores SET username = ? WHERE player_id = ?";
    $stmt = $db_conx->prepare($sql);
    $stmt->bind_param('ss', $username, $playerId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Username updated']);
        } else {
            // Player doesn't exist yet - that's OK, they'll be added when they submit a score
            echo json_encode(['success' => true, 'message' => 'No existing record to update']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update username']);
    }
    
    $stmt->close();
}
?>

