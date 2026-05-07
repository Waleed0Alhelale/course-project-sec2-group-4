<?php
/**
 * Weekly Course Breakdown API
 *
 * RESTful API for CRUD operations on weekly course content and discussion
 * comments. Uses PDO to interact with the MySQL database defined in
 * schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: weeks
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   start_date  DATE          NOT NULL
 *   description TEXT
 *   links       TEXT          — JSON-encoded array of URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP
 *
 * Table: comments_week
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   week_id     INT UNSIGNED  NOT NULL   — FK → weeks.id (ON DELETE CASCADE)
 *   author      VARCHAR(100)  NOT NULL
 *   text        TEXT          NOT NULL
 *   created_at  TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve week(s) or comments
 *   POST   — Create a new week or comment
 *   PUT    — Update an existing week
 *   DELETE — Delete a week (cascade removes its comments) or a single comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Weeks:
 *     GET    ./api/index.php                  — list all weeks
 *     GET    ./api/index.php?id={id}           — get one week by integer id
 *     POST   ./api/index.php                  — create a new week
 *     PUT    ./api/index.php                  — update a week (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a week
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&week_id={id}
 *                                             — list comments for a week
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all weeks:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, start_date (default: start_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(['success' => true, 'message' => 'Preflight OK']);
}

require_once __DIR__ . '/../../common/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data    = !empty($rawData) ? json_decode($rawData, true) : [];

$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;

$db = getDBConnection();


// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

/**
 * Get all weeks (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 *
 * Query parameters handled inside:
 *   search — filter by title LIKE or description LIKE
 *   sort   — allowed: title, start_date   (default: start_date)
 *   order  — allowed: asc, desc           (default: asc)
 *
 * Each week row in the response has links decoded from its JSON string
 * to a PHP array before encoding the final JSON output.
 */
function getAllWeeks(PDO $db): void
{
    $query = 'SELECT id, title, start_date, description, links, created_at FROM weeks';
    $params = [];

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $query .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSorts = ['title', 'start_date'];
    $sort = $_GET['sort'] ?? 'start_date';
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'start_date';
    }

    $order = strtolower($_GET['order'] ?? 'asc');
    if ($order !== 'asc' && $order !== 'desc') {
        $order = 'asc';
    }

    $query .= ' ORDER BY ' . $sort . ' ' . $order;

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $weeks = $stmt->fetchAll();

    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'] ?? '[]', true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}


/**
 * Get a single week by its integer primary key.
 * Method: GET with ?id={id}.
 *
 * Response (found):
 *   { "success": true, "data": { id, title, start_date, description,
 *                                 links, created_at } }
 * Response (not found): HTTP 404.
 */
function getWeekById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?');
    $stmt->execute([$id]);

    $week = $stmt->fetch();

    if (!$week) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $week['links'] = json_decode($week['links'] ?? '[]', true) ?? [];

    sendResponse(['success' => true, 'data' => $week]);
}


/**
 * Create a new week.
 * Method: POST (no ?action parameter).
 *
 * Required JSON body fields:
 *   title       — string (required)
 *   start_date  — string "YYYY-MM-DD" (required)
 *   description — string (optional, defaults to "")
 *   links       — array of URL strings (optional, defaults to [])
 *
 * Response (success): HTTP 201 — { success, message, id }
 * Response (invalid start_date): HTTP 400.
 */
function createWeek(PDO $db, array $data): void
{
    $title = trim($data['title'] ?? '');
    $start_date = trim($data['start_date'] ?? '');
    $description = trim($data['description'] ?? '');
    $links = $data['links'] ?? [];

    if (!$title || !$start_date) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required.'], 400);
    }

    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD.'], 400);
    }

    $linksJson = is_array($links) ? json_encode($links) : json_encode([]);

    $stmt = $db->prepare('INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)');
    $stmt->execute([$title, $start_date, $description, $linksJson]);

    if ($stmt->rowCount() > 0) {
        $id = $db->lastInsertId();
        sendResponse(
            ['success' => true, 'message' => 'Week created.', 'id' => $id],
            201
        );
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create week.'], 500);
    }
}


/**
 * Update an existing week.
 * Method: PUT.
 *
 * Required JSON body:
 *   id — integer primary key of the week to update (required).
 * Optional JSON body fields (at least one must be present):
 *   title, start_date, description, links.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 * Response (invalid start_date): HTTP 400.
 */
function updateWeek(PDO $db, array $data): void
{
    $id = $data['id'] ?? null;

    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid week id.'], 400);
    }

    // Check week exists
    $checkStmt = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $updates = [];
    $params = [];

    if (array_key_exists('title', $data)) {
        $updates[] = 'title = ?';
        $params[] = trim($data['title'] ?? '');
    }

    if (array_key_exists('start_date', $data)) {
        $start_date = trim($data['start_date'] ?? '');
        if (!validateDate($start_date)) {
            sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD.'], 400);
        }
        $updates[] = 'start_date = ?';
        $params[] = $start_date;
    }

    if (array_key_exists('description', $data)) {
        $updates[] = 'description = ?';
        $params[] = trim($data['description'] ?? '');
    }

    if (array_key_exists('links', $data)) {
        $linksJson = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
        $updates[] = 'links = ?';
        $params[] = $linksJson;
    }

    if (empty($updates)) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }

    $params[] = $id;

    $query = 'UPDATE weeks SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week updated.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update week.'], 500);
    }
}


/**
 * Delete a week by integer id.
 * Method: DELETE with ?id={id}.
 *
 * The ON DELETE CASCADE constraint on comments_week.week_id
 * automatically removes all comments for this week — no manual
 * deletion of comments is needed.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteWeek(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week id.'], 400);
    }

    // Check week exists
    $checkStmt = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM weeks WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete week.'], 500);
    }
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific week.
 * Method: GET with ?action=comments&week_id={id}.
 *
 * Reads from the comments_week table.
 * Returns an empty data array if no comments exist — not an error.
 *
 * Each comment object: { id, week_id, author, text, created_at }
 */
function getCommentsByWeek(PDO $db, $weekId): void
{
    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing week_id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC');
    $stmt->execute([$weekId]);

    $comments = $stmt->fetchAll();

    sendResponse(['success' => true, 'data' => $comments]);
}


/**
 * Create a new comment.
 * Method: POST with ?action=comment.
 *
 * Required JSON body:
 *   week_id — integer FK into weeks.id (required)
 *   author  — string (required)
 *   text    — string (required, must be non-empty after trim)
 *
 * Response (success): HTTP 201 — { success, message, id, data: comment }
 * Response (week not found): HTTP 404.
 * Response (missing fields): HTTP 400.
 */
function createComment(PDO $db, array $data): void
{
    $weekId = $data['week_id'] ?? null;
    $author = trim($data['author'] ?? '');
    $text = trim($data['text'] ?? '');

    if (!$weekId || !$author || !$text) {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required.'], 400);
    }

    if (!is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid week_id.'], 400);
    }

    // Check week exists
    $checkStmt = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $checkStmt->execute([$weekId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare('INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $id = $db->lastInsertId();
        $comment = [
            'id' => $id,
            'week_id' => $weekId,
            'author' => $author,
            'text' => $text,
            'created_at' => date('Y-m-d H:i:s')
        ];
        sendResponse(
            ['success' => true, 'message' => 'Comment created.', 'id' => $id, 'data' => $comment],
            201
        );
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}


/**
 * Delete a single comment.
 * Method: DELETE with ?action=delete_comment&comment_id={id}.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing comment_id.'], 400);
    }

    // Check comment exists
    $checkStmt = $db->prepare('SELECT id FROM comments_week WHERE id = ?');
    $checkStmt->execute([$commentId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_week WHERE id = ?');
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        // ?action=comments&week_id={id} → list comments for a week
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        }
        // ?id={id} → single week
        elseif ($id !== null) {
            getWeekById($db, $id);
        }
        // no parameters → all weeks (supports ?search, ?sort, ?order)
        else {
            getAllWeeks($db);
        }

    } elseif ($method === 'POST') {

        // ?action=comment → create a comment in comments_week
        if ($action === 'comment') {
            createComment($db, $data);
        }
        // no action → create a new week
        else {
            createWeek($db, $data);
        }

    } elseif ($method === 'PUT') {

        // Update a week; id comes from the JSON body
        updateWeek($db, $data);

    } elseif ($method === 'DELETE') {

        // ?action=delete_comment&comment_id={id} → delete one comment
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        }
        // ?id={id} → delete a week (and its comments via CASCADE)
        else {
            deleteWeek($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error. Please try again later.'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error. Please try again later.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}


/**
 * Validate a date string against the "YYYY-MM-DD" format.
 *
 * @param  string $date
 * @return bool  True if valid, false otherwise.
 */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}


/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
