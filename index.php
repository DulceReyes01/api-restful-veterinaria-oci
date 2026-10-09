<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$host = "localhost";
$db   = "veterinaria_db";
$user = "app_webuser";
$pass = "WebVetPass_2026!";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error de conexión a la base de datos: " . $e->getMessage()]);
    exit();
}

// Parsear ID de la URL
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$uri = trim($uri, "/");
$parts = explode("/", $uri);
$id = null;

if (isset($parts[3]) && is_numeric($parts[3])) {
    $id = intval($parts[3]);
}

$method = $_SERVER["REQUEST_METHOD"];

switch ($method) {
    // 1. GET /api/v1/consultas o GET /api/v1/consultas/{id}
    case "GET":
        if ($id) {
            $stmt = $pdo->prepare("SELECT id_consulta, id_mascota, motivo, diagnostico, costo_consulta, estado FROM consulta WHERE id_consulta = ?");
            $stmt->execute([$id]);
            $consulta = $stmt->fetch();
            if ($consulta) {
                http_response_code(200);
                echo json_encode(["status" => "success", "data" => $consulta]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Consulta con ID $id no encontrada"]);
            }
        } else {
            $stmt = $pdo->query("SELECT id_consulta, id_mascota, motivo, diagnostico, costo_consulta, estado FROM consulta ORDER BY id_consulta DESC");
            $consultas = $stmt->fetchAll();
            http_response_code(200);
            echo json_encode(["status" => "success", "count" => count($consultas), "data" => $consultas]);
        }
        break;

    // 2. POST /api/v1/consultas (Crear nuevo)
    case "POST":
        $body = json_decode(file_get_contents("php://input"), true);
        if (!$body || !isset($body["id_mascota"]) || !isset($body["motivo"])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Datos incompletos. Se requiere id_mascota y motivo."]);
            exit();
        }

        $id_mascota  = intval($body["id_mascota"]);
        $motivo      = trim($body["motivo"]);
        $diagnostico = isset($body["diagnostico"]) ? trim($body["diagnostico"]) : "Sin diagnóstico inicial";
        $costo       = isset($body["costo_consulta"]) ? floatval($body["costo_consulta"]) : 350.00;
        $estado      = isset($body["estado"]) ? trim($body["estado"]) : "Programada";

        $sql = "INSERT INTO consulta (id_mascota, motivo, diagnostico, costo_consulta, estado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$id_mascota, $motivo, $diagnostico, $costo, $estado])) {
            $nuevo_id = $pdo->lastInsertId();
            http_response_code(201);
            echo json_encode([
                "status"  => "success",
                "message" => "Registro creado exitosamente",
                "id"      => intval($nuevo_id)
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "No se pudo crear el registro."]);
        }
        break;

    // 3. PUT /api/v1/consultas/{id} (Actualizar existente)
    case "PUT":
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID no especificado en la URL."]);
            exit();
        }

        $check = $pdo->prepare("SELECT id_consulta FROM consulta WHERE id_consulta = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Consulta con ID $id no encontrada para actualizar."]);
            exit();
        }

        $body = json_decode(file_get_contents("php://input"), true);
        if (!$body) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Cuerpo de la petición JSON inválido."]);
            exit();
        }

        $motivo      = $body["motivo"] ?? null;
        $diagnostico = $body["diagnostico"] ?? null;
        $costo       = isset($body["costo_consulta"]) ? floatval($body["costo_consulta"]) : null;
        $estado      = $body["estado"] ?? null;

        $fields = [];
        $params = [];
        if ($motivo !== null) { $fields[] = "motivo = ?"; $params[] = $motivo; }
        if ($diagnostico !== null) { $fields[] = "diagnostico = ?"; $params[] = $diagnostico; }
        if ($costo !== null) { $fields[] = "costo_consulta = ?"; $params[] = $costo; }
        if ($estado !== null) { $fields[] = "estado = ?"; $params[] = $estado; }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "No se enviaron campos válidos para actualizar."]);
            exit();
        }

        $params[] = $id;
        $sql = "UPDATE consulta SET " . implode(", ", $fields) . " WHERE id_consulta = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Registro actualizado correctamente"
        ]);
        break;

    // 4. DELETE /api/v1/consultas/{id} (Eliminar)
    case "DELETE":
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID no especificado en la URL."]);
            exit();
        }

        $check = $pdo->prepare("SELECT id_consulta FROM consulta WHERE id_consulta = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Consulta con ID $id no encontrada para eliminar."]);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM consulta WHERE id_consulta = ?");
        $stmt->execute([$id]);

        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Registro eliminado exitosamente",
            "id"      => $id
        ]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Método HTTP no permitido."]);
        break;
}
