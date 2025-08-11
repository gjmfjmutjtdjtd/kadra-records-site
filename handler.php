<?php
/* Файл: handler.php
   Обработчик формы для KADRA Records.
   - Принимает POST-запрос
   - Валидирует поля
   - Записывает в leads.csv (с блокировкой flock)
   - Возвращает JSON { success: true } или { success:false, error: "..." }
   - Дополнительно: можно раскомментировать отправку email
*/

/* Настройки */
$adminEmail = 'info@kadra-records.ru'; // при желании менять
$leadsFile = __DIR__ . '/leads.csv';
$maxLengths = [
    'name' => 150,
    'phone' => 50,
    'email' => 150,
    'city' => 80,
    'service' => 150,
    'budget' => 80,
    'message' => 2000,
    'website' => 200
];

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Только POST-запросы допускаются.']);
    exit;
}

// Honeypot
$website = isset($_POST['website']) ? trim($_POST['website']) : '';
if (!empty($website)) {
    // Бот
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => 'Spam detected.']);
    exit;
}

// Helper: sanitize
function get_post($key, $default = '') {
    if (!isset($_POST[$key])) return $default;
    $v = $_POST[$key];
    // strip tags but allow basic punctuation
    return trim(strip_tags($v));
}

// Получаем и валидируем поля
$name = substr(get_post('name'), 0, $maxLengths['name']);
$phone = substr(get_post('phone'), 0, $maxLengths['phone']);
$email = substr(get_post('email'), 0, $maxLengths['email']);
$city = substr(get_post('city'), 0, $maxLengths['city']);
$service = substr(get_post('service'), 0, $maxLengths['service']);
$budget = substr(get_post('budget'), 0, $maxLengths['budget']);
$message = substr(get_post('message'), 0, $maxLengths['message']);
$consent = isset($_POST['consent']) ? 'yes' : 'no';

if (empty($name) || empty($phone) || $consent !== 'yes') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Пожалуйста, заполните обязательные поля и подтвердите согласие.']);
    exit;
}

// Доп. валидация email (если есть)
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Неверный формат email.']);
    exit;
}

// Собираем мета-данные
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$utm_source = substr(get_post('utm_source'), 0, 200);
$utm_medium = substr(get_post('utm_medium'), 0, 200);
$utm_campaign = substr(get_post('utm_campaign'), 0, 200);
$timestamp = date('Y-m-d H:i:s');

// Запись в CSV
$columns = [$timestamp, $name, $phone, $email, $city, $service, $budget, $message, $consent, $ip, $ua, $utm_source, $utm_medium, $utm_campaign];

$fp = @fopen($leadsFile, 'a+');
if (!$fp) {
    // попытка создать файл с заголовком
    $created = @file_put_contents($leadsFile, "timestamp,name,phone,email,city,service,budget,message,consent,ip,user_agent,utm_source,utm_medium,utm_campaign\n", FILE_APPEND | LOCK_EX);
    $fp = @fopen($leadsFile, 'a+');
    if (!$fp) {
        echo json_encode(['success' => false, 'error' => 'Не удалось открыть файл for leads.csv. Проверьте права на запись.']);
        exit;
    }
}

if (flock($fp, LOCK_EX)) {
    // Если файл пустой — добавить заголовок
    $stat = fstat($fp);
    if ($stat['size'] == 0) {
        fputcsv($fp, ['timestamp','name','phone','email','city','service','budget','message','consent','ip','user_agent','utm_source','utm_medium','utm_campaign']);
    }
    fputcsv($fp, $columns);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
} else {
    fclose($fp);
    echo json_encode(['success' => false, 'error' => 'Не удалось получить блокировку файла. Попробуйте позже.']);
    exit;
}

// Опция: отправить уведомление на email (раскомментировать при настройке почты на сервере)
// $subject = "Новая заявка с сайта KADRA Records";
// $body = "Новая заявка:\n\n" .
//         "Время: $timestamp\n" .
//         "Имя: $name\n" .
//         "Телефон: $phone\n" .
//         "Email: $email\n" .
//         "Город: $city\n" .
//         "Услуга: $service\n" .
//         "Бюджет: $budget\n" .
//         "Комментарий: $message\n\n" .
//         "IP: $ip\nUA: $ua\nUTM: $utm_source / $utm_medium / $utm_campaign\n";
// @mail($adminEmail, $subject, $body);

// Возврат JSON
echo json_encode(['success' => true]);
exit;
?>
