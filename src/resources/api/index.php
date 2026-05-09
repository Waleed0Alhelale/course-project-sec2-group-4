<?php
/**
 * Course Resources API
 * 
 * This is a RESTful API that handles all CRUD operations for course resources 
 * and their associated comments/discussions.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structures (for reference):
 * 
 * Table: resources
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - title (VARCHAR(255), NOT NULL)
 *   - description (TEXT, nullable)
 *   - link (VARCHAR(500), NOT NULL)
 *   - created_at (TIMESTAMP)
 * 
 * Table: comments_resource
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - resource_id (INT UNSIGNED, FOREIGN KEY references resources.id, CASCADE DELETE)
 *   - author (VARCHAR(100), NOT NULL)
 *   - text (TEXT, NOT NULL)
 *   - created_at (TIMESTAMP)
 * 
 * HTTP Methods Supported:
 *   - GET:    Retrieve resource(s) or comment(s)
 *   - POST:   Create a new resource or comment
 *   - PUT:    Update an existing resource
 *   - DELETE: Delete a resource (associated comments in comments_resource are
 *             removed automatically by the ON DELETE CASCADE constraint)
 * 
 * Response Format: JSON
 * All responses follow the structure:
 *   { "success": true,  "data": ...    }  (on success)
 *   { "success": false, "message": ... }  (on error)
 * 
 * API Endpoints:
 * 
 *   Resources:
 *     GET    /resources/api/index.php                         - Get all resources
 *     GET    /resources/api/index.php?id={id}                 - Get single resource by ID
 *     POST   /resources/api/index.php                         - Create new resource
 *     PUT    /resources/api/index.php                         - Update resource
 *     DELETE /resources/api/index.php?id={id}                 - Delete resource
 * 
 *   Comments:
 *     GET    /resources/api/index.php?resource_id={id}&action=comments
 *                                                             - Get all comments for a resource
 *     POST   /resources/api/index.php?action=comment          - Create a new comment
 *     DELETE /resources/api/index.php?comment_id={id}&action=delete_comment
 *                                                             - Delete a single comment
 * 
 * Query Parameters for GET all resources:
 *   - search: Optional. Filter resources by title or description using LIKE.
 *   - sort:   Optional. Sort field — allowed values: title, created_at (default: created_at).
 *   - order:  Optional. Sort direction — allowed values: asc, desc (default: desc).
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the database connection file
require_once './config/Database.php';

// Get the PDO database connection
$database = new Database();
$db = $database->getConnection();

// Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the request body for POST and PUT requests
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Parse query parameters from $_GET
$action     = $_GET['action']      ?? null;
$id         = $_GET['id']          ?? null;
$resourceId = $_GET['resource_id'] ?? null;
$commentId  = $_GET['comment_id']  ?? null;


// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

/**
 * Function: Get all resources
 */
function getAllResources($db) {
    // Base SQL query
    $sql = 'SELECT id, title, description, link, created_at FROM resources';

    // Optional search filter
    $search = $_GET['search'] ?? null;
    if (!empty($search)) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
    }

    // Validate sort parameter
    $allowedSorts = ['title', 'created_at'];
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts)
        ? $_GET['sort']
        : 'created_at';

    // Validate order parameter
    $allowedOrders = ['asc', 'desc'];
    $order = isset($_GET['order']) && in_array(strtolower($_GET['order']), $allowedOrders)
        ? strtolower($_GET['order'])
        : 'desc';

    // Add ORDER BY clause
    $sql .= " ORDER BY $sort $order";

    // Prepare the statement
    $stmt = $db->prepare($sql);

    // Bind search value if used
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }

    // Execute the query
    $stmt->execute();

    // Fetch all results
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response
    sendResponse(['success' => true, 'data' => $resources]);
}


/**
 * Function: Get a single resource by ID
 */
function getResourceById($db, $resourceId) {
    // Validate resourceId
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
        return;
    }

    // Prepare SQL query
    $stmt = $db->prepare('SELECT id, title, description, link, created_at FROM resources WHERE id = ?');
    $stmt->bindValue(1, (int)$resourceId, PDO::PARAM_INT);
    $stmt->execute();

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}


/**
 * Function: Create a new resource
 */
function createResource($db, $data) {
    // Validate required fields
    $validation = validateRequiredFields($data, ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing']) . '.'
        ], 400);
        return;
    }

    // Sanitize input
    $title       = sanitizeInput($data['title']);
    $link        = sanitizeInput($data['link']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';

    // Validate title is not empty after sanitizing
    if ($title === '') {
        sendResponse(['success' => false, 'message' => 'Title cannot be empty.'], 400);
        return;
    }

    // Validate the link URL
    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL provided for link.'], 400);
        return;
    }

    // Prepare INSERT query
    $stmt = $db->prepare('INSERT INTO resources (title, description, link) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $title);
    $stmt->bindValue(2, $description);
    $stmt->bindValue(3, $link);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id'      => $db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create resource.'], 500);
    }
}


/**
 * Function: Update an existing resource
 */
function updateResource($db, $data) {
    // Validate that id is provided
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
        return;
    }

    $resourceId = (int)$data['id'];

    // Check if the resource exists
    $checkStmt = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $checkStmt->bindValue(1, $resourceId, PDO::PARAM_INT);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }

    // Build UPDATE query dynamically for only the fields provided
    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $title = sanitizeInput($data['title']);
        if ($title === '') {
            sendResponse(['success' => false, 'message' => 'Title cannot be empty.'], 400);
            return;
        }
        $fields[] = 'title = ?';
        $values[] = $title;
    }

    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($data['description']);
    }

    if (isset($data['link'])) {
        $link = sanitizeInput($data['link']);
        if (!validateUrl($link)) {
            sendResponse(['success' => false, 'message' => 'Invalid URL provided for link.'], 400);
            return;
        }
        $fields[] = 'link = ?';
        $values[] = $link;
    }

    // If no fields to update
    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields provided to update.'], 400);
        return;
    }

    // Build final SQL
    $sql = 'UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $values[] = $resourceId;

    $stmt = $db->prepare($sql);
    foreach ($values as $i => $value) {
        $stmt->bindValue($i + 1, $value);
    }
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
    } else {
        // rowCount() can be 0 if no values actually changed — still a success
        sendResponse(['success' => true, 'message' => 'Resource updated successfully (no changes detected).']);
    }
}


/**
 * Function: Delete a resource
 */
function deleteResource($db, $resourceId) {
    // Validate resourceId
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
        return;
    }

    $resourceId = (int)$resourceId;

    // Check if the resource exists
    $checkStmt = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $checkStmt->bindValue(1, $resourceId, PDO::PARAM_INT);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }

    // Prepare DELETE query
    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->bindValue(1, $resourceId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete resource.'], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

/**
 * Function: Get all comments for a specific resource
 */
function getCommentsByResourceId($db, $resourceId) {
    // Validate resourceId
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
        return;
    }

    // Prepare SQL query
    $stmt = $db->prepare(
        'SELECT id, resource_id, author, text, created_at
         FROM comments_resource
         WHERE resource_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->bindValue(1, (int)$resourceId, PDO::PARAM_INT);
    $stmt->execute();

    // Fetch all results
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return success response — always an array, even if empty
    sendResponse(['success' => true, 'data' => $comments]);
}


/**
 * Function: Create a new comment
 */
function createComment($db, $data) {
    // Validate required fields
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing']) . '.'
        ], 400);
        return;
    }

    // Validate that resource_id is numeric
    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'resource_id must be numeric.'], 400);
        return;
    }

    $resourceId = (int)$data['resource_id'];

    // Check that the resource exists
    $checkStmt = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $checkStmt->bindValue(1, $resourceId, PDO::PARAM_INT);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }

    // Sanitize author and text
    $author = sanitizeInput($data['author']);
    $text   = sanitizeInput($data['text']);

    if ($author === '' || $text === '') {
        sendResponse(['success' => false, 'message' => 'Author and text cannot be empty.'], 400);
        return;
    }

    // Prepare INSERT query
    $stmt = $db->prepare(
        'INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)'
    );
    $stmt->bindValue(1, $resourceId, PDO::PARAM_INT);
    $stmt->bindValue(2, $author);
    $stmt->bindValue(3, $text);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id'      => $db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}


/**
 * Function: Delete a comment
 */
function deleteComment($db, $commentId) {
    // Validate commentId
    if (empty($commentId) || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing comment ID.'], 400);
        return;
    }

    $commentId = (int)$commentId;

    // Check if the comment exists
    $checkStmt = $db->prepare('SELECT id FROM comments_resource WHERE id = ?');
    $checkStmt->bindValue(1, $commentId, PDO::PARAM_INT);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
        return;
    }

    // Prepare DELETE query
    $stmt = $db->prepare('DELETE FROM comments_resource WHERE id = ?');
    $stmt->bindValue(1, $commentId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {

        if ($action === 'comments') {
            // Get all comments for a resource
            getCommentsByResourceId($db, $resourceId);

        } elseif (!empty($id)) {
            // Get single resource by ID
            getResourceById($db, $id);

        } else {
            // Get all resources (supports ?search=, ?sort=, ?order=)
            getAllResources($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            // Create a new comment
            createComment($db, $data);
        } else {
            // Create a new resource
            createResource($db, $data);
        }

    } elseif ($method === 'PUT') {

        // Update an existing resource
        updateResource($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            // Delete a single comment
            deleteComment($db, $commentId);
        } else {
            // Delete a resource
            deleteResource($db, $id);
        }

    } else {
        // Unsupported HTTP method
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    error_log('PDOException: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred. Please try again later.'], 500);

} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Helper: Send a JSON response and stop execution.
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);

    if (!is_array($data)) {
        $data = ['data' => $data];
    }

    echo json_encode($data);
    exit;
}


/**
 * Helper: Validate a URL string.
 */
function validateUrl($url) {
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
}


/**
 * Helper: Sanitize a single input string.
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}


/**
 * Helper: Check that all required fields exist and are non-empty in $data.
 */
function validateRequiredFields($data, $requiredFields) {
    $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $missing[] = $field;
        }
    }

    return [
        'valid'   => count($missing) === 0,
        'missing' => $missing,
    ];
}
?>
