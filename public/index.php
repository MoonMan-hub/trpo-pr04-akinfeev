<?php
// Единственная страница приложения: сначала PHP-логика, ниже HTML.

// Режим локальной разработки: встроенный сервер PHP (php -S). Только тогда показываем все ошибки.
$isLocal = (PHP_SAPI === 'cli-server');
if ($isLocal) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/../src/functions.php';

date_default_timezone_set('Europe/Moscow');

// ---------- переменные разных типов ----------
$appName = 'Заявки сотрудников';   // строка
$appVersion = '1.0';               // строка
$today = date('d.m.Y');            // текущая дата, строка

// ---------- чтение dataset ----------
$employees = [];
$departments = [];
$loadError = '';
$cases = [];
$casesError = '';

try {
    $employees = loadEmployees(__DIR__ . '/../data/employees.csv');
    $departments = getDepartments($employees);
} catch (RuntimeException $e) {
    $loadError = $e->getMessage();
}

try {
    $cases = loadFormCases(__DIR__ . '/../data/form_cases.json');
} catch (RuntimeException $e) {
    $casesError = $e->getMessage();
}

$employeesCount = count($employees);                    // число
$avgExperience = averageExperience($employees);     // дробное число

// ---------- фильтр (GET-параметр department) ----------
$selectedDepartment = '';
if (isset($_GET['department']) && is_string($_GET['department'])) {
    $selectedDepartment = trim($_GET['department']);
}
$shownEmployees = filterEmployees($employees, $selectedDepartment);

// ---------- форма (POST) ----------
$input = ['name' => '', 'email' => '', 'department' => '', 'experience' => ''];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = readFormInput($_POST);
    $errors = validateInput($input, $departments);
    $success = count($errors) === 0;
}

// ---------- проверка сценариев из form_cases.json ----------
$caseResults = runFormCases($cases, $departments);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header">
    <div class="brand">Практическая работа 4</div>
    <nav class="nav">
        <a href="#employees">Сотрудники</a>
        <a href="#form">Форма</a>
        <a href="#cases">Проверка</a>
    </nav>
    <span class="version">Версия <?= e($appVersion) ?>, <?= e($today) ?></span>
</header>

<main class="wrap">
    <section class="hero">
        <?php if ($isLocal): ?>
            <span class="pill">локальная разработка</span>
        <?php endif; ?>
        <h1><?= e($appName) ?></h1>
        <p class="muted">Таблица сотрудников из employees.csv, фильтр по отделу и форма с серверной проверкой.</p>

        <div class="grid">
            <div class="card">
                <b>Сотрудников</b>
                <span class="big"><?= e($employeesCount) ?></span>
            </div>
            <div class="card">
                <b>Средний стаж, лет</b>
                <span class="big"><?= e(number_format($avgExperience, 1, ',', ' ')) ?></span>
            </div>
            <div class="card">
                <b>Отделы</b>
                <?php if (count($departments) === 0): ?>
                    <span class="muted">нет данных</span>
                <?php else: ?>
                    <?php foreach ($departments as $department): ?>
                        <span class="pill"><?= e($department) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($loadError !== ''): ?>
        <section class="box error">
            <h2>Ошибка данных</h2>
            <p><?= e($loadError) ?></p>
        </section>
    <?php endif; ?>

    <section class="box" id="employees">
        <h2>Сотрудники</h2>

        <form method="get" action="index.php#employees" class="filter-form">
            <label for="department_filter">Отдел:</label>
            <select name="department" id="department_filter">
                <option value="">Все отделы</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e($department) ?>" <?= $department === $selectedDepartment ? 'selected' : '' ?>>
                        <?= e($department) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Показать</button>
            <a href="index.php#employees" class="reset">Сбросить</a>
        </form>

        <?php if (count($shownEmployees) === 0): ?>
            <p class="muted">Сотрудники не найдены.</p>
        <?php else: ?>
            <table class="table">
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Отдел</th>
                    <th>Стаж, лет</th>
                    <th>Email</th>
                </tr>
                <?php foreach ($shownEmployees as $employee): ?>
                    <tr>
                        <td><?= e($employee['id']) ?></td>
                        <td><?= e($employee['name']) ?></td>
                        <td><?= e($employee['department']) ?></td>
                        <td><?= e($employee['experience']) ?></td>
                        <td><?= e($employee['email']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

    <section class="box" id="form">
        <h2>Заявка сотрудника</h2>

        <?php if ($success): ?>
            <div class="success">
                <b>Заявка принята.</b>
                <p>
                    <?= e($input['name']) ?>, <?= e($input['email']) ?>,
                    отдел: <?= e($input['department']) ?>,
                    стаж: <?= e($input['experience']) ?> лет.
                </p>
            </div>
        <?php endif; ?>

        <?php if (count($errors) > 0): ?>
            <div class="error-list">
                <b>Исправьте ошибки:</b>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= e($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php#form" class="form" novalidate>
            <div class="field">
                <label for="name">ФИО</label>
                <input type="text" name="name" id="name" value="<?= e($input['name']) ?>">
                <?php if (isset($errors['name'])): ?>
                    <span class="field-error"><?= e($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="text" name="email" id="email" value="<?= e($input['email']) ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="department">Отдел</label>
                <select name="department" id="department">
                    <option value="">Выберите отдел</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= e($department) ?>" <?= $department === $input['department'] ? 'selected' : '' ?>>
                            <?= e($department) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['department'])): ?>
                    <span class="field-error"><?= e($errors['department']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="experience">Стаж, лет</label>
                <input type="text" name="experience" id="experience" value="<?= e($input['experience']) ?>">
                <?php if (isset($errors['experience'])): ?>
                    <span class="field-error"><?= e($errors['experience']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn">Отправить заявку</button>
        </form>
    </section>

    <section class="box" id="cases">
        <h2>Проверка сценариев из form_cases.json</h2>

        <?php if ($casesError !== ''): ?>
            <p class="error-text"><?= e($casesError) ?></p>
        <?php elseif (count($caseResults) === 0): ?>
            <p class="muted">Сценариев нет.</p>
        <?php else: ?>
            <table class="table">
                <tr>
                    <th>Сценарий</th>
                    <th>Имя</th>
                    <th>Стаж</th>
                    <th>Ожидалось</th>
                    <th>Получено</th>
                    <th>Результат</th>
                </tr>
                <?php foreach ($caseResults as $row): ?>
                    <tr>
                        <td><?= e($row['case']) ?></td>
                        <td><?= $row['name'] === '' ? '(пусто)' : e($row['name']) ?></td>
                        <td><?= e($row['experience']) ?></td>
                        <td><?= $row['expected'] ? 'верно' : 'ошибка' ?></td>
                        <td><?= $row['actual'] ? 'верно' : 'ошибка' ?></td>
                        <td>
                            <?php if ($row['ok']): ?>
                                <span class="status ok">совпало</span>
                            <?php else: ?>
                                <span class="status no">не совпало</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>
</main>

<footer class="footer">
    <p class="muted">Практическая работа №4. Технологии разработки программного обеспечения.</p>
</footer>
</body>
</html>
