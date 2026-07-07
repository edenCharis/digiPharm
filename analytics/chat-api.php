<?php
/**
 * digiMind chat — conversation persistence + LLM bridge.
 * Every action requires a logged-in session; ownership is checked on each row.
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
ai_check_auth();

header('Content-Type: application/json');

$user       = ai_user();
$userId     = (int) $user['id'];
$pharmacyId = (int) $user['pharmacy_id'];
$apiKey     = $user['api_key'];

$db     = analytics_db();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function respond($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function own_conversation(PDO $db, int $id, int $userId): ?array
{
    $st = $db->prepare("SELECT * FROM ai_chat_conversations WHERE id = ? AND user_id = ?");
    $st->execute([$id, $userId]);
    $row = $st->fetch();
    return $row ?: null;
}

function fetch_messages(PDO $db, int $convId): array
{
    $st = $db->prepare("SELECT id, role, content, created_at FROM ai_chat_messages WHERE conversation_id = ? ORDER BY id ASC");
    $st->execute([$convId]);
    return $st->fetchAll();
}

function recent_history(PDO $db, int $convId, int $limit = 8): array
{
    $st = $db->prepare("SELECT role, content FROM ai_chat_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT $limit");
    $st->execute([$convId]);
    return array_reverse($st->fetchAll());
}

/** @return array{0: bool, 1: string} [ok, reply_or_error] */
function call_llm(string $apiKey, string $question, array $history): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "X-API-Key: $apiKey\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => json_encode(['question' => $question, 'history' => $history]),
            'timeout'       => 25,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents('http://127.0.0.1:8000/analytics/chat', false, $ctx);
    if ($body === false) {
        return [false, 'Service AI indisponible'];
    }
    $decoded = json_decode($body, true);
    if (!isset($decoded['reply'])) {
        return [false, $decoded['detail'] ?? ($decoded['error'] ?? 'Réponse invalide du service AI')];
    }
    return [true, $decoded['reply']];
}

switch ($action) {

    case 'list':
        $st = $db->prepare("SELECT id, title, updated_at FROM ai_chat_conversations WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50");
        $st->execute([$userId]);
        respond(['conversations' => $st->fetchAll()]);

    case 'get':
        $id   = (int) ($_GET['id'] ?? 0);
        $conv = own_conversation($db, $id, $userId);
        if (!$conv) respond(['error' => 'Conversation introuvable'], 404);
        respond(['conversation' => $conv, 'messages' => fetch_messages($db, $id)]);

    case 'send':
        if (!$apiKey) respond(['error' => 'Aucune clé API associée à ce compte'], 400);
        $input    = json_decode(file_get_contents('php://input'), true) ?: [];
        $question = trim($input['question'] ?? '');
        $convId   = (int) ($input['conversation_id'] ?? 0);
        if ($question === '') respond(['error' => 'Question vide'], 400);

        if ($convId > 0) {
            if (!own_conversation($db, $convId, $userId)) respond(['error' => 'Conversation introuvable'], 404);
        } else {
            $title = mb_substr($question, 0, 60);
            $st = $db->prepare("INSERT INTO ai_chat_conversations (user_id, pharmacy_id, title) VALUES (?, ?, ?)");
            $st->execute([$userId, $pharmacyId, $title]);
            $convId = (int) $db->lastInsertId();
        }

        $history = recent_history($db, $convId);
        $db->prepare("INSERT INTO ai_chat_messages (conversation_id, role, content) VALUES (?, 'user', ?)")
           ->execute([$convId, $question]);

        [$ok, $result] = call_llm($apiKey, $question, $history);

        if (!$ok) {
            respond(['conversation_id' => $convId, 'messages' => fetch_messages($db, $convId), 'available' => false, 'error' => $result]);
        }

        $db->prepare("INSERT INTO ai_chat_messages (conversation_id, role, content) VALUES (?, 'assistant', ?)")
           ->execute([$convId, $result]);
        $db->prepare("UPDATE ai_chat_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

        respond(['conversation_id' => $convId, 'reply' => $result, 'messages' => fetch_messages($db, $convId), 'available' => true]);

    case 'edit':
        if (!$apiKey) respond(['error' => 'Aucune clé API associée à ce compte'], 400);
        $input     = json_decode(file_get_contents('php://input'), true) ?: [];
        $convId    = (int) ($input['conversation_id'] ?? 0);
        $messageId = (int) ($input['message_id'] ?? 0);
        $content   = trim($input['content'] ?? '');
        if ($content === '') respond(['error' => 'Message vide'], 400);
        if (!own_conversation($db, $convId, $userId)) respond(['error' => 'Conversation introuvable'], 404);

        $st = $db->prepare("SELECT id FROM ai_chat_messages WHERE id = ? AND conversation_id = ? AND role = 'user'");
        $st->execute([$messageId, $convId]);
        if (!$st->fetch()) respond(['error' => 'Message introuvable'], 404);

        // Edit-and-regenerate: drop this message and everything after it, then resend.
        $db->prepare("DELETE FROM ai_chat_messages WHERE conversation_id = ? AND id >= ?")->execute([$convId, $messageId]);

        $history = recent_history($db, $convId);
        $db->prepare("INSERT INTO ai_chat_messages (conversation_id, role, content) VALUES (?, 'user', ?)")
           ->execute([$convId, $content]);

        [$ok, $result] = call_llm($apiKey, $content, $history);

        if (!$ok) {
            respond(['conversation_id' => $convId, 'messages' => fetch_messages($db, $convId), 'available' => false, 'error' => $result]);
        }

        $db->prepare("INSERT INTO ai_chat_messages (conversation_id, role, content) VALUES (?, 'assistant', ?)")
           ->execute([$convId, $result]);
        $db->prepare("UPDATE ai_chat_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

        respond(['conversation_id' => $convId, 'reply' => $result, 'messages' => fetch_messages($db, $convId), 'available' => true]);

    case 'delete':
        $input  = json_decode(file_get_contents('php://input'), true) ?: [];
        $convId = (int) ($input['conversation_id'] ?? 0);
        if (!own_conversation($db, $convId, $userId)) respond(['error' => 'Conversation introuvable'], 404);
        $db->prepare("DELETE FROM ai_chat_conversations WHERE id = ?")->execute([$convId]);
        respond(['ok' => true]);

    default:
        respond(['error' => 'Action inconnue'], 400);
}
