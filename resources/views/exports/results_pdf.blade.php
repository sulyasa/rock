<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Отчет по викторине #{{ $session->pin }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 20px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #34495e;
            padding-bottom: 10px;
            font-size: 24px;
        }
        h2 {
            color: #2980b9;
            margin-top: 30px;
            font-size: 18px;
        }
        .meta-info {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
        }
        .meta-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #bdc3c7;
            padding: 10px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #ecf0f1;
            font-weight: bold;
            color: #2c3e50;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            background-color: #2ecc71;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
        }
        .chart-placeholder {
            border: 1px dashed #7f8c8d;
            background-color: #fcfcfc;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <h1>Сводный отчет по игре: {{ $session->quiz->title }}</h1>
    
    <div class="meta-info">
        <p><strong>PIN-код сессии:</strong> {{ $session->pin }}</p>
        <p><strong>Статус:</strong> Завершена</p>
        <p><strong>Дата завершения:</strong> {{ $session->updated_at->format('d.m.Y H:i') }}</p>
        <p><strong>Количество раундов:</strong> {{ $session->quiz->rounds_count }}</p>
    </div>

    <h2>Таблица Лидеров (Топ-10)</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;" class="text-center">Место</th>
                <th style="width: 50%;">Имя игрока</th>
                <th style="width: 20%;" class="text-center">Всего очков</th>
                <th style="width: 20%;" class="text-center">Правильных ответов</th>
            </tr>
        </thead>
        <tbody>
            @foreach($leaderboard->take(10) as $index => $row)
                <tr>
                    <td class="text-center"><strong>{{ $index + 1 }}</strong></td>
                    <td>{{ $row->name }}</td>
                    <td class="text-center">{{ $row->total_points }}</td>
                    <td class="text-center"><span class="badge">{{ $row->correct_answers }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break" style="page-break-before: always;"></div>

    <h2>Статистика по вопросам</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;" class="text-center">№</th>
                <th style="width: 50%;">Текст вопроса</th>
                <th style="width: 20%;" class="text-center">Всего ответов</th>
                <th style="width: 20%;" class="text-center">Процент верных</th>
            </tr>
        </thead>
        <tbody>
            @foreach($questionsStats as $stat)
                <tr>
                    <td class="text-center">{{ $stat->order }}</td>
                    <td>{{ $stat->question_text }}</td>
                    <td class="text-center">{{ $stat->total_responses }}</td>
                    <td class="text-center">
                        {{ $stat->total_responses > 0 
                            ? round(($stat->correct_responses / $stat->total_responses) * 100, 1) . '%' 
                            : '0%' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="chart-placeholder">
        [ Визуализация графиков активности игроков и распределения ответов (Chart.js) ]
    </div>

</body>
</html>
