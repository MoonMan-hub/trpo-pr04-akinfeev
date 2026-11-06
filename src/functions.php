<?php

// Функции учебного приложения. Подключаются в public/index.php через require_once.

// ---------- вывод ----------

// Экранирование значения перед выводом в HTML
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------- работа с данными ----------

// Преобразует одну строку CSV (массив с ключами заголовка) в удобную структуру
function makeEmployee(array $row): array
{
    return [
        'id'         => (int)($row['id'] ?? 0),
        'name'       => trim($row['name'] ?? ''),
        'department' => trim($row['department'] ?? ''),
        'experience' => (int)($row['experience'] ?? 0),
        'email'      => trim($row['email'] ?? ''),
    ];
}

// Читает employees.csv через fgetcsv и возвращает массив сотрудников.
// Если файла нет, бросает RuntimeException.
function loadEmployees(string $file): array
{
    if (!file_exists($file)) {
        throw new RuntimeException('Файл не найден: ' . basename($file));
    }

    $handle = @fopen($file, 'r');
    if ($handle === false) {
        throw new RuntimeException('Не удалось открыть файл: ' . basename($file));
    }

    // Первая строка - заголовок. В начале файла может быть невидимый BOM, убираем его.
    $header = fgetcsv($handle, 0, ';', '"', '');
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('Файл пустой: ' . basename($file));
    }
    $header[0] = str_replace("\xEF\xBB\xBF", '', $header[0]);

    $employees = [];
    while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
        if (count($row) !== count($header)) {
            continue; // пустая или повреждённая строка
        }
        $employees[] = makeEmployee(array_combine($header, $row));
    }

    fclose($handle);
    return $employees;
}

// Читает form_cases.json (сценарии для проверки формы)
function loadFormCases(string $file): array
{
    if (!file_exists($file)) {
        throw new RuntimeException('Файл не найден: ' . basename($file));
    }

    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) {
        throw new RuntimeException('Файл повреждён (неверный JSON): ' . basename($file));
    }

    return $data;
}

// Список уникальных отделов (для фильтра и поля формы)
function getDepartments(array $employees): array
{
    $departments = array_unique(array_column($employees, 'department'));
    sort($departments);
    return $departments;
}

// Фильтр по отделу. Пустая строка значит "показать всех".
function filterEmployees(array $employees, string $department): array
{
    if ($department === '') {
        return $employees;
    }

    $result = [];
    foreach ($employees as $employee) {
        if ($employee['department'] === $department) {
            $result[] = $employee;
        }
    }
    return $result;
}

// Средний стаж (для пустого списка 0)
function averageExperience(array $employees): float
{
    if (count($employees) === 0) {
        return 0;
    }

    $sum = 0;
    foreach ($employees as $employee) {
        $sum += $employee['experience'];
    }
    return $sum / count($employees);
}

// ---------- форма ----------

// Читает поля формы из $_POST (оператор ??) и убирает пробелы по краям (trim)
function readFormInput(array $post): array
{
    $input = [];

    foreach (['name', 'email', 'department', 'experience'] as $field) {
        $value = $post[$field] ?? '';
        $input[$field] = is_string($value) ? trim($value) : '';
    }

    return $input;
}

// Длина строки в символах (работает и с русскими буквами)
function textLength(string $text): int
{
    $length = preg_match_all('/./us', $text);
    return $length === false ? strlen($text) : $length;
}

// Серверная валидация. Возвращает массив ошибок [поле => сообщение].
// Пустой массив значит, что данные корректны.
function validateInput(array $input, array $departments): array
{
    $errors = [];

    // ФИО: обязательное, от 2 до 100 символов
    $name = $input['name'] ?? '';
    if ($name === '') {
        $errors['name'] = 'Введите ФИО';
    } elseif (textLength($name) < 2 || textLength($name) > 100) {
        $errors['name'] = 'ФИО должно быть от 2 до 100 символов';
    }

    // Email: обязательный, корректный формат
    $email = $input['email'] ?? '';
    if ($email === '') {
        $errors['email'] = 'Введите email';
    } elseif (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email';
    }

    // Отдел: должен быть из списка
    $department = $input['department'] ?? '';
    if ($department === '') {
        $errors['department'] = 'Выберите отдел';
    } elseif (!in_array($department, $departments, true)) {
        $errors['department'] = 'Такого отдела нет в списке';
    }

    // Стаж: целое число от 0 до 50
    $experience = $input['experience'] ?? '';
    if ($experience === '') {
        $errors['experience'] = 'Укажите стаж';
    } elseif (filter_var($experience, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50]]) === false) {
        $errors['experience'] = 'Стаж должен быть целым числом от 0 до 50';
    }

    return $errors;
}

// Прогоняет сценарии из form_cases.json через validateInput.
// В сценариях есть только имя и стаж, остальные поля подставляем корректные.
function runFormCases(array $cases, array $departments): array
{
    $results = [];

    foreach ($cases as $case) {
        $input = [
            'name'       => trim((string)($case['name'] ?? '')),
            'email'      => 'test@example.test',
            'department' => $departments[0] ?? '',
            'experience' => (string)($case['experience'] ?? ''),
        ];

        $errors = validateInput($input, $departments);
        $expected = (bool)($case['valid'] ?? false);
        $actual = count($errors) === 0;

        $results[] = [
            'case'       => (string)($case['case'] ?? ''),
            'name'       => $input['name'],
            'experience' => $input['experience'],
            'expected'   => $expected,
            'actual'     => $actual,
            'errors'     => $errors,
            'ok'         => $expected === $actual,
        ];
    }

    return $results;
}
