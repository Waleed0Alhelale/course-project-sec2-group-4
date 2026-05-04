<?php
/**
 * Assignment Management API
 *
 * JSON API for course assignments and assignment comments.
 */

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
$data = [];

if ($rawData !== '') {
    $decoded = json_decode($rawData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(['success' => false, 'message' => 'Invalid JSON request body.'], 400);
    }

    $data = is_array($decoded) ? $decoded : [];
}

$action = $_GET['action'] ?? ($data['action'] ?? null);
$id = $_GET['id'] ?? ($data['id'] ?? null);
$assignmentId = $_GET['assignment_id'] ?? ($data['assignment_id'] ?? null);
$commentId = $_GET['comment_id'] ?? ($data['comment_id'] ?? null);

try {
    $db = getDBConnection();

    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
        } elseif ($id !== null) {
            getAssignmentById($db, $id);
        } else {
            getAllAssignments($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment' || $action === 'add_comment') {
            createComment($db, $data);
        } elseif ($action === 'update' || ($action === null && array_key_exists('id', $data))) {
            updateAssignment($db, $data);
        } elseif ($action === 'delete') {
            deleteAssignment($db, $id);
        } elseif ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            createAssignment($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateAssignment($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteAssignment($db, $id);
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

function getAllAssignments(PDO $db): void
{
    $query = 'SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments';
    $params = [];

    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $query .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSorts = ['title', 'due_date', 'created_at'];
    $sort = $_GET['sort'] ?? 'due_date';

    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'due_date';
    }

    $order = strtolower($_GET['order'] ?? 'asc');

    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'asc';
    }

    $query .= ' ORDER BY ' . $sort . ' ' . strtoupper($order);

    $statement = $db->prepare($query);
    $statement->execute($params);

    $assignments = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as &$assignment) {
        $assignment['files'] = decodeFiles($assignment['files'] ?? null);
    }

    sendResponse(['success' => true, 'data' => $assignments]);
}

function getAssignmentById(PDO $db, $id): void
{
    $assignmentId = getPositiveInteger($id);

    if ($assignmentId === null) {
        sendResponse(['success' => false, 'message' => 'A valid assignment id is required.'], 400);
    }

    $statement = $db->prepare(
        'SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments WHERE id = ?'
    );
    $statement->execute([$assignmentId]);
    $assignment = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $assignment['files'] = decodeFiles($assignment['files'] ?? null);
    $assignment['comments'] = fetchComments($db, $assignmentId);

    sendResponse(['success' => true, 'data' => $assignment]);
}

function createAssignment(PDO $db, array $data): void
{
    $title = sanitizeInput($data['title'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $dueDate = getPlainString($data['due_date'] ?? '');
    $files = encodeFiles($data['files'] ?? []);

    if ($title === '' || $description === '' || $dueDate === '') {
        sendResponse(['success' => false, 'message' => 'Title, description, and due date are required.'], 400);
    }

    if (!validateDate($dueDate)) {
        sendResponse(['success' => false, 'message' => 'Due date must use YYYY-MM-DD format.'], 400);
    }

    $statement = $db->prepare(
        'INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$title, $description, $dueDate, $files]);

    $newId = (int) $db->lastInsertId();
    $assignment = fetchAssignment($db, $newId);

    sendResponse([
        'success' => true,
        'message' => 'Assignment created.',
        'id' => $newId,
        'data' => $assignment
    ], 201);
}

function updateAssignment(PDO $db, array $data): void
{
    $id = getPositiveInteger($data['id'] ?? null);

    if ($id === null) {
        sendResponse(['success' => false, 'message' => 'A valid assignment id is required.'], 400);
    }

    if (!assignmentExists($db, $id)) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $clauses = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        $title = sanitizeInput($data['title']);

        if ($title === '') {
            sendResponse(['success' => false, 'message' => 'Title cannot be empty.'], 400);
        }

        $clauses[] = 'title = ?';
        $values[] = $title;
    }

    if (array_key_exists('description', $data)) {
        $description = sanitizeInput($data['description']);

        if ($description === '') {
            sendResponse(['success' => false, 'message' => 'Description cannot be empty.'], 400);
        }

        $clauses[] = 'description = ?';
        $values[] = $description;
    }

    if (array_key_exists('due_date', $data)) {
        $dueDate = getPlainString($data['due_date']);

        if (!validateDate($dueDate)) {
            sendResponse(['success' => false, 'message' => 'Due date must use YYYY-MM-DD format.'], 400);
        }

        $clauses[] = 'due_date = ?';
        $values[] = $dueDate;
    }

    if (array_key_exists('files', $data)) {
        $clauses[] = 'files = ?';
        $values[] = encodeFiles($data['files']);
    }

    if (count($clauses) === 0) {
        sendResponse(['success' => false, 'message' => 'No assignment fields were provided.'], 400);
    }

    $values[] = $id;
    $statement = $db->prepare('UPDATE assignments SET ' . implode(', ', $clauses) . ' WHERE id = ?');
    $statement->execute($values);

    sendResponse([
        'success' => true,
        'message' => 'Assignment updated.',
        'data' => fetchAssignment($db, $id)
    ]);
}

function deleteAssignment(PDO $db, $id): void
{
    $assignmentId = getPositiveInteger($id);

    if ($assignmentId === null) {
        sendResponse(['success' => false, 'message' => 'A valid assignment id is required.'], 400);
    }

    if (!assignmentExists($db, $assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $statement = $db->prepare('DELETE FROM assignments WHERE id = ?');
    $statement->execute([$assignmentId]);

    sendResponse(['success' => true, 'message' => 'Assignment deleted.']);
}

function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    $id = getPositiveInteger($assignmentId);

    if ($id === null) {
        sendResponse(['success' => false, 'message' => 'A valid assignment id is required.'], 400);
    }

    sendResponse(['success' => true, 'data' => fetchComments($db, $id)]);
}

function createComment(PDO $db, array $data): void
{
    $assignmentId = getPositiveInteger($data['assignment_id'] ?? null);
    $author = sanitizeInput($data['author'] ?? '');
    $text = sanitizeInput($data['text'] ?? '');

    if ($assignmentId === null || $author === '' || $text === '') {
        sendResponse(['success' => false, 'message' => 'Assignment id, author, and comment text are required.'], 400);
    }

    if (!assignmentExists($db, $assignmentId)) {
        sendResponse(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    $statement = $db->prepare(
        'INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)'
    );
    $statement->execute([$assignmentId, $author, $text]);

    $commentId = (int) $db->lastInsertId();
    $comment = fetchComment($db, $commentId);

    sendResponse([
        'success' => true,
        'message' => 'Comment added.',
        'id' => $commentId,
        'data' => $comment
    ], 201);
}

function deleteComment(PDO $db, $commentId): void
{
    $id = getPositiveInteger($commentId);

    if ($id === null) {
        sendResponse(['success' => false, 'message' => 'A valid comment id is required.'], 400);
    }

    if (!commentExists($db, $id)) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $statement = $db->prepare('DELETE FROM comments_assignment WHERE id = ?');
    $statement->execute([$id]);

    sendResponse(['success' => true, 'message' => 'Comment deleted.']);
}

function fetchAssignment(PDO $db, int $id): ?array
{
    $statement = $db->prepare(
        'SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments WHERE id = ?'
    );
    $statement->execute([$id]);
    $assignment = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        return null;
    }

    $assignment['files'] = decodeFiles($assignment['files'] ?? null);
    return $assignment;
}

function fetchComments(PDO $db, int $assignmentId): array
{
    $statement = $db->prepare(
        'SELECT id, assignment_id, author, text, created_at
         FROM comments_assignment
         WHERE assignment_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $statement->execute([$assignmentId]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function fetchComment(PDO $db, int $commentId): ?array
{
    $statement = $db->prepare(
        'SELECT id, assignment_id, author, text, created_at FROM comments_assignment WHERE id = ?'
    );
    $statement->execute([$commentId]);
    $comment = $statement->fetch(PDO::FETCH_ASSOC);

    return $comment ?: null;
}

function assignmentExists(PDO $db, int $id): bool
{
    $statement = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $statement->execute([$id]);

    return (bool) $statement->fetch(PDO::FETCH_ASSOC);
}

function commentExists(PDO $db, int $id): bool
{
    $statement = $db->prepare('SELECT id FROM comments_assignment WHERE id = ?');
    $statement->execute([$id]);

    return (bool) $statement->fetch(PDO::FETCH_ASSOC);
}

function decodeFiles($files): array
{
    if (is_array($files)) {
        return normalizeFiles($files);
    }

    if (!is_string($files) || trim($files) === '') {
        return [];
    }

    $decoded = json_decode($files, true);

    return is_array($decoded) ? normalizeFiles($decoded) : [];
}

function encodeFiles($files): string
{
    return json_encode(normalizeFiles($files), JSON_UNESCAPED_SLASHES);
}

function normalizeFiles($files): array
{
    if (!is_array($files)) {
        return [];
    }

    $cleanFiles = [];

    foreach ($files as $file) {
        if (!is_scalar($file)) {
            continue;
        }

        $url = trim(strip_tags((string) $file));

        if ($url !== '') {
            $cleanFiles[] = $url;
        }
    }

    return array_values($cleanFiles);
}

function getPositiveInteger($value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
        return (int) $value;
    }

    return null;
}

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);
    return $dateObject && $dateObject->format('Y-m-d') === $date;
}

function getPlainString($data): string
{
    if (is_array($data) || is_object($data)) {
        return '';
    }

    return trim((string) $data);
}

function sanitizeInput($data): string
{
    return strip_tags(getPlainString($data));
}
